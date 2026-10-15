<?php
/**
 * La désinstallation — §6.6.
 *
 * L'asymétrie est le sujet du test : les réglages ne sont supprimés que si
 * l'administrateur l'a demandé. Supprimer par défaut ferait perdre une
 * configuration de plusieurs dizaines de lignes à qui désinstalle pour
 * déboguer ou le temps d'une migration, et une liste d'adresses ne se retrouve
 * pas de mémoire.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Integration;

use Ip_Restriction_For_Restrict_Content\Settings\Settings;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use WP_UnitTestCase;

/**
 * Comportement du point d'entrée de désinstallation.
 */
final class UninstallTest extends WP_UnitTestCase {

	/**
	 * Exécute le fichier de désinstallation.
	 *
	 * Il est inclus et non `require_once` : plusieurs tests doivent pouvoir le
	 * rejouer dans le même processus, ce qui est aussi la raison pour laquelle
	 * il ne déclare qu'une fonction, nommée et préfixée.
	 *
	 * @return void
	 */
	private function run_uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'ip-restriction-for-restrict-content/ip-restriction-for-restrict-content.php' );
		}

		require_once dirname( __DIR__, 2 ) . '/uninstall.php';

		ip_restriction_for_restrict_content_uninstall_site();
	}

	/**
	 * Écrit des réglages, avec ou sans demande de suppression.
	 *
	 * @param bool $deletes L'administrateur demande la suppression.
	 * @return void
	 */
	private function store_settings( bool $deletes ): void {
		( new Settings_Repository() )->save(
			Settings::from_array(
				array(
					'allow_list'           => "192.0.2.1\n",
					'deletes_on_uninstall' => $deletes,
				)
			)
		);
	}

	/**
	 * Sans demande explicite, les réglages survivent à la désinstallation.
	 *
	 * @return void
	 */
	public function test_settings_survive_by_default(): void {
		$this->store_settings( false );

		$this->run_uninstall();

		$this->assertNotFalse( get_option( Settings_Repository::OPTION ) );
		$this->assertSame( "192.0.2.1\n", ( new Settings_Repository() )->load()->allow_list() );
	}

	/**
	 * Sur demande explicite, les réglages et la version sont supprimés.
	 *
	 * @return void
	 */
	public function test_settings_are_deleted_on_request(): void {
		$this->store_settings( true );

		$this->assertNotFalse( get_option( Settings_Repository::OPTION ) );

		$this->run_uninstall();

		$this->assertFalse( get_option( Settings_Repository::OPTION ) );
		$this->assertFalse( get_option( Settings_Repository::VERSION_OPTION ) );
	}

	/**
	 * La désinstallation ne touche aucun compte utilisateur.
	 *
	 * Le test est là parce que l'exigence est absolue : jamais, dans aucun
	 * scénario. Une suppression d'utilisateurs serait irréversible, et ce
	 * plugin n'en crée aucun.
	 *
	 * @return void
	 */
	public function test_uninstalling_never_touches_users(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->store_settings( true );
		$this->run_uninstall();

		$this->assertInstanceOf( 'WP_User', get_user_by( 'id', $user_id ) );
	}

	/**
	 * La désinstallation ne touche aucune option de la dépendance.
	 *
	 * @return void
	 */
	public function test_uninstalling_leaves_the_dependency_alone(): void {
		update_option( 'restrict_content_chosen_version', '3.0' );

		$this->store_settings( true );
		$this->run_uninstall();

		$this->assertSame( '3.0', get_option( 'restrict_content_chosen_version' ) );

		delete_option( 'restrict_content_chosen_version' );
	}
}
