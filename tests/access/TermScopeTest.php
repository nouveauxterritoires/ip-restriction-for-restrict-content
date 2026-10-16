<?php
/**
 * Les restrictions de taxonomie — §5.1.1, V-06.
 *
 * Deux choses très différentes sont établies ici, et les confondre serait
 * l'erreur de ce lot.
 *
 * **Les contenus d'une catégorie restreinte** sont couverts par le branchement
 * du lot 5, sans rien de nouveau : leur décision passe bien par
 * `rcp_member_can_access`. Le premier test l'établit par l'exécution, et c'est
 * ce constat qui a réduit le lot (JE-027).
 *
 * **L'archive du terme lui-même** n'a aucun point de décision :
 * `rcp_user_can_access_term()` n'applique aucun filtre (établi par la suite
 * `contract` au lot 1). La seule voie est de neutraliser la restriction du
 * terme, ce qui modifie une donnée et non une décision — et les derniers tests
 * mesurent précisément jusqu'où cette altération porte. C'est la vérification
 * que V-06 demandait.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Access;

use Ip_Restriction_For_Restrict_Content\Integration\Cache_Guard;
use Ip_Restriction_For_Restrict_Content\Integration\Content_Access;
use Ip_Restriction_For_Restrict_Content\Integration\Term_Access;
use Ip_Restriction_For_Restrict_Content\Integration\Visitor_Address;
use Ip_Restriction_For_Restrict_Content\Policy\Scope;
use Ip_Restriction_For_Restrict_Content\Settings\Settings;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use Ip_Restriction_For_Restrict_Content\Support\Extension_Points;
use WP_UnitTestCase;

/**
 * Comportement observable sur les contenus et les archives de terme.
 */
final class TermScopeTest extends WP_UnitTestCase {

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
		remove_all_filters( 'rcp_get_term_restrictions' );

		$this->repository = new Settings_Repository();

		$_SERVER['REMOTE_ADDR'] = self::ALLOWED;

		wp_set_current_user( 0 );
	}

	/**
	 * Remet l'environnement en état.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'rcp_get_term_restrictions' );
		remove_all_filters( Extension_Points::RULES );
		remove_all_actions( Extension_Points::TERM_RESTRICTION_LIFTED );

		parent::tear_down();
	}

	/**
	 * Branche les deux accroches, ce qui simule la requête suivante.
	 *
	 * @return Cache_Guard
	 */
	private function boot(): Cache_Guard {
		remove_all_filters( 'rcp_member_can_access' );
		remove_all_filters( 'rcp_get_term_restrictions' );

		$repository = new Settings_Repository();
		$cache      = new Cache_Guard();
		$visitor    = new Visitor_Address( $repository );

		( new Content_Access( $repository, $cache, $visitor ) )->register();
		( new Term_Access( $repository, $cache, $visitor ) )->register();

		return $cache;
	}

	/**
	 * Autorise la couverture des archives de terme.
	 *
	 * @param string $allow_list Liste d'autorisation globale.
	 * @return void
	 */
	private function opt_in( string $allow_list = self::ALLOWED ): void {
		$this->repository->save(
			Settings::from_array(
				array(
					'allow_list'            => $allow_list,
					'covers_taxonomy_terms' => true,
				)
			)
		);
	}

	/**
	 * Crée une catégorie restreinte.
	 *
	 * @param array<int, int> $levels Niveaux exigés ; aucun pour « payant seulement ».
	 * @return int
	 */
	private function restricted_term( array $levels = array() ): int {
		$term_id = self::factory()->category->create();

		update_term_meta(
			$term_id,
			'rcp_restricted_meta',
			array(
				'paid_only'     => array() === $levels,
				'subscriptions' => $levels,
				'access_level'  => 0,
			)
		);

		return $term_id;
	}

	/**
	 * Crée un contenu rangé dans une catégorie, et restreint par elle seule.
	 *
	 * @param int $term_id Catégorie.
	 * @return int
	 */
	private function post_in_term( int $term_id ): int {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		wp_set_post_categories( $post_id, array( $term_id ) );

		return $post_id;
	}

	/**
	 * Un contenu restreint par sa catégorie est couvert par la portée globale,
	 * sans la couverture des archives.
	 *
	 * **C'est le constat qui a réduit ce lot** (JE-027) : la décision d'accès à
	 * un contenu restreint par terme passe par `rcp_member_can_access`, donc
	 * par le branchement du lot 5. Aucun filtre sur les restrictions n'est
	 * nécessaire, et le risque du §5.1.1 n'est pas pris.
	 *
	 * @return void
	 */
	public function test_a_post_restricted_by_its_term_is_covered_without_the_option(): void {
		$term_id = $this->restricted_term();
		$post_id = $this->post_in_term( $term_id );

		$this->assertTrue( rcp_has_term_restrictions( $post_id ), 'Prémisse : le contenu est restreint par son terme.' );
		$this->assertFalse( rcp_user_can_access( 0, $post_id ), 'Comportement de la dépendance seule.' );

		$this->repository->save( Settings::from_array( array( 'allow_list' => self::ALLOWED ) ) );
		$this->boot();

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
		$this->assertFalse(
			has_filter( 'rcp_get_term_restrictions' ),
			'Le filtre grossier du §5.1.1 n\'est pas branché : l\'option est désactivée par défaut.'
		);
	}

	/**
	 * Un contenu restreint par un terme exigeant un niveau est couvert par la
	 * liste de ce niveau (FNC-02).
	 *
	 * La dépendance ne consulte les restrictions de terme que si le contenu
	 * n'en porte aucune : nous suivons cette précédence, et la liste consultée
	 * reste celle d'un niveau qui restreint bien ce contenu.
	 *
	 * @return void
	 */
	public function test_a_term_level_list_covers_the_posts_of_that_term(): void {
		$level_id = (int) rcp_add_membership_level(
			array(
				'name'     => 'Or',
				'price'    => 10,
				'duration' => 1,
				'status'   => 'active',
			)
		);

		$term_id = $this->restricted_term( array( $level_id ) );
		$post_id = $this->post_in_term( $term_id );

		$this->assertFalse( rcp_user_can_access( 0, $post_id ), 'Comportement de la dépendance seule.' );

		$this->repository->save_level_list( $level_id, self::ALLOWED );
		$this->boot();

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * Une autorisation posée sur un autre niveau n'ouvre pas le contenu d'un
	 * terme (FNC-04).
	 *
	 * @return void
	 */
	public function test_another_level_list_does_not_cover_the_posts_of_a_term(): void {
		$gold   = (int) rcp_add_membership_level(
			array(
				'name'     => 'Or',
				'price'    => 10,
				'duration' => 1,
				'status'   => 'active',
			)
		);
		$silver = (int) rcp_add_membership_level(
			array(
				'name'     => 'Argent',
				'price'    => 5,
				'duration' => 1,
				'status'   => 'active',
			)
		);

		$post_id = $this->post_in_term( $this->restricted_term( array( $gold ) ) );

		$this->repository->save_level_list( $silver, self::ALLOWED );
		$this->boot();

		$this->assertFalse( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * L'archive d'un terme restreint s'ouvre à une adresse autorisée, une fois
	 * l'option demandée.
	 *
	 * @return void
	 */
	public function test_the_archive_of_a_restricted_term_opens_with_the_option(): void {
		$term_id = $this->restricted_term();

		$this->assertFalse( rcp_user_can_access_term( 0, $term_id ), 'Comportement de la dépendance seule.' );

		$this->opt_in();
		$this->boot();
		$this->go_to( (string) get_term_link( $term_id ) );

		$this->assertTrue( rcp_user_can_access_term( 0, $term_id ) );
	}

	/**
	 * Sans l'option, l'archive reste fermée.
	 *
	 * @return void
	 */
	public function test_without_the_option_the_archive_stays_closed(): void {
		$term_id = $this->restricted_term();

		$this->repository->save( Settings::from_array( array( 'allow_list' => self::ALLOWED ) ) );
		$this->boot();
		$this->go_to( (string) get_term_link( $term_id ) );

		$this->assertFalse( rcp_user_can_access_term( 0, $term_id ) );
	}

	/**
	 * Le filtre n'est pas branché si aucune liste d'autorisation n'existe.
	 *
	 * Deuxième garde-fou du §5.1.1 : une option cochée sans liste n'a rien à
	 * accorder, et un filtre qui ne peut rien accorder ne doit pas exister.
	 *
	 * @return void
	 */
	public function test_the_filter_is_not_hooked_without_any_allow_list(): void {
		$this->opt_in( '' );
		$this->boot();

		$this->assertFalse( has_filter( 'rcp_get_term_restrictions' ) );
	}

	/**
	 * Le filtre est branché si une liste de niveau existe, même sans liste
	 * globale.
	 *
	 * @return void
	 */
	public function test_a_level_list_alone_is_enough_to_hook_the_filter(): void {
		$level_id = (int) rcp_add_membership_level(
			array(
				'name'     => 'Or',
				'price'    => 10,
				'duration' => 1,
				'status'   => 'active',
			)
		);

		$this->opt_in( '' );
		$this->repository->save_level_list( $level_id, self::ALLOWED );
		$this->boot();

		$this->assertNotFalse( has_filter( 'rcp_get_term_restrictions' ) );
	}

	/**
	 * L'archive d'un terme exigeant un niveau s'ouvre à la liste de ce niveau,
	 * et pas à celle d'un autre (FNC-04).
	 *
	 * @return void
	 */
	public function test_the_archive_consults_only_the_levels_that_term_requires(): void {
		$gold   = (int) rcp_add_membership_level(
			array(
				'name'     => 'Or',
				'price'    => 10,
				'duration' => 1,
				'status'   => 'active',
			)
		);
		$silver = (int) rcp_add_membership_level(
			array(
				'name'     => 'Argent',
				'price'    => 5,
				'duration' => 1,
				'status'   => 'active',
			)
		);

		$term_id = $this->restricted_term( array( $gold ) );

		$this->opt_in( '' );
		$this->repository->save_level_list( $silver, self::ALLOWED );
		$this->boot();
		$this->go_to( (string) get_term_link( $term_id ) );

		$this->assertFalse(
			rcp_user_can_access_term( 0, $term_id ),
			'FNC-04 : la liste d\'« Argent » n\'ouvre pas une archive réservée à « Or ».'
		);

		$this->repository->save_level_list( $gold, self::ALLOWED );
		$this->boot();
		$this->go_to( (string) get_term_link( $term_id ) );

		$this->assertTrue( rcp_user_can_access_term( 0, $term_id ) );
	}

	/**
	 * Une adresse hors liste n'ouvre aucune archive.
	 *
	 * @return void
	 */
	public function test_an_address_outside_the_list_opens_no_archive(): void {
		$term_id = $this->restricted_term();

		$this->opt_in( '203.0.113.7' );
		$this->boot();
		$this->go_to( (string) get_term_link( $term_id ) );

		$this->assertFalse( rcp_user_can_access_term( 0, $term_id ) );
	}

	/**
	 * **V-06, quatrième garde-fou : les restrictions d'un autre terme sortent
	 * intactes.**
	 *
	 * C'est la moitié rassurante de la vérification que V-06 demandait : le
	 * filtre ne neutralise que le terme demandé par la requête, donc une
	 * seconde lecture portant sur un autre terme n'est pas altérée.
	 *
	 * @return void
	 */
	public function test_another_term_is_not_altered_during_the_same_request(): void {
		$queried = $this->restricted_term();
		$other   = $this->restricted_term();

		$this->opt_in();
		$this->boot();
		$this->go_to( (string) get_term_link( $queried ) );

		$this->assertSame( array(), rcp_get_term_restrictions( $queried ), 'Le terme demandé est neutralisé.' );
		$this->assertNotSame(
			array(),
			rcp_get_term_restrictions( $other ),
			'V-06 : un autre terme n\'est pas altéré par le filtre.'
		);
		$this->assertFalse(
			rcp_user_can_access_term( 0, $other ),
			'Et sa décision n\'est pas retournée.'
		);
	}

	/**
	 * **V-06, mesure du protocole exact du cahier des charges : la seconde
	 * lecture du même terme, pendant la même requête, est bien altérée.**
	 *
	 * Le cahier des charges prévoyait les deux issues : « si elle l'est, la
	 * couverture des taxonomies passe en fonctionnalité optionnelle,
	 * désactivée par défaut ». Elle l'est. Ce test fige le constat qui a rendu
	 * la fonctionnalité optionnelle, et il doit rester lisible comme tel : ce
	 * n'est pas un comportement souhaité, c'est le prix de la seule accroche
	 * disponible (§5.1.1).
	 *
	 * @return void
	 */
	public function test_the_queried_term_is_altered_for_every_reader_of_the_request(): void {
		$term_id = $this->restricted_term();

		$this->opt_in();
		$this->boot();
		$this->go_to( (string) get_term_link( $term_id ) );

		$this->assertTrue( rcp_user_can_access_term( 0, $term_id ), 'La décision est retournée.' );

		$this->assertSame(
			array(),
			rcp_get_term_restrictions( $term_id ),
			'V-06, constat : tout code qui relit les restrictions de ce terme pendant cette '
			. 'requête voit un terme non restreint. C\'est la raison pour laquelle la '
			. 'couverture des archives est optionnelle et désactivée par défaut.'
		);
	}

	/**
	 * **V-06, garde-fou d'administration : un écran d'administration lit les
	 * restrictions intactes.**
	 *
	 * C'est le pire effet indirect imaginable — l'écran d'édition du terme
	 * affiche ses restrictions, et un enregistrement depuis un écran qui les
	 * croit vides les effacerait. La requête d'administration n'interroge
	 * aucune archive, donc le filtre n'y neutralise rien.
	 *
	 * @return void
	 */
	public function test_an_admin_request_reads_the_restrictions_intact(): void {
		$term_id = $this->restricted_term();

		$this->opt_in();
		$this->boot();
		$this->go_to( (string) get_term_link( $term_id ) );

		set_current_screen( 'edit-category' );

		$this->assertNotSame(
			array(),
			rcp_get_term_restrictions( $term_id ),
			'V-06 : un écran d\'administration ne doit jamais voir un terme neutralisé.'
		);

		set_current_screen( 'front' );
	}

	/**
	 * Hors d'une archive de terme, rien n'est neutralisé.
	 *
	 * @return void
	 */
	public function test_nothing_is_lifted_outside_a_term_archive(): void {
		$term_id = $this->restricted_term();

		$this->opt_in();
		$this->boot();
		$this->go_to( get_permalink( $this->post_in_term( $term_id ) ) );

		$this->assertNotSame( array(), rcp_get_term_restrictions( $term_id ) );
	}

	/**
	 * Un terme sans restriction sort tel quel, et sans décision.
	 *
	 * Tel quel vaut ici `false` et non un tableau vide : c'est ce que la
	 * dépendance renvoie pour un terme sans métadonnée, constaté par ce test.
	 * Le filtre doit donc laisser passer ce qui n'est pas un tableau, sans quoi
	 * il changerait le type de la donnée pour tout le monde.
	 *
	 * @return void
	 */
	public function test_an_unrestricted_term_is_returned_as_is(): void {
		$term_id = self::factory()->category->create();

		$this->opt_in();
		$this->boot();
		$this->go_to( (string) get_term_link( $term_id ) );

		$this->assertFalse( rcp_get_term_restrictions( $term_id ) );
		$this->assertTrue( rcp_user_can_access_term( 0, $term_id ) );
	}

	/**
	 * Un terme sans restriction parmi les catégories d'un contenu est ignoré.
	 *
	 * Un contenu peut être rangé dans plusieurs catégories dont une seule est
	 * restreinte. Les autres ne renvoient aucune restriction — pas même un
	 * tableau —, et les lire comme telles planterait la résolution des niveaux.
	 *
	 * @return void
	 */
	public function test_an_unrestricted_category_among_several_is_ignored(): void {
		$level_id = (int) rcp_add_membership_level(
			array(
				'name'     => 'Or',
				'price'    => 10,
				'duration' => 1,
				'status'   => 'active',
			)
		);

		$restricted = $this->restricted_term( array( $level_id ) );
		$post_id    = $this->post_in_term( $restricted );

		wp_set_post_categories( $post_id, array( $restricted, self::factory()->category->create() ) );

		$this->repository->save_level_list( $level_id, self::ALLOWED );
		$this->boot();

		$this->assertTrue( rcp_user_can_access( 0, $post_id ) );
	}

	/**
	 * Une métadonnée de terme mal formée ne fait pas tomber la décision.
	 *
	 * Les restrictions d'un terme sont une métadonnée libre : une version
	 * antérieure de la dépendance, un import ou un autre plugin peuvent y
	 * avoir écrit autre chose qu'une liste de niveaux. La portée globale, elle,
	 * continue de répondre.
	 *
	 * @return void
	 */
	public function test_a_malformed_term_meta_does_not_break_the_decision(): void {
		$term_id = self::factory()->category->create();

		update_term_meta(
			$term_id,
			'rcp_restricted_meta',
			array(
				'paid_only'     => true,
				'subscriptions' => 'une chaîne, pas une liste',
				'access_level'  => 0,
			)
		);

		$this->opt_in();
		$this->boot();
		$this->go_to( (string) get_term_link( $term_id ) );

		$this->assertTrue( rcp_user_can_access_term( 0, $term_id ) );
	}

	/**
	 * La neutralisation marque la réponse comme non cachable (SEC-14).
	 *
	 * @return void
	 */
	public function test_lifting_a_restriction_marks_the_response(): void {
		$term_id = $this->restricted_term();

		$this->opt_in();
		$cache = $this->boot();
		$this->go_to( (string) get_term_link( $term_id ) );

		$this->assertFalse( $cache->has_marked(), 'Rien n\'est marqué avant la décision.' );

		rcp_user_can_access_term( 0, $term_id );

		$this->assertTrue( $cache->has_marked() );
	}

	/**
	 * La neutralisation est signalée par son propre point d'extension.
	 *
	 * Distinct de l'octroi d'accès à un contenu : la chose faite est distincte,
	 * et un exploitant qui journalise a le droit de savoir laquelle.
	 *
	 * @return void
	 */
	public function test_lifting_a_restriction_fires_its_own_extension_point(): void {
		$term_id = $this->restricted_term();
		$calls   = array();

		add_action(
			Extension_Points::TERM_RESTRICTION_LIFTED,
			static function ( $address, $lifted_term, $scope, $scope_id ) use ( &$calls ): void {
				$calls[] = array( $address, $lifted_term, $scope, $scope_id );
			},
			10,
			4
		);

		$this->opt_in();
		$this->boot();
		$this->go_to( (string) get_term_link( $term_id ) );

		rcp_user_can_access_term( 0, $term_id );

		$this->assertNotSame( array(), $calls );
		$this->assertSame( '198.51.100.x', $calls[0][0], 'L\'adresse est expurgée (SEC-06).' );
		$this->assertSame( $term_id, $calls[0][1] );
		$this->assertSame( Scope::GLOBAL_LIST->value, $calls[0][2] );
		$this->assertNull( $calls[0][3] );
	}

	/**
	 * La liste du terme passe par le point d'extension des règles.
	 *
	 * @return void
	 */
	public function test_the_global_list_passes_through_the_rules_extension_point(): void {
		$term_id = $this->restricted_term();

		$this->opt_in( '' );
		$this->repository->save(
			Settings::from_array(
				array(
					'allow_list'            => '203.0.113.7',
					'covers_taxonomy_terms' => true,
				)
			)
		);

		add_filter(
			Extension_Points::RULES,
			static function ( $text, $scope ) {
				return Scope::GLOBAL_LIST->value === $scope ? self::ALLOWED : $text;
			},
			10,
			2
		);

		$this->boot();
		$this->go_to( (string) get_term_link( $term_id ) );

		$this->assertTrue( rcp_user_can_access_term( 0, $term_id ) );
	}
}
