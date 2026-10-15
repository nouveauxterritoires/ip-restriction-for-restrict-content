<?php
/**
 * La portée par contenu — FNC-03, FNC-04, FNC-26, SEC-02.
 *
 * **Le test central de ce lot est celui du contournement par identifiant.**
 * Un nonce valide prouve que la soumission vient bien d'un de nos formulaires,
 * et une capacité vérifiée « en général » prouve que l'auteur peut modifier
 * *quelque chose*. Ni l'un ni l'autre ne dit qu'il peut modifier **ce**
 * contenu-là. Un contributeur qui change un identifiant dans la requête poserait
 * sans cela une autorisation d'accès sur n'importe quel contenu du site.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Access;

use Ip_Restriction_For_Restrict_Content\Admin\Post_Metabox;
use Ip_Restriction_For_Restrict_Content\Integration\Cache_Guard;
use Ip_Restriction_For_Restrict_Content\Integration\Content_Access;
use Ip_Restriction_For_Restrict_Content\Policy\Scope;
use Ip_Restriction_For_Restrict_Content\Settings\Settings;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use Ip_Restriction_For_Restrict_Content\Support\Extension_Points;
use WP_UnitTestCase;

/**
 * Comportement observable de la portée par contenu.
 */
final class PostScopeTest extends WP_UnitTestCase {

	/**
	 * Adresse autorisée dans les tests.
	 *
	 * @var string
	 */
	private const ALLOWED = '198.51.100.9';

	/**
	 * Dépôt des réglages.
	 *
	 * @var Settings_Repository
	 */
	private Settings_Repository $repository;

	/**
	 * Prépare l'environnement.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		remove_all_filters( 'rcp_member_can_access' );

		$this->repository = new Settings_Repository();

		$_SERVER['REMOTE_ADDR'] = self::ALLOWED;
		$_POST                  = array();

		wp_set_current_user( 0 );
	}

	/**
	 * Remet l'environnement en état.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_POST           = array();
		$GLOBALS['post'] = null;

		remove_all_filters( Extension_Points::RULES );
		remove_all_actions( Extension_Points::ACCESS_GRANTED );

		parent::tear_down();
	}

	/**
	 * Branche la décision, ce qui simule la requête suivante.
	 *
	 * @return void
	 */
	private function boot(): void {
		remove_all_filters( 'rcp_member_can_access' );

		( new Content_Access( new Settings_Repository(), new Cache_Guard() ) )->register();
	}

	/**
	 * Crée un contenu restreint à un niveau d'adhésion.
	 *
	 * La restriction importe : sans elle la dépendance accorde l'accès
	 * d'elle-même, et le test n'observerait pas notre décision.
	 *
	 * @param int $author_id Auteur du contenu, 0 pour l'auteur par défaut.
	 * @return int
	 */
	private function restricted_post( int $author_id = 0 ): int {
		$level_id = rcp_add_membership_level(
			array(
				'name'     => 'Or',
				'price'    => 10,
				'duration' => 1,
				'status'   => 'active',
			)
		);

		$arguments = array( 'post_status' => 'publish' );

		if ( 0 !== $author_id ) {
			$arguments['post_author'] = $author_id;
		}

		$post_id = self::factory()->post->create( $arguments );

		update_post_meta( $post_id, 'rcp_subscription_level', array( (int) $level_id ) );

		return $post_id;
	}

	/**
	 * Une adresse de la liste d'un contenu accède à ce contenu (FNC-03).
	 *
	 * @return void
	 */
	public function test_an_address_on_a_post_list_reads_that_post(): void {
		$post_id = $this->restricted_post();

		$this->assertFalse( rcp_user_can_access( 0, $post_id ), 'Comportement de la dépendance seule.' );

		$this->repository->save_post_list( $post_id, self::ALLOWED );
		$this->boot();

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * **Portée croisée : l'autorisation d'un contenu n'en ouvre pas un autre**
	 * (FNC-04).
	 *
	 * @return void
	 */
	public function test_a_post_authorisation_does_not_open_another_post(): void {
		$allowed = $this->restricted_post();
		$other   = $this->restricted_post();

		$this->repository->save_post_list( $allowed, self::ALLOWED );
		$this->boot();

		$this->assertTrue(
			rcp_user_can_access( 0, $allowed ),
			'L\'autorisation vaut pour le contenu où elle est posée.'
		);
		$this->assertFalse(
			rcp_user_can_access( 0, $other ),
			'FNC-04 : elle ne vaut pour aucun autre contenu.'
		);
	}

	/**
	 * Une adresse hors liste n'obtient rien de plus (FNC-31).
	 *
	 * @return void
	 */
	public function test_an_address_outside_the_post_list_gains_nothing(): void {
		$post_id = $this->restricted_post();

		$this->repository->save_post_list( $post_id, '203.0.113.7' );
		$this->boot();

		$this->assertFalse( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * La portée rapportée est la plus large des portées satisfaites (FNC-06).
	 *
	 * @return void
	 */
	public function test_the_widest_satisfied_scope_explains_the_access(): void {
		$post_id = $this->restricted_post();
		$scopes  = array();

		add_action(
			Extension_Points::ACCESS_GRANTED,
			static function ( $address, $scope ) use ( &$scopes ): void {
				unset( $address );

				$scopes[] = $scope;
			},
			10,
			2
		);

		$this->repository->save_post_list( $post_id, self::ALLOWED );
		$this->boot();

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
		$this->assertSame( array( Scope::POST->value ), $scopes );

		$this->repository->save( Settings::from_array( array( 'allow_list' => self::ALLOWED ) ) );
		$this->boot();
		$scopes = array();

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
		$this->assertSame( array( Scope::GLOBAL_LIST->value ), $scopes );
	}

	/**
	 * Un contenu sans identifiant ne consulte aucune liste de contenu.
	 *
	 * @return void
	 */
	public function test_content_without_an_identifier_consults_no_post_list(): void {
		$this->repository->save_post_list( $this->restricted_post(), self::ALLOWED );
		$this->boot();

		$this->assertFalse( (bool) apply_filters( 'rcp_member_can_access', false, 0, 0 ) );
	}

	/**
	 * La liste d'un contenu se range en métadonnée de publication (§6.4).
	 *
	 * Le préfixe de soulignement la retire de l'interface des champs
	 * personnalisés : un réglage d'accès n'a pas à être modifiable par un champ
	 * libre, qu'aucune de nos deux gardes ne protégerait.
	 *
	 * @return void
	 */
	public function test_a_post_list_is_stored_as_protected_post_meta(): void {
		$post_id = $this->restricted_post();

		$this->repository->save_post_list( $post_id, self::ALLOWED );

		$this->assertSame(
			self::ALLOWED,
			get_post_meta( $post_id, Settings_Repository::POST_META_KEY, true )
		);
		$this->assertSame( self::ALLOWED, $this->repository->load_post_list( $post_id ) );
		$this->assertTrue(
			is_protected_meta( Settings_Repository::POST_META_KEY, 'post' ),
			'§6.4 : la clé est protégée, donc absente de l\'interface des champs personnalisés.'
		);
	}

	/**
	 * Une liste vidée ne laisse pas de métadonnée derrière elle.
	 *
	 * @return void
	 */
	public function test_an_emptied_post_list_leaves_no_meta(): void {
		$post_id = $this->restricted_post();

		$this->repository->save_post_list( $post_id, self::ALLOWED );
		$this->repository->save_post_list( $post_id, '' );

		$this->assertSame( '', (string) get_post_meta( $post_id, Settings_Repository::POST_META_KEY, true ) );
		$this->assertSame( '', $this->repository->load_post_list( $post_id ) );
	}

	/**
	 * La suppression d'un contenu emporte sa liste.
	 *
	 * C'est WordPress qui s'en charge pour les métadonnées de publication —
	 * contrairement aux métadonnées de niveau, où le constat JE-025 a imposé
	 * notre propre nettoyage.
	 *
	 * @return void
	 */
	public function test_deleting_a_post_takes_its_list_with_it(): void {
		$post_id = $this->restricted_post();

		$this->repository->save_post_list( $post_id, self::ALLOWED );

		wp_delete_post( $post_id, true );

		$this->assertSame( '', $this->repository->load_post_list( $post_id ) );
	}

	/**
	 * La boîte n'enregistre rien sans le nonce de la dépendance (SEC-02).
	 *
	 * @return void
	 */
	public function test_the_metabox_refuses_a_submission_without_a_nonce(): void {
		$post_id = $this->restricted_post();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array( Post_Metabox::FIELD => self::ALLOWED );

		( new Post_Metabox( $this->repository ) )->maybe_save( $post_id );

		$this->assertSame( '', $this->repository->load_post_list( $post_id ) );
	}

	/**
	 * La boîte n'enregistre rien sur un nonce invalide (SEC-02).
	 *
	 * @return void
	 */
	public function test_the_metabox_refuses_an_invalid_nonce(): void {
		$post_id = $this->restricted_post();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array(
			Post_Metabox::FIELD => self::ALLOWED,
			'rcp_meta_box'      => 'ce-n-est-pas-un-nonce',
		);

		( new Post_Metabox( $this->repository ) )->maybe_save( $post_id );

		$this->assertSame( '', $this->repository->load_post_list( $post_id ) );
	}

	/**
	 * La boîte n'enregistre rien sans la capacité (SEC-02).
	 *
	 * @return void
	 */
	public function test_the_metabox_refuses_a_submission_without_the_capability(): void {
		$post_id = $this->restricted_post();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$_POST = array(
			Post_Metabox::FIELD => self::ALLOWED,
			'rcp_meta_box'      => wp_create_nonce( 'metabox.php' ),
		);

		( new Post_Metabox( $this->repository ) )->maybe_save( $post_id );

		$this->assertSame( '', $this->repository->load_post_list( $post_id ) );
	}

	/**
	 * **Le contournement par identifiant est refusé** (SEC-02).
	 *
	 * L'auteur soumet un formulaire authentique — nonce valide, capacité
	 * d'édition bien réelle sur son propre article — en substituant
	 * l'identifiant d'un contenu qui n'est pas le sien. La capacité étant
	 * vérifiée **sur le contenu enregistré**, et non en général, l'écriture est
	 * refusée là et acceptée ici.
	 *
	 * @return void
	 */
	public function test_an_author_cannot_authorise_a_post_they_cannot_edit(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$own       = $this->restricted_post( $author_id );
		$other     = $this->restricted_post( self::factory()->user->create( array( 'role' => 'author' ) ) );

		wp_set_current_user( $author_id );

		$this->assertTrue( current_user_can( 'edit_post', $own ), 'Prémisse : l\'auteur peut modifier le sien.' );
		$this->assertFalse( current_user_can( 'edit_post', $other ), 'Prémisse : il ne peut pas modifier l\'autre.' );

		$_POST = array(
			Post_Metabox::FIELD => self::ALLOWED,
			'rcp_meta_box'      => wp_create_nonce( 'metabox.php' ),
		);

		$metabox = new Post_Metabox( $this->repository );

		$metabox->maybe_save( $other );

		$this->assertSame(
			'',
			$this->repository->load_post_list( $other ),
			'SEC-02 : un nonce valide ne vaut pas capacité sur le contenu visé.'
		);

		$metabox->maybe_save( $own );

		$this->assertSame(
			self::ALLOWED,
			$this->repository->load_post_list( $own ),
			'La même soumission sur son propre contenu est acceptée : c\'est bien l\'identifiant qui a été refusé.'
		);

		$this->boot();

		$this->assertFalse(
			rcp_user_can_access( 0, $other ),
			'Et le contenu visé reste fermé.'
		);
	}

	/**
	 * Avec le nonce et la capacité, la boîte enregistre et normalise.
	 *
	 * La normalisation est celle de FNC-28, par le même analyseur que les
	 * autres listes : A-02 l'exige aux deux moments.
	 *
	 * @return void
	 */
	public function test_the_metabox_stores_and_normalises(): void {
		$post_id = $this->restricted_post();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array(
			Post_Metabox::FIELD => "  198.51.100.9  \n\n198.51.100.9\nexemple.test\n",
			'rcp_meta_box'      => wp_create_nonce( 'metabox.php' ),
		);

		( new Post_Metabox( $this->repository ) )->maybe_save( $post_id );

		$this->assertSame(
			"198.51.100.9\nexemple.test",
			$this->repository->load_post_list( $post_id ),
			'Doublons fusionnés, entrée refusée conservée (FNC-22, FNC-28).'
		);
	}

	/**
	 * Une soumission authentique sans notre champ n'écrit rien.
	 *
	 * L'écran d'édition est enregistré par bien d'autres chemins que le nôtre —
	 * enregistrement automatique, modification rapide, autres extensions.
	 * L'absence du champ ne doit pas vider une liste existante.
	 *
	 * @return void
	 */
	public function test_an_authentic_submission_without_our_field_writes_nothing(): void {
		$post_id = $this->restricted_post();

		$this->repository->save_post_list( $post_id, self::ALLOWED );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array( 'rcp_meta_box' => wp_create_nonce( 'metabox.php' ) );

		( new Post_Metabox( $this->repository ) )->maybe_save( $post_id );

		$this->assertSame( self::ALLOWED, $this->repository->load_post_list( $post_id ) );
	}

	/**
	 * La garde d'identifiant ne fait rien sans identifiant.
	 *
	 * @return void
	 */
	public function test_the_identifier_guard_does_nothing_without_an_identifier(): void {
		$_POST = array(
			Post_Metabox::FIELD => self::ALLOWED,
			'rcp_meta_box'      => wp_create_nonce( 'metabox.php' ),
		);

		( new Post_Metabox( $this->repository ) )->maybe_save( 0 );

		$this->assertSame( '', (string) get_post_meta( 0, Settings_Repository::POST_META_KEY, true ) );
	}

	/**
	 * La boîte affiche la liste du contenu édité, échappée (FNC-26).
	 *
	 * @return void
	 */
	public function test_the_metabox_renders_its_value_escaped(): void {
		$post_id = $this->restricted_post();

		$this->repository->save_post_list( $post_id, '<script>alert(1)</script>' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$output = $this->render( $post_id );

		$this->assertStringContainsString( Post_Metabox::FIELD, $output );
		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
		$this->assertStringContainsString( 'ce seul contenu', $output, 'Le libellé énonce la non-transitivité.' );
	}

	/**
	 * La boîte ne s'affiche pas pour qui ne peut pas modifier le contenu.
	 *
	 * Afficher le champ en lecture seule laisserait croire qu'il est
	 * modifiable ; l'afficher modifiable mentirait sur ce que l'enregistrement
	 * fera. Ne rien afficher est la seule réponse exacte.
	 *
	 * @return void
	 */
	public function test_the_metabox_renders_nothing_without_the_capability(): void {
		$post_id = $this->restricted_post();

		$this->repository->save_post_list( $post_id, self::ALLOWED );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( '', $this->render( $post_id ) );
	}

	/**
	 * La boîte ne s'affiche pas hors d'un contenu.
	 *
	 * @return void
	 */
	public function test_the_metabox_renders_nothing_outside_a_post(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$GLOBALS['post'] = null;

		ob_start();
		( new Post_Metabox( $this->repository ) )->render();

		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * Une entrée refusée est signalée sur l'écran où elle a été saisie
	 * (FNC-22, A-03).
	 *
	 * Le signalement est ici et non dans l'avis général : énumérer les contenus
	 * porteurs d'une liste demanderait une requête non bornée, là où l'écran
	 * d'édition a déjà la liste sous les yeux.
	 *
	 * @return void
	 */
	public function test_a_rejected_post_entry_is_reported_on_the_editing_screen(): void {
		$post_id = $this->restricted_post();

		$this->repository->save_post_list( $post_id, "198.51.100.9\nexemple.test" );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$output = $this->render( $post_id );

		$this->assertStringContainsString( 'exemple.test', $output );
		$this->assertStringContainsString( 'Ligne 2', $output );
	}

	/**
	 * Une liste de contenu valide ne produit aucun signalement.
	 *
	 * @return void
	 */
	public function test_a_valid_post_list_produces_no_report(): void {
		$post_id = $this->restricted_post();

		$this->repository->save_post_list( $post_id, self::ALLOWED );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertStringNotContainsString( 'ignorée', $this->render( $post_id ) );
	}

	/**
	 * La liste du contenu passe par le point d'extension des règles.
	 *
	 * @return void
	 */
	public function test_the_post_list_passes_through_the_rules_extension_point(): void {
		$post_id = $this->restricted_post();

		add_filter(
			Extension_Points::RULES,
			static function ( $text, $scope, $scope_id ) use ( $post_id ) {
				if ( Scope::POST->value !== $scope || $post_id !== $scope_id ) {
					return $text;
				}

				return self::ALLOWED;
			},
			10,
			3
		);

		$this->boot();

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * Rend la boîte pour un contenu donné.
	 *
	 * @param int $post_id Contenu édité.
	 * @return string
	 */
	private function render( int $post_id ): string {
		$GLOBALS['post'] = get_post( $post_id );

		ob_start();
		( new Post_Metabox( $this->repository ) )->render();

		return (string) ob_get_clean();
	}
}
