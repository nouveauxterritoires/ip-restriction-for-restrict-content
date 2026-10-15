<?php
/**
 * Le blocage d'inscription, tel qu'un visiteur l'éprouve — FNC-07 à FNC-12,
 * A-01, SEC-03, SEC-17.
 *
 * Les tests passent par `rcp_process_registration()`, le traitement réel de la
 * dépendance : ce qui compte est le comportement observable, et c'est aussi la
 * seule façon d'établir que l'erreur ajoutée **annule** l'inscription au lieu de
 * s'afficher à côté d'une inscription réussie.
 *
 * L'échappement y est vérifié pour une raison précise, consignée en JE-023 : la
 * dépendance interpole les messages d'erreur dans son balisage **sans les
 * échapper**. Notre échappement n'est donc pas une précaution redondante, c'est
 * la seule barrière.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Access;

use Ip_Restriction_For_Restrict_Content\Integration\Cache_Guard;
use Ip_Restriction_For_Restrict_Content\Integration\Content_Access;
use Ip_Restriction_For_Restrict_Content\Integration\Registration_Guard;
use Ip_Restriction_For_Restrict_Content\Settings\Settings;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use Ip_Restriction_For_Restrict_Content\Support\Extension_Points;
use Ip_Restriction_For_Restrict_Content\Tests\Support\Request_Halted;
use WP_UnitTestCase;

/**
 * Comportement observable de la liste de blocage.
 */
final class RegistrationBlockTest extends WP_UnitTestCase {

	/**
	 * Adresse inscrite dans la liste de blocage.
	 *
	 * @var string
	 */
	private const BLOCKED = '203.0.113.9';

	/**
	 * Adresse absente de toute liste.
	 *
	 * @var string
	 */
	private const OUTSIDE = '198.51.100.9';

	/**
	 * Prépare l'environnement de requête.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		remove_all_actions( 'rcp_form_errors' );
		remove_all_filters( 'rcp_member_can_access' );

		$this->reset_dependency_errors();

		$_SERVER['REMOTE_ADDR'] = self::OUTSIDE;
		$_POST                  = array();
	}

	/**
	 * Vide le collecteur d'erreurs de la dépendance.
	 *
	 * `rcp_errors()` renvoie un `WP_Error` **statique**, partagé par tout le
	 * processus : un message ajouté par un test survit au suivant, et comme
	 * `WP_Error::get_error_message()` renvoie le premier message d'un code,
	 * c'est le message du premier test qui s'affiche dans tous les autres.
	 * Sans cette remise à zéro, tous les tests de ce fichier sauf le premier
	 * éprouvent l'état laissé par leurs prédécesseurs (JE-024).
	 *
	 * En production, le collecteur vit le temps d'une requête : le problème est
	 * propre au processus de test, et sa solution y appartient aussi.
	 *
	 * @return void
	 */
	private function reset_dependency_errors(): void {
		foreach ( rcp_errors()->get_error_codes() as $code ) {
			rcp_errors()->remove( $code );
		}
	}

	/**
	 * Remet l'environnement en état.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_POST = array();

		foreach ( array( Extension_Points::REGISTRATION_BLOCKED, Extension_Points::BLOCKED_MESSAGE, Extension_Points::REGISTRATION_BLOCK_APPLIED ) as $hook ) {
			remove_all_filters( $hook );
			remove_all_actions( $hook );
		}

		parent::tear_down();
	}

	/**
	 * Inscrit des réglages et rebranche le garde.
	 *
	 * @param array<string, string> $values Réglages.
	 * @return void
	 */
	private function configure( array $values ): void {
		( new Settings_Repository() )->save( Settings::from_array( $values ) );

		remove_all_actions( 'rcp_form_errors' );

		( new Registration_Guard( new Settings_Repository() ) )->register();
	}

	/**
	 * Prépare une inscription recevable sur un niveau gratuit.
	 *
	 * @return void
	 */
	private function prepare_registration(): void {
		$this->reset_dependency_errors();

		$level_id = rcp_add_membership_level(
			array(
				'name'     => 'Gratuit',
				'price'    => 0,
				'duration' => 0,
				'status'   => 'active',
			)
		);

		rcp_setup_registration( (int) $level_id );

		$_POST = array(
			'rcp_register_nonce'    => wp_create_nonce( 'rcp-register-nonce' ),
			'rcp_level'             => (int) $level_id,
			'rcp_gateway'           => 'free',
			'rcp_user_login'        => 'inscription-test',
			'rcp_user_email'        => 'inscription@example.test',
			'rcp_user_pass'         => 'mot-de-passe-de-test',
			'rcp_user_pass_confirm' => 'mot-de-passe-de-test',
		);
	}

	/**
	 * Rejoue le traitement de l'inscription et renvoie la charge décodée.
	 *
	 * @return array<string, mixed>
	 */
	private function process(): array {
		Request_Halted::intercept_wp_die();

		ob_start();

		try {
			rcp_process_registration();
		} catch ( Request_Halted $halted ) {
			unset( $halted );
		}

		$payload = (string) ob_get_clean();
		$decoded = json_decode( $payload, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Une adresse bloquée ne peut pas s'inscrire (FNC-07).
	 *
	 * @return void
	 */
	public function test_a_blocked_address_cannot_register(): void {
		$this->configure( array( 'block_list' => self::BLOCKED ) );
		$this->prepare_registration();

		$_SERVER['REMOTE_ADDR'] = self::BLOCKED;

		$decoded = $this->process();

		$this->assertFalse( $decoded['success'] ?? true, 'L\'inscription doit être annulée.' );
		$this->assertFalse( username_exists( 'inscription-test' ), 'Aucun compte ne doit avoir été créé.' );
	}

	/**
	 * Une adresse absente de la liste s'inscrit normalement (FNC-31).
	 *
	 * @return void
	 */
	public function test_an_address_outside_the_list_registers_normally(): void {
		$this->configure( array( 'block_list' => self::BLOCKED ) );
		$this->prepare_registration();

		$_SERVER['REMOTE_ADDR'] = self::OUTSIDE;

		$decoded = $this->process();

		$this->assertTrue( $decoded['success'] ?? false, 'Le plugin ne doit pas gêner une inscription légitime.' );
	}

	/**
	 * Une liste de blocage vide ne bloque personne (FNC-27).
	 *
	 * @return void
	 */
	public function test_an_empty_block_list_blocks_nobody(): void {
		$this->configure( array() );
		$this->prepare_registration();

		$_SERVER['REMOTE_ADDR'] = self::BLOCKED;

		$this->assertTrue( $this->process()['success'] ?? false );
	}

	/**
	 * Le message par défaut ne révèle ni l'adresse, ni la règle, ni
	 * l'existence d'une liste (FNC-11, SEC-17).
	 *
	 * @return void
	 */
	public function test_the_default_message_reveals_nothing(): void {
		$this->configure( array( 'block_list' => self::BLOCKED ) );
		$this->prepare_registration();

		$_SERVER['REMOTE_ADDR'] = self::BLOCKED;

		$errors = (string) ( $this->process()['data']['errors'] ?? '' );

		$this->assertNotSame( '', $errors );
		$this->assertStringNotContainsString( self::BLOCKED, $errors, 'Le message ne révèle pas l\'adresse.' );
		$this->assertStringNotContainsString( 'liste', $errors, 'Le message ne révèle pas l\'existence d\'une liste.' );
		$this->assertStringContainsString( 'connexion', $errors );
	}

	/**
	 * Le message est identique quel que soit le motif (SEC-17).
	 *
	 * Un message qui varierait selon la règle qui a filtré permettrait de
	 * deviner le contenu de la liste par essais successifs.
	 *
	 * @return void
	 */
	public function test_the_message_is_identical_whatever_the_rule(): void {
		$this->configure( array( 'block_list' => "203.0.113.9\n203.0.113.0/24" ) );

		$this->prepare_registration();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		$first                  = (string) ( $this->process()['data']['errors'] ?? '' );

		$this->prepare_registration();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.50';
		$second                 = (string) ( $this->process()['data']['errors'] ?? '' );

		$this->assertSame( $first, $second );
	}

	/**
	 * Le message personnalisé remplace le défaut (FNC-10).
	 *
	 * @return void
	 */
	public function test_a_custom_message_replaces_the_default(): void {
		$this->configure(
			array(
				'block_list'      => self::BLOCKED,
				'blocked_message' => 'Nous ne prenons pas de nouvelles inscriptions depuis ce réseau.',
			)
		);
		$this->prepare_registration();

		$_SERVER['REMOTE_ADDR'] = self::BLOCKED;

		$this->assertStringContainsString(
			'depuis ce réseau',
			(string) ( $this->process()['data']['errors'] ?? '' )
		);
	}

	/**
	 * Un message porteur de balises est rendu inerte (SEC-03).
	 *
	 * C'est le test que JE-023 rend indispensable : la dépendance interpole le
	 * message sans l'échapper, donc un message non échappé par nous
	 * s'exécuterait dans la page du visiteur.
	 *
	 * @return void
	 */
	public function test_a_message_carrying_markup_is_rendered_inert(): void {
		$this->configure(
			array(
				'block_list'      => self::BLOCKED,
				'blocked_message' => '<script>alert(1)</script>Non.',
			)
		);
		$this->prepare_registration();

		$_SERVER['REMOTE_ADDR'] = self::BLOCKED;

		$errors = (string) ( $this->process()['data']['errors'] ?? '' );

		$this->assertStringNotContainsString( '<script>', $errors );
		$this->assertStringContainsString( '&lt;script&gt;', $errors );
	}

	/**
	 * Un message injecté par un filtre est échappé lui aussi (SEC-03).
	 *
	 * Un filtre est du code tiers : lui faire confiance pour l'échappement
	 * reviendrait à ne pas échapper.
	 *
	 * @return void
	 */
	public function test_a_filtered_message_is_escaped_too(): void {
		$this->configure( array( 'block_list' => self::BLOCKED ) );
		$this->prepare_registration();

		add_filter( Extension_Points::BLOCKED_MESSAGE, static fn(): string => '<img src=x onerror=alert(1)>' );

		$_SERVER['REMOTE_ADDR'] = self::BLOCKED;

		$errors = (string) ( $this->process()['data']['errors'] ?? '' );

		$this->assertStringNotContainsString( '<img', $errors );
		$this->assertStringContainsString( '&lt;img', $errors );
	}

	/**
	 * La liste de blocage n'a aucun effet sur l'accès au contenu (FNC-08,
	 * A-01).
	 *
	 * C'est la première moitié de l'asymétrie : une adresse bloquée qui
	 * consulte un contenu restreint n'obtient ni plus ni moins que ce que la
	 * dépendance lui accorde.
	 *
	 * @return void
	 */
	public function test_the_block_list_does_not_touch_content_access(): void {
		$this->configure( array( 'block_list' => self::BLOCKED ) );

		$level_id = rcp_add_membership_level(
			array(
				'name'     => 'Or',
				'price'    => 10,
				'duration' => 1,
				'status'   => 'active',
			)
		);

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'rcp_subscription_level', array( (int) $level_id ) );

		$public_id = self::factory()->post->create();

		$_SERVER['REMOTE_ADDR'] = self::BLOCKED;

		( new Content_Access( new Settings_Repository(), new Cache_Guard() ) )->register();

		wp_set_current_user( 0 );

		$this->assertFalse( rcp_user_can_access( 0, $post_id ), 'Le contenu restreint reste régi par la dépendance.' );
		$this->assertTrue( rcp_user_can_access( 0, $public_id ), 'Un contenu public reste accessible à une adresse bloquée.' );
	}

	/**
	 * Une adresse de la liste d'autorisation n'est **pas** bloquée à
	 * l'inscription (A-01).
	 *
	 * C'est la seconde moitié de l'asymétrie, et la plus importante : une
	 * confusion de champ ne doit pas pouvoir transformer une autorisation en
	 * blocage, ni l'inverse.
	 *
	 * @return void
	 */
	public function test_an_allowed_address_is_not_blocked_at_registration(): void {
		$this->configure( array( 'allow_list' => self::BLOCKED ) );
		$this->prepare_registration();

		$_SERVER['REMOTE_ADDR'] = self::BLOCKED;

		$this->assertTrue(
			$this->process()['success'] ?? false,
			'Une adresse de la liste d\'autorisation ne doit pas être bloquée à l\'inscription.'
		);
	}

	/**
	 * Le blocage n'a aucun effet sur la connexion d'un compte existant
	 * (FNC-09).
	 *
	 * @return void
	 */
	public function test_the_block_list_does_not_touch_authentication(): void {
		$this->configure( array( 'block_list' => self::BLOCKED ) );

		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'deja-inscrit',
				'user_pass'  => 'mot-de-passe-connu',
				'role'       => 'subscriber',
			)
		);

		$_SERVER['REMOTE_ADDR'] = self::BLOCKED;

		$authenticated = wp_authenticate( 'deja-inscrit', 'mot-de-passe-connu' );

		$this->assertInstanceOf( 'WP_User', $authenticated );
		$this->assertSame( $user_id, $authenticated->ID );
	}

	/**
	 * La liste de blocage n'existe qu'à la portée globale (FNC-12).
	 *
	 * Le test porte sur la forme des réglages : étendre le blocage à d'autres
	 * portées serait une invention, pas une reprise — la documentation amont ne
	 * décrit le champ de blocage que sur l'écran global.
	 *
	 * @return void
	 */
	public function test_the_block_list_exists_only_at_the_global_scope(): void {
		$keys = array_keys( Settings::defaults()->to_array() );

		$this->assertContains( 'block_list', $keys );

		foreach ( $keys as $key ) {
			$this->assertStringNotContainsString( 'level_block', $key );
			$this->assertStringNotContainsString( 'post_block', $key );
		}
	}

	/**
	 * Le filtre de blocage peut retirer un blocage, et en ajouter un.
	 *
	 * L'asymétrie est assumée par le §4.6 : une erreur par excès ici refuse
	 * des inscriptions légitimes, ce qui est bruyant et donc corrigé, là où la
	 * même erreur sur l'accès serait une fuite silencieuse.
	 *
	 * @return void
	 */
	public function test_the_block_filter_works_both_ways(): void {
		$this->configure( array( 'block_list' => self::BLOCKED ) );
		$this->prepare_registration();

		$_SERVER['REMOTE_ADDR'] = self::BLOCKED;

		add_filter( Extension_Points::REGISTRATION_BLOCKED, '__return_false' );

		$this->assertTrue( $this->process()['success'] ?? false, 'Le filtre doit pouvoir retirer un blocage.' );

		remove_all_filters( Extension_Points::REGISTRATION_BLOCKED );

		$this->configure( array() );
		$this->prepare_registration();

		add_filter( Extension_Points::REGISTRATION_BLOCKED, '__return_true' );

		$this->assertFalse( $this->process()['success'] ?? true, 'Le filtre doit pouvoir ajouter un blocage.' );
	}

	/**
	 * L'action de blocage transporte une adresse expurgée (SEC-06).
	 *
	 * @return void
	 */
	public function test_the_block_action_carries_a_redacted_address(): void {
		$received = null;

		add_action(
			Extension_Points::REGISTRATION_BLOCK_APPLIED,
			static function ( $address ) use ( &$received ): void {
				$received = $address;
			}
		);

		$this->configure( array( 'block_list' => self::BLOCKED ) );
		$this->prepare_registration();

		$_SERVER['REMOTE_ADDR'] = self::BLOCKED;
		$this->process();

		$this->assertSame( '203.0.113.x', $received );
		$this->assertStringNotContainsString( self::BLOCKED, (string) $received );
	}
}
