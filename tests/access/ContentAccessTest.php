<?php
/**
 * L'autorisation globale, telle qu'un visiteur l'éprouve — FNC-01, FNC-05,
 * FNC-30, FNC-31, SEC-13, SEC-14.
 *
 * C'est le premier lot où le plugin change quelque chose pour un visiteur. Les
 * tests passent donc par la fonction de la dépendance — `rcp_user_can_access()`
 * — et non par la politique interne : ce qui compte est le comportement
 * observable, moteur et branchement compris.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Access;

use Ip_Restriction_For_Restrict_Content\Integration\Cache_Guard;
use Ip_Restriction_For_Restrict_Content\Integration\Content_Access;
use Ip_Restriction_For_Restrict_Content\Settings\Settings;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use WP_UnitTestCase;

/**
 * Comportement observable de la liste d'autorisation globale.
 */
final class ContentAccessTest extends WP_UnitTestCase {

	/**
	 * Adresse inscrite dans la liste d'autorisation.
	 *
	 * @var string
	 */
	private const ALLOWED = '198.51.100.9';

	/**
	 * Adresse absente de toute liste.
	 *
	 * @var string
	 */
	private const OUTSIDE = '203.0.113.9';

	/**
	 * Marquage de non-cachabilité de l'instance étudiée.
	 *
	 * @var Cache_Guard
	 */
	private Cache_Guard $cache;

	/**
	 * Prépare le branchement et l'environnement de requête.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->cache = new Cache_Guard();

		remove_all_filters( 'rcp_member_can_access' );

		( new Content_Access( new Settings_Repository(), $this->cache ) )->register();

		$_SERVER['REMOTE_ADDR'] = self::OUTSIDE;
	}

	/**
	 * Rebranche une instance neuve, ce qui simule la requête suivante.
	 *
	 * L'adresse retenue est mémorisée par **instance**, et l'instance vit le
	 * temps d'une requête : un contenu restreint peut être évalué des dizaines
	 * de fois dans une page, et résoudre l'adresse autant de fois n'apprendrait
	 * rien de plus. Un test qui change `$_SERVER` en cours de route doit donc
	 * rebrancher, sans quoi il éprouverait une situation qu'aucune requête
	 * réelle ne présente.
	 *
	 * @return void
	 */
	private function next_request(): void {
		remove_all_filters( 'rcp_member_can_access' );

		$this->cache = new Cache_Guard();

		( new Content_Access( new Settings_Repository(), $this->cache ) )->register();
	}

	/**
	 * Inscrit une liste d'autorisation globale.
	 *
	 * @param string $entries Liste, une entrée par ligne.
	 * @return void
	 */
	private function allow( string $entries ): void {
		( new Settings_Repository() )->save( Settings::from_array( array( 'allow_list' => $entries ) ) );
	}

	/**
	 * Crée un contenu restreint à un niveau d'adhésion.
	 *
	 * @return int Identifiant du contenu.
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
		update_post_meta( $post_id, 'rcp_access_level', 0 );

		return $post_id;
	}

	/**
	 * Une adresse de la liste globale accède à un contenu restreint (FNC-01).
	 *
	 * @return void
	 */
	public function test_an_allowed_address_reads_restricted_content(): void {
		$post_id = $this->restricted_post();

		$this->allow( self::ALLOWED );
		$_SERVER['REMOTE_ADDR'] = self::ALLOWED;

		wp_set_current_user( 0 );

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * Une adresse absente des listes obtient le comportement de la dépendance
	 * seule (FNC-31).
	 *
	 * @return void
	 */
	public function test_an_address_outside_the_list_changes_nothing(): void {
		$post_id = $this->restricted_post();

		$this->allow( self::ALLOWED );
		$_SERVER['REMOTE_ADDR'] = self::OUTSIDE;

		wp_set_current_user( 0 );

		$this->assertFalse( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * Une installation sans liste ne change rien (FNC-27, FNC-31).
	 *
	 * @return void
	 */
	public function test_an_empty_list_changes_nothing(): void {
		$post_id = $this->restricted_post();

		$_SERVER['REMOTE_ADDR'] = self::ALLOWED;

		wp_set_current_user( 0 );

		$this->assertFalse( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * L'accès ne dépend pas de l'état de connexion (FNC-05).
	 *
	 * Le même contenu, la même adresse, et deux visiteurs : un sans compte, un
	 * avec. La dépendance emprunte deux chemins différents pour décider
	 * (§5.1), et le plugin doit répondre de la même façon des deux.
	 *
	 * @return void
	 */
	public function test_access_does_not_depend_on_being_logged_in(): void {
		$post_id = $this->restricted_post();

		$this->allow( self::ALLOWED );
		$_SERVER['REMOTE_ADDR'] = self::ALLOWED;

		wp_set_current_user( 0 );
		$this->assertTrue( rcp_user_can_access( 0, $post_id ), 'Chemin déconnecté.' );

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		$this->assertTrue( rcp_user_can_access( $user_id, $post_id ), 'Chemin connecté sans client.' );
	}

	/**
	 * L'accès ne dépend pas de l'existence d'un compte client (FNC-05).
	 *
	 * @return void
	 */
	public function test_access_does_not_depend_on_a_customer_record(): void {
		$post_id = $this->restricted_post();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		rcp_add_customer( array( 'user_id' => $user_id ) );

		$this->allow( self::ALLOWED );
		$_SERVER['REMOTE_ADDR'] = self::ALLOWED;

		wp_set_current_user( $user_id );

		$this->assertTrue( rcp_user_can_access( $user_id, $post_id ) );
	}

	/**
	 * Une adresse autorisée l'emporte sur une adhésion **expirée** — V-01.
	 *
	 * L'attendu du cahier des charges est que l'autorisation l'emporte, parce
	 * que FNC-05 pose que l'accès ne dépend ni de l'état de connexion ni de
	 * l'adhésion : une adhésion expirée n'est pas un cas particulier, c'est
	 * l'absence d'adhésion. Le test l'établit sur les deux chemins, avec et
	 * sans enregistrement client, parce que la dépendance ne décide pas au même
	 * endroit dans les deux cas.
	 *
	 * @return void
	 */
	public function test_an_allowed_address_outlives_an_expired_membership(): void {
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

		$user_id     = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$customer_id = rcp_add_customer( array( 'user_id' => $user_id ) );

		$membership_id = rcp_add_membership(
			array(
				'customer_id'     => (int) $customer_id,
				'object_id'       => (int) $level_id,
				'status'          => 'expired',
				'expiration_date' => '2020-01-01 00:00:00',
			)
		);

		$this->assertNotEmpty( $membership_id, 'La dépendance n\'a pas créé l\'adhésion attendue.' );

		wp_set_current_user( $user_id );
		$_SERVER['REMOTE_ADDR'] = self::OUTSIDE;

		$this->assertFalse(
			rcp_user_can_access( $user_id, $post_id ),
			'Sans autorisation, une adhésion expirée ne donne pas accès : c\'est le comportement de la dépendance.'
		);

		$this->allow( self::ALLOWED );
		$_SERVER['REMOTE_ADDR'] = self::ALLOWED;
		$this->next_request();

		$this->assertTrue(
			rcp_user_can_access( $user_id, $post_id ),
			'V-01 : l\'autorisation l\'emporte sur une adhésion expirée.'
		);
	}

	/**
	 * L'autorisation n'accorde aucune capacité WordPress (FNC-30, SEC-13).
	 *
	 * C'est l'exigence qui distingue « lire un contenu » de « devenir
	 * administrateur » : le branchement ne renvoie qu'un booléen à la question
	 * de l'accès au contenu, et il ne peut rien accorder d'autre.
	 *
	 * @return void
	 */
	public function test_an_authorisation_grants_no_capability(): void {
		$post_id = $this->restricted_post();

		$this->allow( self::ALLOWED );
		$_SERVER['REMOTE_ADDR'] = self::ALLOWED;

		wp_set_current_user( 0 );

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );

		$this->assertFalse( current_user_can( 'manage_options' ) );
		$this->assertFalse( current_user_can( 'edit_posts' ) );
		$this->assertFalse( current_user_can( 'read_private_posts' ) );
		$this->assertFalse( current_user_can( 'rcp_manage_settings' ) );
		$this->assertFalse( is_user_logged_in(), 'L\'autorisation ne connecte personne.' );
	}

	/**
	 * L'autorisation ne crée ni adhésion ni client (FNC-30).
	 *
	 * @return void
	 */
	public function test_an_authorisation_creates_no_membership(): void {
		$post_id = $this->restricted_post();

		$this->allow( self::ALLOWED );
		$_SERVER['REMOTE_ADDR'] = self::ALLOWED;

		wp_set_current_user( 0 );
		rcp_user_can_access( 0, $post_id );

		$this->assertEmpty( rcp_get_customer_by_user_id( 0 ) );
	}

	/**
	 * Une réponse élargie par une autorisation est marquée non cachable
	 * (SEC-14).
	 *
	 * @return void
	 */
	public function test_a_widened_response_is_marked_non_cacheable(): void {
		$post_id = $this->restricted_post();

		$this->allow( self::ALLOWED );
		$_SERVER['REMOTE_ADDR'] = self::ALLOWED;

		wp_set_current_user( 0 );

		$this->assertFalse( $this->cache->has_marked(), 'Rien n\'est marqué avant qu\'une autorisation ne s\'applique.' );

		rcp_user_can_access( 0, $post_id );

		$this->assertTrue( $this->cache->has_marked() );
	}

	/**
	 * Une réponse que le plugin n'a pas élargie n'est pas marquée (SEC-14).
	 *
	 * L'exigence n'est pas de désactiver le cache : c'est de ne jamais laisser
	 * une réponse élargie entrer dans un cache partagé. Marquer toutes les
	 * réponses coûterait le cache du site entier et masquerait l'exigence.
	 *
	 * @return void
	 */
	public function test_an_untouched_response_is_not_marked(): void {
		$post_id = $this->restricted_post();

		$this->allow( self::ALLOWED );
		$_SERVER['REMOTE_ADDR'] = self::OUTSIDE;

		wp_set_current_user( 0 );
		rcp_user_can_access( 0, $post_id );

		$this->assertFalse( $this->cache->has_marked() );
	}

	/**
	 * Un contenu public n'est pas marqué non cachable.
	 *
	 * La dépendance accorde déjà l'accès : le plugin n'a rien à ajouter, et
	 * marquer la page priverait le site de son cache sur tout le contenu
	 * public.
	 *
	 * @return void
	 */
	public function test_public_content_is_not_marked(): void {
		$post_id = self::factory()->post->create();

		$this->allow( self::ALLOWED );
		$_SERVER['REMOTE_ADDR'] = self::ALLOWED;

		wp_set_current_user( 0 );

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
		$this->assertFalse( $this->cache->has_marked() );
	}

	/**
	 * Une adresse indéterminable n'accorde rien (SEC-10).
	 *
	 * @return void
	 */
	public function test_an_undetermined_address_grants_nothing(): void {
		$post_id = $this->restricted_post();

		$this->allow( self::ALLOWED );
		unset( $_SERVER['REMOTE_ADDR'] );

		wp_set_current_user( 0 );

		$this->assertFalse( rcp_user_can_access( 0, $post_id ) );
		$this->assertFalse( $this->cache->has_marked() );
	}

	/**
	 * Derrière un proxy déclaré, l'adresse de l'en-tête décide.
	 *
	 * C'est la chaîne complète du plugin, de `$_SERVER` à la décision : le
	 * seul test qui l'exerce de bout en bout.
	 *
	 * @return void
	 */
	public function test_behind_a_declared_proxy_the_header_decides(): void {
		$post_id = $this->restricted_post();

		( new Settings_Repository() )->save(
			Settings::from_array(
				array(
					'allow_list'       => self::ALLOWED,
					'trusted_proxies'  => '203.0.113.7',
					'forwarded_header' => 'X-Forwarded-For',
				)
			)
		);

		$_SERVER['REMOTE_ADDR']          = '203.0.113.7';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.10, ' . self::ALLOWED;

		wp_set_current_user( 0 );

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}
}
