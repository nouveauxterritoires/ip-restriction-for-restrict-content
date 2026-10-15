<?php
/**
 * Les points d'extension de la décision d'accès — §8.
 *
 * Deux d'entre eux portent une contrainte de sécurité, et c'est pour eux que ce
 * fichier existe : `ip_restriction_for_restrict_content_match` et
 * `..._access_decision` ne peuvent que **retirer** un octroi, jamais en
 * ajouter. Un filtre capable d'accorder transformerait tout plugin tiers en
 * fournisseur d'accès au contenu payant, et mettrait le contrôle hors de portée
 * de l'administrateur qui a rempli les listes.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Access;

use Ip_Restriction_For_Restrict_Content\Address\Client_Address;
use Ip_Restriction_For_Restrict_Content\Integration\Cache_Guard;
use Ip_Restriction_For_Restrict_Content\Integration\Content_Access;
use Ip_Restriction_For_Restrict_Content\Policy\Access_Decision;
use Ip_Restriction_For_Restrict_Content\Settings\Settings;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use Ip_Restriction_For_Restrict_Content\Support\Extension_Points;
use WP_UnitTestCase;

/**
 * Comportement des points d'extension sur le chemin d'accès.
 */
final class ExtensionPointsTest extends WP_UnitTestCase {

	/**
	 * Adresse du visiteur dans tous les tests de ce fichier.
	 *
	 * @var string
	 */
	private const VISITOR = '198.51.100.9';

	/**
	 * Marquage de non-cachabilité.
	 *
	 * @var Cache_Guard
	 */
	private Cache_Guard $cache;

	/**
	 * Prépare l'environnement.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		remove_all_filters( 'rcp_member_can_access' );

		$_SERVER['REMOTE_ADDR'] = self::VISITOR;

		wp_set_current_user( 0 );
	}

	/**
	 * Retire les filtres posés par un test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( array( Extension_Points::CLIENT_ADDRESS, Extension_Points::TRUSTED_PROXIES, Extension_Points::RULES, Extension_Points::ACCESS_DECISION, Extension_Points::ACCESS_GRANTED ) as $hook ) {
			remove_all_filters( $hook );
			remove_all_actions( $hook );
		}

		parent::tear_down();
	}

	/**
	 * Branche une instance neuve et renvoie son marquage.
	 *
	 * @return void
	 */
	private function boot(): void {
		remove_all_filters( 'rcp_member_can_access' );

		$this->cache = new Cache_Guard();

		( new Content_Access( new Settings_Repository(), $this->cache ) )->register();
	}

	/**
	 * Inscrit des réglages.
	 *
	 * @param array<string, string> $values Réglages.
	 * @return void
	 */
	private function store( array $values ): void {
		( new Settings_Repository() )->save( Settings::from_array( $values ) );
	}

	/**
	 * Crée un contenu restreint.
	 *
	 * @return int
	 */
	private function restricted_post(): int {
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

		return $post_id;
	}

	/**
	 * Le filtre des règles complète la liste depuis une source externe.
	 *
	 * @return void
	 */
	public function test_the_rules_filter_can_complete_the_list(): void {
		$post_id = $this->restricted_post();

		add_filter( Extension_Points::RULES, static fn(): string => self::VISITOR );

		$this->boot();

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * Le filtre de décision peut **retirer** un octroi.
	 *
	 * @return void
	 */
	public function test_the_decision_filter_can_remove_a_grant(): void {
		$post_id = $this->restricted_post();

		$this->store( array( 'allow_list' => self::VISITOR ) );

		add_filter( Extension_Points::ACCESS_DECISION, static fn(): Access_Decision => Access_Decision::not_handled() );

		$this->boot();

		$this->assertFalse( rcp_user_can_access( 0, $post_id ) );
		$this->assertFalse( $this->cache->has_marked(), 'Un octroi retiré ne doit pas marquer la réponse.' );
	}

	/**
	 * Le filtre de décision ne peut **pas** créer un octroi.
	 *
	 * C'est la contrainte qui compte : sans elle, n'importe quel plugin tiers
	 * deviendrait un fournisseur d'accès au contenu payant.
	 *
	 * @return void
	 */
	public function test_the_decision_filter_cannot_create_a_grant(): void {
		$post_id = $this->restricted_post();
		$rule    = \Ip_Restriction_For_Restrict_Content\Rule\Rule_Set::from_text( '0.0.0.0/1' )->rules()[0];

		add_filter(
			Extension_Points::ACCESS_DECISION,
			static fn(): Access_Decision => Access_Decision::granted_by(
				\Ip_Restriction_For_Restrict_Content\Policy\Scope::GLOBAL_LIST,
				$rule
			)
		);

		$this->boot();

		$this->assertFalse(
			rcp_user_can_access( 0, $post_id ),
			'Aucune liste n\'autorise ce visiteur : un filtre ne doit pas pouvoir l\'autoriser.'
		);
		$this->assertFalse( $this->cache->has_marked() );
	}

	/**
	 * Une valeur de retour qui n'est pas une décision retire l'octroi.
	 *
	 * La fermeture sur l'inconnu vaut aussi pour ce que renvoie un tiers : un
	 * filtre qui renvoie n'importe quoi ne doit pas accorder un accès.
	 *
	 * @return void
	 */
	public function test_a_nonsense_return_removes_the_grant(): void {
		$post_id = $this->restricted_post();

		$this->store( array( 'allow_list' => self::VISITOR ) );

		add_filter( Extension_Points::ACCESS_DECISION, static fn(): string => 'oui' );

		$this->boot();

		$this->assertFalse( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * Le filtre d'adresse a le dernier mot sur l'adresse retenue.
	 *
	 * @return void
	 */
	public function test_the_address_filter_has_the_last_word(): void {
		$post_id = $this->restricted_post();

		$this->store( array( 'allow_list' => '192.0.2.10' ) );

		add_filter(
			Extension_Points::CLIENT_ADDRESS,
			static fn(): Client_Address => Client_Address::from_connection(
				\Ip_Restriction_For_Restrict_Content\Address\Ip_Number::from_address( '192.0.2.10' )
			)
		);

		$this->boot();

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * Un retour qui n'est pas une adresse est ignoré.
	 *
	 * @return void
	 */
	public function test_a_nonsense_address_return_is_ignored(): void {
		$post_id = $this->restricted_post();

		$this->store( array( 'allow_list' => self::VISITOR ) );

		add_filter( Extension_Points::CLIENT_ADDRESS, static fn(): string => '192.0.2.10' );

		$this->boot();

		$this->assertTrue(
			rcp_user_can_access( 0, $post_id ),
			'L\'adresse réellement résolue doit rester celle qui décide.'
		);
	}

	/**
	 * Le filtre de proxys déclare un proxy par code.
	 *
	 * @return void
	 */
	public function test_the_proxy_filter_declares_a_proxy_by_code(): void {
		$post_id = $this->restricted_post();

		$this->store(
			array(
				'allow_list'       => '198.51.100.20',
				'forwarded_header' => 'X-Forwarded-For',
			)
		);

		add_filter( Extension_Points::TRUSTED_PROXIES, static fn(): string => self::VISITOR );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.20';

		$this->boot();

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * L'action d'octroi est déclenchée avec une adresse **expurgée** (SEC-06).
	 *
	 * Ce que l'exploitant journalise ensuite relève de sa responsabilité ; ce
	 * que le plugin lui remet relève de la nôtre.
	 *
	 * @return void
	 */
	public function test_the_granted_action_carries_a_redacted_address(): void {
		$post_id  = $this->restricted_post();
		$received = array();

		$this->store( array( 'allow_list' => self::VISITOR ) );

		add_action(
			Extension_Points::ACCESS_GRANTED,
			static function ( $address, $scope, $post ) use ( &$received ): void {
				$received = array( $address, $scope, $post );
			},
			10,
			3
		);

		$this->boot();

		rcp_user_can_access( 0, $post_id );

		$this->assertSame( '198.51.100.x', $received[0] );
		$this->assertStringNotContainsString( self::VISITOR, $received[0] );
		$this->assertSame( 'global', $received[1] );
		$this->assertSame( $post_id, $received[2] );
	}

	/**
	 * L'action n'est pas déclenchée quand rien n'est accordé.
	 *
	 * @return void
	 */
	public function test_the_granted_action_stays_silent_without_a_grant(): void {
		$post_id = $this->restricted_post();
		$fired   = false;

		add_action(
			Extension_Points::ACCESS_GRANTED,
			static function () use ( &$fired ): void {
				$fired = true;
			}
		);

		$this->boot();

		rcp_user_can_access( 0, $post_id );

		$this->assertFalse( $fired );
	}

	/**
	 * Tous les noms de hooks portent la forme longue du préfixe (ADR-0001).
	 *
	 * Le test existe parce que la configuration de style ne peut pas vérifier
	 * un nom déclaré en constante de classe : l'exclusion nommée dans
	 * `phpcs.xml.dist` est compensée ici.
	 *
	 * @return void
	 */
	public function test_every_hook_name_carries_the_long_prefix(): void {
		$constants = ( new \ReflectionClass( Extension_Points::class ) )->getConstants();

		$this->assertNotSame( array(), $constants );

		foreach ( $constants as $name => $hook ) {
			$this->assertStringStartsWith(
				'ip_restriction_for_restrict_content_',
				$hook,
				$name . ' ne porte pas la forme longue du préfixe.'
			);
		}
	}
}
