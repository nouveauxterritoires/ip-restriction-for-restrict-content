<?php
/**
 * Contrat : les accroches des niveaux d'adhésion et leurs métadonnées.
 *
 * Deux choses à établir, et elles n'ont pas la même nature.
 *
 * La première est le stockage : ADR-0004 range le réglage de portée par niveau
 * dans une **métadonnée de niveau**, par l'interface de la dépendance, et non
 * dans une option ni dans une table propre. Un aller-retour réel l'établit —
 * et établit du même coup que la table de métadonnées existe dans
 * l'environnement de test, ce que JE-002 a montré n'être jamais acquis.
 *
 * La seconde est l'accroche : les écrans de niveau et l'enregistrement d'un
 * niveau exposent quatre points, et c'est par eux que passera le champ de
 * FNC-25. Les deux écrans sont des fichiers que la dépendance ne charge qu'en
 * administration : les charger explicitement est le prix à payer pour établir
 * le contrat par l'exécution, et c'est préférable à une lecture de source.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Contract;

use Ip_Restriction_For_Restrict_Content\Tests\Support\Dependency_Capabilities;
use Ip_Restriction_For_Restrict_Content\Tests\Support\Hook_Witness;
use WP_UnitTestCase;

/**
 * Niveaux d'adhésion : métadonnées et accroches.
 */
final class MembershipLevelContractTest extends WP_UnitTestCase {

	/**
	 * Clé de métadonnée utilisée par les tests, préfixée comme le sera la
	 * vraie (ADR-0001, forme courte pour les métas).
	 *
	 * @var string
	 */
	private const META_KEY = 'rcipr_contract_probe';

	/**
	 * Crée un niveau d'adhésion et renvoie son identifiant.
	 *
	 * @return int
	 */
	private function create_level(): int {
		$level_id = rcp_add_membership_level(
			array(
				'name'     => 'Contrat — niveau',
				'price'    => 0,
				'duration' => 0,
				'status'   => 'active',
			)
		);

		$this->assertNotEmpty( $level_id, 'La dépendance n\'a pas créé le niveau attendu (voir JE-002).' );

		return (int) $level_id;
	}

	/**
	 * Charge les écrans d'administration de la dépendance.
	 *
	 * La dépendance ne les charge que si `is_admin()`
	 * (`core/includes/class-restrict-content.php:247`), ce qu'un processus de
	 * test n'est pas. La table de liste de WordPress est chargée d'abord : le
	 * fichier d'écran en dérive une classe, et son absence serait une erreur
	 * fatale sans rapport avec ce qui est testé.
	 *
	 * @return void
	 */
	private function load_admin_screens(): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once RCP_PLUGIN_DIR . 'core/includes/admin/class-list-table.php';
		require_once RCP_PLUGIN_DIR . 'core/includes/admin/subscriptions/subscription-levels.php';
	}

	/**
	 * Rend l'écran des niveaux et renvoie sa sortie.
	 *
	 * @param int|null $edit_level_id Niveau à éditer, ou null pour la liste.
	 * @return string
	 */
	private function render_levels_screen( ?int $edit_level_id = null ): string {
		$this->load_admin_screens();

		/*
		 * La table de liste de la dépendance interroge l'écran courant dès son
		 * constructeur (`core/includes/admin/class-list-table.php:75`). Un
		 * processus de test n'en a pas : sans écran, l'erreur est une erreur
		 * fatale sans rapport avec l'accroche observée.
		 */
		set_current_screen( 'rcp-members_page_rcp-member-levels' );

		if ( null === $edit_level_id ) {
			unset( $_GET['edit_subscription'] );
		} else {
			$_GET['edit_subscription'] = (string) $edit_level_id;
		}

		ob_start();
		rcp_member_levels_page();

		return (string) ob_get_clean();
	}

	/**
	 * Remet l'environnement de requête en état.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		unset( $_GET['edit_subscription'] );

		parent::tear_down();
	}

	/**
	 * Une métadonnée de niveau survit à un aller-retour.
	 *
	 * @return void
	 */
	public function test_a_level_meta_survives_a_round_trip(): void {
		$level_id = $this->create_level();

		$this->assertNotFalse(
			rcp_add_membership_level_meta( $level_id, self::META_KEY, '203.0.113.7', true ),
			'L\'interface de métadonnées de niveau n\'écrit plus : ADR-0004 perd son stockage.'
		);
		$this->assertSame(
			'203.0.113.7',
			rcp_get_membership_level_meta( $level_id, self::META_KEY, true )
		);

		rcp_update_membership_level_meta( $level_id, self::META_KEY, '198.51.100.9' );

		$this->assertSame(
			'198.51.100.9',
			rcp_get_membership_level_meta( $level_id, self::META_KEY, true ),
			'La mise à jour d\'une métadonnée de niveau n\'est plus relue.'
		);
	}

	/**
	 * Une métadonnée de niveau n'est pas une option.
	 *
	 * ADR-0003 interdit toute table dédiée, et ADR-0004 range ce réglage en
	 * métadonnée : il ne doit donc apparaître nulle part ailleurs.
	 *
	 * @return void
	 */
	public function test_a_level_meta_is_not_stored_as_an_option(): void {
		$level_id = $this->create_level();

		rcp_add_membership_level_meta( $level_id, self::META_KEY, '203.0.113.7', true );

		$this->assertFalse( get_option( self::META_KEY ) );
		$this->assertFalse( get_option( self::META_KEY . '_' . $level_id ) );
	}

	/**
	 * La création d'un niveau déclenche son accroche d'enregistrement.
	 *
	 * @return void
	 */
	public function test_creating_a_level_fires_its_save_hook(): void {
		$witness = Hook_Witness::watch( 'rcp_add_subscription', 2 );

		$level_id = $this->create_level();

		$this->assertTrue(
			$witness->fired(),
			'L\'amont a changé : rcp_add_membership_level() ne déclenche plus '
			. 'rcp_add_subscription (constaté en '
			. 'core/includes/membership-levels/membership-level-functions.php:278). '
			. 'FNC-25 perd son point d\'enregistrement à la création.'
		);

		$arguments = $witness->first_call();

		$this->assertSame( $level_id, (int) $arguments[0], 'Le premier argument est l\'identifiant du niveau.' );
		$this->assertIsArray( $arguments[1], 'Le second argument transporte les données soumises.' );
	}

	/**
	 * La modification d'un niveau déclenche son accroche d'enregistrement.
	 *
	 * @return void
	 */
	public function test_updating_a_level_fires_its_save_hook(): void {
		$level_id = $this->create_level();
		$witness  = Hook_Witness::watch( 'rcp_edit_subscription_level', 2 );

		rcp_update_membership_level( $level_id, array( 'name' => 'Contrat — niveau modifié' ) );

		$this->assertTrue(
			$witness->fired(),
			'L\'amont a changé : rcp_update_membership_level() ne déclenche plus '
			. 'rcp_edit_subscription_level (constaté en '
			. 'core/includes/membership-levels/membership-level-functions.php:381).'
		);
		$this->assertSame( $level_id, (int) $witness->first_call()[0] );
	}

	/**
	 * L'écran de création d'un niveau expose son accroche de champ.
	 *
	 * L'accroche est à l'intérieur d'une garde de capacité
	 * (`core/includes/admin/subscriptions/subscription-levels.php:43`) : sans
	 * les capacités de la dépendance, l'administrateur du test ne voit pas le
	 * formulaire, et l'échec accuse l'amont au lieu de l'environnement
	 * (JE-017).
	 *
	 * @return void
	 */
	public function test_the_add_level_screen_exposes_its_field_hook(): void {
		Dependency_Capabilities::grant();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$witness = Hook_Witness::watch( 'rcp_add_subscription_form', 1 );

		$this->render_levels_screen();

		$this->assertTrue(
			$witness->fired(),
			'L\'amont a changé : l\'écran de création d\'un niveau ne déclenche plus '
			. 'rcp_add_subscription_form (constaté en '
			. 'core/includes/admin/subscriptions/subscription-levels.php:195). '
			. 'Le champ de FNC-25 n\'a plus d\'emplacement à la création.'
		);
	}

	/**
	 * L'écran d'édition d'un niveau expose son accroche de champ, et lui
	 * transporte le niveau édité.
	 *
	 * @return void
	 */
	public function test_the_edit_level_screen_exposes_its_field_hook(): void {
		Dependency_Capabilities::grant();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$level_id = $this->create_level();
		$witness  = Hook_Witness::watch( 'rcp_edit_subscription_form', 1 );

		$this->render_levels_screen( $level_id );

		$this->assertTrue(
			$witness->fired(),
			'L\'amont a changé : l\'écran d\'édition d\'un niveau ne déclenche plus '
			. 'rcp_edit_subscription_form (constaté en '
			. 'core/includes/admin/subscriptions/edit-subscription.php:164).'
		);

		$level = $witness->first_call()[0] ?? null;

		$this->assertIsObject( $level, 'L\'accroche doit transporter le niveau édité.' );
		$this->assertSame( $level_id, (int) $level->get_id() );
	}
}
