<?php
/**
 * L'écran d'état des capacités, dans un WordPress réel.
 *
 * Trois propriétés comptent, et la première est celle qu'on oublie : l'écran
 * doit être là **quand la dépendance ne l'est pas**. C'est précisément la
 * situation où il sert — un administrateur qui vient de basculer la branche de
 * sa dépendance, ou qui n'a pas compris l'avis, a besoin de voir ce que le
 * plugin constate. Un écran rangé sous le menu de la dépendance disparaîtrait
 * avec elle.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Integration;

use Ip_Restriction_For_Restrict_Content\Admin\Capability_Status_Page;
use Ip_Restriction_For_Restrict_Content\Domain\Dependency_Diagnosis;
use Ip_Restriction_For_Restrict_Content\Integration\Capability_Probe;
use Ip_Restriction_For_Restrict_Content\Plugin;
use WP_UnitTestCase;

/**
 * Enregistrement et rendu de l'écran d'état.
 */
final class CapabilityStatusPageTest extends WP_UnitTestCase {

	/**
	 * Charge les fonctions d'administration nécessaires aux menus.
	 *
	 * @return void
	 */
	private function load_admin_menu_api(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	/**
	 * Vide les menus construits par un test précédent.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$GLOBALS['menu']    = array();
		$GLOBALS['submenu'] = array();
	}

	/**
	 * L'écran est enregistré sous l'outillage de WordPress.
	 *
	 * @return void
	 */
	public function test_the_screen_is_registered_under_tools(): void {
		$this->load_admin_menu_api();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		Plugin::from_environment()->register();

		do_action( 'admin_menu' );

		$slugs = array_column( $GLOBALS['submenu']['tools.php'] ?? array(), 2 );

		$this->assertContains(
			Capability_Status_Page::SLUG,
			$slugs,
			'L\'écran d\'état n\'est pas enregistré : il est le seul endroit où un '
			. 'administrateur peut voir ce que le plugin constate.'
		);
	}

	/**
	 * L'écran est enregistré même quand les prérequis manquent.
	 *
	 * @return void
	 */
	public function test_the_screen_is_registered_even_without_the_dependency(): void {
		$this->load_admin_menu_api();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$absent = Dependency_Diagnosis::from_observation( false, false );
		$report = Capability_Probe::observe( true )->report();

		( new Capability_Status_Page( $report, $absent ) )->register();

		do_action( 'admin_menu' );

		$slugs = array_column( $GLOBALS['submenu']['tools.php'] ?? array(), 2 );

		$this->assertContains( Capability_Status_Page::SLUG, $slugs );
	}

	/**
	 * L'écran exige `manage_options`.
	 *
	 * La capacité de la dépendance ne conviendrait pas : elle n'existe pas
	 * quand la dépendance est absente, et l'écran serait alors inaccessible à
	 * tout le monde.
	 *
	 * @return void
	 */
	public function test_the_screen_requires_manage_options(): void {
		$this->load_admin_menu_api();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		Plugin::from_environment()->register();

		do_action( 'admin_menu' );

		$capabilities = array();

		foreach ( $GLOBALS['submenu']['tools.php'] ?? array() as $entry ) {
			if ( Capability_Status_Page::SLUG === $entry[2] ) {
				$capabilities[] = $entry[1];
			}
		}

		/*
		 * Les doublons sont un artefact du processus de test : l'amorçage réel
		 * a déjà eu lieu au chargement de WordPress, et ce test réamorce. En
		 * production, l'amorçage a lieu une fois par requête.
		 */
		$this->assertSame(
			array( Capability_Status_Page::CAPABILITY ),
			array_values( array_unique( $capabilities ) )
		);
		$this->assertNotSame( array(), $capabilities );
	}

	/**
	 * L'écran ne rend rien à qui n'a pas la capacité.
	 *
	 * @return void
	 */
	public function test_nothing_is_rendered_without_the_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$page = new Capability_Status_Page(
			Capability_Probe::observe( true )->report(),
			Dependency_Diagnosis::from_observation( true, true )
		);

		ob_start();
		$page->render();

		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * L'écran nomme chaque capacité, son état et sa preuve amont.
	 *
	 * @return void
	 */
	public function test_the_screen_names_each_capability_and_its_evidence(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$report = Capability_Probe::observe( false )->report();
		$page   = new Capability_Status_Page( $report, Dependency_Diagnosis::from_observation( true, true ) );

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		foreach ( $report->capabilities() as $capability ) {
			$this->assertStringContainsString( $capability->symbol(), $output );
			$this->assertStringContainsString( $capability->evidence(), $output );
		}

		$this->assertStringContainsString( 'non observable sur cette requête', $output );
	}

	/**
	 * Sur un diagnostic de branche historique, l'écran nomme la cause et
	 * l'action de retour.
	 *
	 * @return void
	 */
	public function test_the_screen_names_the_legacy_branch_and_the_way_back(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$page = new Capability_Status_Page(
			Capability_Probe::observe( true )->report(),
			Dependency_Diagnosis::from_observation( false, true )
		);

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'base de code historique', $output );
		$this->assertStringContainsString( 'sans réactivation', $output );
	}

	/**
	 * L'écran cite l'option amont comme une indication, en le disant.
	 *
	 * La citer est autorisé ; en déduire quoi que ce soit ne l'est pas. Le
	 * libellé doit donc porter la réserve, faute de quoi le lecteur suivant
	 * croira que le plugin s'en sert.
	 *
	 * @return void
	 */
	public function test_the_screen_marks_the_upstream_option_as_an_indication(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		update_option( 'restrict_content_chosen_version', '3.0' );

		$page = new Capability_Status_Page(
			Capability_Probe::observe( true )->report(),
			Dependency_Diagnosis::from_observation( true, true )
		);

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'non utilisée pour décider', $output );
		$this->assertStringContainsString( 'restrict_content_chosen_version', $output );

		delete_option( 'restrict_content_chosen_version' );
	}

	/**
	 * L'écran n'écrit rien.
	 *
	 * Un écran de diagnostic qui écrirait une option contredirait P-01 par la
	 * porte de service.
	 *
	 * @return void
	 */
	public function test_rendering_the_screen_writes_nothing(): void {
		global $wpdb;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options}" );

		$page = new Capability_Status_Page(
			Capability_Probe::observe( true )->report(),
			Dependency_Diagnosis::from_observation( true, true )
		);

		ob_start();
		$page->render();
		ob_end_clean();

		$this->assertSame( $before, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options}" ) );
	}
}
