<?php
/**
 * La portée par niveau d'adhésion — FNC-02, FNC-04, FNC-06, FNC-25.
 *
 * **Le test central de ce lot est celui de la portée croisée.** FNC-04 énonce
 * que les portées ne sont pas transitives : une autorisation posée sur le
 * niveau « Argent » ne donne aucun accès à un contenu réservé au niveau « Or ».
 * Ce n'est pas une subtilité d'implémentation — l'implémentation naïve, une
 * seule liste consultée quelle que soit la restriction rencontrée, viole
 * l'exigence silencieusement et transforme chaque autorisation de niveau en
 * autorisation globale.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Access;

use Ip_Restriction_For_Restrict_Content\Admin\Level_Field;
use Ip_Restriction_For_Restrict_Content\Admin\Rejection_Notice;
use Ip_Restriction_For_Restrict_Content\Integration\Cache_Guard;
use Ip_Restriction_For_Restrict_Content\Integration\Content_Access;
use Ip_Restriction_For_Restrict_Content\Policy\Scope;
use Ip_Restriction_For_Restrict_Content\Settings\Settings;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use Ip_Restriction_For_Restrict_Content\Support\Extension_Points;
use Ip_Restriction_For_Restrict_Content\Tests\Support\Dependency_Capabilities;
use WP_UnitTestCase;

/**
 * Comportement observable de la portée par niveau.
 */
final class LevelScopeTest extends WP_UnitTestCase {

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
		$_POST = array();

		remove_all_filters( Extension_Points::RULES );
		remove_all_actions( Extension_Points::ACCESS_GRANTED );

		parent::tear_down();
	}

	/**
	 * Branche la décision, ce qui simule la requête suivante.
	 *
	 * @return Cache_Guard
	 */
	private function boot(): Cache_Guard {
		remove_all_filters( 'rcp_member_can_access' );

		$cache = new Cache_Guard();

		( new Content_Access( new Settings_Repository(), $cache ) )->register();

		return $cache;
	}

	/**
	 * Crée un niveau d'adhésion.
	 *
	 * @param string $name Nom du niveau.
	 * @return int
	 */
	private function level( string $name ): int {
		$level_id = rcp_add_membership_level(
			array(
				'name'     => $name,
				'price'    => 10,
				'duration' => 1,
				'status'   => 'active',
			)
		);

		$this->assertNotEmpty( $level_id, 'La dépendance n\'a pas créé le niveau ' . $name . '.' );

		return (int) $level_id;
	}

	/**
	 * Crée un contenu restreint à un niveau.
	 *
	 * @param int $level_id Niveau exigé.
	 * @return int
	 */
	private function post_for_level( int $level_id ): int {
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, 'rcp_subscription_level', array( $level_id ) );

		return $post_id;
	}

	/**
	 * Une adresse de la liste d'un niveau accède aux contenus de ce niveau
	 * (FNC-02).
	 *
	 * @return void
	 */
	public function test_an_address_on_a_level_list_reads_that_level_content(): void {
		$gold    = $this->level( 'Or' );
		$post_id = $this->post_for_level( $gold );

		$this->repository->save_level_list( $gold, self::ALLOWED );
		$this->boot();

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * **Portée croisée : l'autorisation d'un niveau n'ouvre pas celui d'un
	 * autre** (FNC-04).
	 *
	 * @return void
	 */
	public function test_a_level_authorisation_does_not_open_another_level(): void {
		$silver = $this->level( 'Argent' );
		$gold   = $this->level( 'Or' );

		$this->repository->save_level_list( $silver, self::ALLOWED );

		$silver_post = $this->post_for_level( $silver );
		$gold_post   = $this->post_for_level( $gold );

		$this->boot();

		$this->assertTrue(
			rcp_user_can_access( 0, $silver_post ),
			'L\'autorisation vaut pour les contenus de son niveau.'
		);
		$this->assertFalse(
			rcp_user_can_access( 0, $gold_post ),
			'FNC-04 : elle ne vaut pour aucun autre niveau.'
		);
	}

	/**
	 * Une autorisation de niveau n'ouvre pas un contenu restreint autrement.
	 *
	 * Un contenu restreint par niveau d'accès ou par rôle, sans niveau
	 * d'adhésion, ne consulte aucune liste de niveau : il n'y en a aucune
	 * d'applicable.
	 *
	 * @return void
	 */
	public function test_a_level_authorisation_does_not_open_otherwise_restricted_content(): void {
		$gold = $this->level( 'Or' );

		$this->repository->save_level_list( $gold, self::ALLOWED );

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'rcp_access_level', 5 );

		$this->boot();

		$this->assertFalse( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * La liste globale ouvre les contenus de tous les niveaux (FNC-01).
	 *
	 * C'est la contrepartie de FNC-04 : la portée globale, elle, est bien
	 * transitive — c'est sa définition.
	 *
	 * @return void
	 */
	public function test_the_global_list_opens_every_level(): void {
		$silver = $this->level( 'Argent' );
		$gold   = $this->level( 'Or' );

		$this->repository->save( Settings::from_array( array( 'allow_list' => self::ALLOWED ) ) );

		$this->boot();

		$this->assertTrue( rcp_user_can_access( 0, $this->post_for_level( $silver ) ) );
		$this->assertTrue( rcp_user_can_access( 0, $this->post_for_level( $gold ) ) );
	}

	/**
	 * Les portées se cumulent par disjonction (FNC-06).
	 *
	 * Une seule portée satisfaite suffit, et deux portées satisfaites n'ont
	 * pas d'effet supplémentaire. C'est le premier lot où l'exigence est
	 * vérifiable : elle demande deux portées.
	 *
	 * @return void
	 */
	public function test_one_satisfied_scope_is_enough_and_two_add_nothing(): void {
		$gold    = $this->level( 'Or' );
		$post_id = $this->post_for_level( $gold );
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

		$this->repository->save_level_list( $gold, self::ALLOWED );
		$this->boot();

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
		$this->assertSame( array( Scope::MEMBERSHIP_LEVEL->value ), $scopes );

		$this->repository->save( Settings::from_array( array( 'allow_list' => self::ALLOWED ) ) );
		$this->boot();
		$scopes = array();

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
		$this->assertSame(
			array( Scope::GLOBAL_LIST->value ),
			$scopes,
			'La portée rapportée est la plus large : c\'est elle qui explique l\'accès.'
		);
	}

	/**
	 * Une adresse autorisée sur le niveau l'emporte sur une adhésion expirée
	 * (V-01, second volet).
	 *
	 * Le premier volet — autorisation globale — a été établi au lot 5. Celui-ci
	 * est le second que la vérification demandait : « refaire avec une
	 * autorisation posée sur le niveau Or ».
	 *
	 * @return void
	 */
	public function test_a_level_authorisation_outlives_an_expired_membership(): void {
		$gold    = $this->level( 'Or' );
		$post_id = $this->post_for_level( $gold );

		$user_id     = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$customer_id = rcp_add_customer( array( 'user_id' => $user_id ) );

		rcp_add_membership(
			array(
				'customer_id'     => (int) $customer_id,
				'object_id'       => $gold,
				'status'          => 'expired',
				'expiration_date' => '2020-01-01 00:00:00',
			)
		);

		wp_set_current_user( $user_id );
		$this->boot();

		$this->assertFalse( rcp_user_can_access( $user_id, $post_id ), 'Comportement de la dépendance seule.' );

		$this->repository->save_level_list( $gold, self::ALLOWED );
		$this->boot();

		$this->assertTrue( rcp_user_can_access( $user_id, $post_id ), 'V-01, second volet.' );
	}

	/**
	 * La liste d'un niveau se range en métadonnée de niveau (§6.3, ADR-0004).
	 *
	 * @return void
	 */
	public function test_a_level_list_is_stored_as_level_meta(): void {
		$gold = $this->level( 'Or' );

		$this->repository->save_level_list( $gold, self::ALLOWED );

		$this->assertSame(
			self::ALLOWED,
			rcp_get_membership_level_meta( $gold, Settings_Repository::LEVEL_META_KEY, true )
		);
		$this->assertSame( self::ALLOWED, $this->repository->load_level_list( $gold ) );
	}

	/**
	 * Une liste vidée ne laisse pas de métadonnée derrière elle.
	 *
	 * Un réglage absent et un réglage vide doivent se lire pareil, et
	 * l'absence est la forme qui ne laisse rien.
	 *
	 * @return void
	 */
	public function test_an_emptied_level_list_leaves_no_meta(): void {
		$gold = $this->level( 'Or' );

		$this->repository->save_level_list( $gold, self::ALLOWED );
		$this->repository->save_level_list( $gold, '' );

		$this->assertSame( '', (string) rcp_get_membership_level_meta( $gold, Settings_Repository::LEVEL_META_KEY, true ) );
		$this->assertSame( '', $this->repository->load_level_list( $gold ) );
	}

	/**
	 * La suppression d'un niveau emporte sa liste (ADR-0004).
	 *
	 * C'est la raison d'être du choix de stockage : aucun code de nettoyage, et
	 * aucun réglage orphelin qui accorderait un accès au prochain niveau à
	 * porter le même identifiant.
	 *
	 * @return void
	 */
	public function test_deleting_a_level_takes_its_list_with_it(): void {
		( new Level_Field( $this->repository ) )->register();

		$gold = $this->level( 'Or' );

		$this->repository->save_level_list( $gold, self::ALLOWED );

		rcp_delete_membership_level( $gold );

		$this->assertSame( '', $this->repository->load_level_list( $gold ) );
	}

	/**
	 * Sans notre nettoyage, la métadonnée survivrait au niveau.
	 *
	 * Le test fige le constat JE-025, qui contredit ADR-0004 : la suppression
	 * d'un niveau **ne** supprime **pas** sa métadonnée sur cette version de la
	 * dépendance. Le jour où l'amont le fera, ce test échouera — et ce sera une
	 * bonne nouvelle, qui permettra de retirer notre nettoyage.
	 *
	 * @return void
	 */
	public function test_the_dependency_alone_leaves_the_meta_behind(): void {
		$gold = $this->level( 'Or' );

		$this->repository->save_level_list( $gold, self::ALLOWED );

		remove_all_actions( 'rcp_remove_level' );
		rcp_delete_membership_level( $gold );

		$this->assertSame(
			self::ALLOWED,
			$this->repository->load_level_list( $gold ),
			'Si cette assertion échoue, la dépendance nettoie désormais ses métadonnées : '
			. 'relire JE-025 et ADR-0004, et retirer notre nettoyage devenu inutile.'
		);
	}

	/**
	 * Le champ de niveau n'enregistre rien sans le nonce de la dépendance.
	 *
	 * @return void
	 */
	public function test_the_level_field_refuses_a_submission_without_a_nonce(): void {
		Dependency_Capabilities::grant();

		$gold = $this->level( 'Or' );
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $user );

		$_POST = array( Level_Field::FIELD => self::ALLOWED );

		( new Level_Field( $this->repository ) )->maybe_save( $gold );

		$this->assertSame( '', $this->repository->load_level_list( $gold ) );
	}

	/**
	 * Le champ de niveau n'enregistre rien sans la capacité.
	 *
	 * @return void
	 */
	public function test_the_level_field_refuses_a_submission_without_the_capability(): void {
		$gold = $this->level( 'Or' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$_POST = array(
			Level_Field::FIELD     => self::ALLOWED,
			'rcp_edit_level_nonce' => wp_create_nonce( 'rcp_edit_level_nonce' ),
		);

		( new Level_Field( $this->repository ) )->maybe_save( $gold );

		$this->assertSame( '', $this->repository->load_level_list( $gold ) );
	}

	/**
	 * Avec le nonce et la capacité, le champ enregistre et normalise.
	 *
	 * La normalisation est celle de FNC-28, par le même analyseur que les
	 * listes globales : A-02 l'exige aux deux moments.
	 *
	 * @return void
	 */
	public function test_the_level_field_stores_and_normalises(): void {
		Dependency_Capabilities::grant();

		$gold = $this->level( 'Or' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array(
			Level_Field::FIELD     => "  198.51.100.9  \n\n198.51.100.9\nexemple.test\n",
			'rcp_edit_level_nonce' => wp_create_nonce( 'rcp_edit_level_nonce' ),
		);

		( new Level_Field( $this->repository ) )->maybe_save( $gold );

		$this->assertSame(
			"198.51.100.9\nexemple.test",
			$this->repository->load_level_list( $gold ),
			'Doublons fusionnés, entrée refusée conservée (FNC-22, FNC-28).'
		);
	}

	/**
	 * Le champ affiche la liste du niveau édité, échappée.
	 *
	 * @return void
	 */
	public function test_the_level_field_renders_its_value_escaped(): void {
		$gold = $this->level( 'Or' );

		$this->repository->save_level_list( $gold, '<script>alert(1)</script>' );

		ob_start();
		( new Level_Field( $this->repository ) )->render_for_level( rcp_get_membership_level( $gold ) );
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
		$this->assertStringContainsString( 'ce niveau', $output, 'Le libellé énonce la non-transitivité.' );
	}

	/**
	 * Le champ est rendu vide sur l'écran de création d'un niveau.
	 *
	 * Un niveau qui n'existe pas encore n'a pas de liste : le champ doit
	 * s'afficher quand même, sans quoi l'administrateur devrait créer le
	 * niveau puis le rouvrir pour le configurer.
	 *
	 * @return void
	 */
	public function test_the_field_renders_empty_on_the_creation_screen(): void {
		ob_start();
		( new Level_Field( $this->repository ) )->render_empty();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( Level_Field::FIELD, $output );
		$this->assertStringContainsString( '></textarea>', $output, 'Le champ est vide.' );
	}

	/**
	 * Le champ ne rend aucune liste pour un niveau sans identifiant.
	 *
	 * La dépendance transmet le niveau édité à son accroche de rendu ; si elle
	 * ne transmet rien — ou autre chose —, le champ s'affiche vide plutôt que
	 * de deviner.
	 *
	 * @return void
	 */
	public function test_the_field_renders_empty_without_a_level(): void {
		ob_start();
		( new Level_Field( $this->repository ) )->render_for_level( null );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '></textarea>', $output );
	}

	/**
	 * Les deux gardes d'identifiant ne font rien sans identifiant.
	 *
	 * Les actions de la dépendance sont aussi déclenchées par des appels
	 * programmatiques, et rien ne garantit qu'un identifiant utile y figure.
	 *
	 * @return void
	 */
	public function test_the_identifier_guards_do_nothing_without_an_identifier(): void {
		$field = new Level_Field( $this->repository );

		$field->maybe_save( 0 );
		$field->forget_level( 0 );

		$this->assertSame( array(), $this->repository->level_lists() );
	}

	/**
	 * Un contenu sans identifiant ne consulte aucune liste de niveau.
	 *
	 * Le filtre de la dépendance est appelé sans identifiant de contenu sur
	 * certains chemins : il n'y a alors aucun niveau applicable, donc aucune
	 * liste de niveau à consulter.
	 *
	 * @return void
	 */
	public function test_content_without_an_identifier_consults_no_level_list(): void {
		$gold = $this->level( 'Or' );

		$this->repository->save_level_list( $gold, self::ALLOWED );
		$this->boot();

		$this->assertFalse(
			(bool) apply_filters( 'rcp_member_can_access', false, 0, 0 ),
			'Sans contenu, aucune portée de niveau ne peut s\'appliquer.'
		);
	}

	/**
	 * Une entrée refusée dans la liste d'un niveau est signalée, avec le nom
	 * du niveau (FNC-22, A-03).
	 *
	 * Un avis qui ne couvrirait que les listes globales laisserait une ligne
	 * inerte sans aucun signe sur un écran de niveau.
	 *
	 * @return void
	 */
	public function test_a_rejected_level_entry_is_reported_with_its_level_name(): void {
		Dependency_Capabilities::grant();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$gold = $this->level( 'Or' );

		$this->repository->save_level_list( $gold, "198.51.100.9\nexemple.test" );

		ob_start();
		( new Rejection_Notice( new Settings_Repository() ) )->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Niveau', $output );
		$this->assertStringContainsString( 'Or', $output );
		$this->assertStringContainsString( 'exemple.test', $output );
		$this->assertStringContainsString( 'ligne 2', $output );
	}

	/**
	 * Une liste de niveau valide ne produit aucun avis.
	 *
	 * @return void
	 */
	public function test_a_valid_level_list_produces_no_notice(): void {
		Dependency_Capabilities::grant();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->repository->save_level_list( $this->level( 'Or' ), self::ALLOWED );

		ob_start();
		( new Rejection_Notice( new Settings_Repository() ) )->render();

		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * Les listes de niveau sont énumérables, et seules celles qui portent
	 * quelque chose y figurent.
	 *
	 * @return void
	 */
	public function test_only_levels_carrying_a_list_are_enumerated(): void {
		$silver = $this->level( 'Argent' );
		$gold   = $this->level( 'Or' );

		$this->repository->save_level_list( $gold, self::ALLOWED );

		$this->assertSame( array( $gold => self::ALLOWED ), $this->repository->level_lists() );
		$this->assertArrayNotHasKey( $silver, $this->repository->level_lists() );
	}

	/**
	 * Une soumission authentique sans notre champ n'écrit rien.
	 *
	 * Les écrans de niveau de la dépendance sont aussi enregistrés par d'autres
	 * chemins — et d'autres extensions peuvent y ajouter leurs propres champs.
	 * L'absence du nôtre ne doit pas vider une liste existante.
	 *
	 * @return void
	 */
	public function test_an_authentic_submission_without_our_field_writes_nothing(): void {
		Dependency_Capabilities::grant();

		$gold = $this->level( 'Or' );

		$this->repository->save_level_list( $gold, self::ALLOWED );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array( 'rcp_edit_level_nonce' => wp_create_nonce( 'rcp_edit_level_nonce' ) );

		( new Level_Field( $this->repository ) )->maybe_save( $gold );

		$this->assertSame(
			self::ALLOWED,
			$this->repository->load_level_list( $gold ),
			'Une liste existante ne doit pas être vidée par une soumission qui ne la porte pas.'
		);
	}
}
