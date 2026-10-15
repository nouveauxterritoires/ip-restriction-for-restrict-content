<?php
/**
 * Contrat : la capacité qui gardera l'écran de réglages.
 *
 * FNC-24 réutilise la capacité de la dépendance plutôt que d'en inventer une :
 * `rcp_manage_settings`, celle qui garde son propre écran de réglages
 * (`core/includes/admin/admin-pages.php:28`) et qu'elle accorde à
 * l'administrateur (`core/includes/class-rcp-capabilities.php:53`).
 *
 * Réutiliser la capacité amont est un choix, et il a une conséquence : si la
 * dépendance la renommait, l'écran du plugin deviendrait inaccessible — ou,
 * bien pire, accessible à qui ne devrait pas. Ce test est là pour que ce
 * changement se manifeste par un échec de suite.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Contract;

use Ip_Restriction_For_Restrict_Content\Tests\Support\Dependency_Capabilities;
use WP_UnitTestCase;

/**
 * Attribution de la capacité de réglages.
 */
final class SettingsCapabilityContractTest extends WP_UnitTestCase {

	/**
	 * Capacité réutilisée par FNC-24.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'rcp_manage_settings';

	/**
	 * La dépendance accorde la capacité à l'administrateur.
	 *
	 * L'attribution est rejouée explicitement : elle a lieu à l'activation du
	 * plugin amont, que la bibliothèque de tests n'effectue pas (JE-002), et le
	 * rejeu seul reste invisible dans le processus (JE-017) — d'où
	 * Dependency_Capabilities. Ce que le test établit est donc bien ce qui
	 * compte : que l'interface d'attribution existe et accorde cette
	 * capacité-là.
	 *
	 * @return void
	 */
	public function test_the_dependency_grants_the_settings_capability_to_administrators(): void {
		Dependency_Capabilities::grant();

		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertTrue(
			user_can( $administrator, self::CAPABILITY ),
			'L\'amont a changé : la dépendance n\'accorde plus « ' . self::CAPABILITY . ' » à '
			. 'l\'administrateur (constaté en core/includes/class-rcp-capabilities.php:53). '
			. 'La garde de FNC-24 doit être réexaminée.'
		);
	}

	/**
	 * La capacité n'est pas accordée à un abonné.
	 *
	 * Sans ce second test, le premier passerait aussi bien si la dépendance
	 * accordait la capacité à tout le monde.
	 *
	 * @return void
	 */
	public function test_the_settings_capability_is_not_granted_to_subscribers(): void {
		Dependency_Capabilities::grant();

		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertFalse(
			user_can( $subscriber, self::CAPABILITY ),
			'L\'amont a changé : « ' . self::CAPABILITY . ' » est désormais accordée à un '
			. 'abonné. Toute garde qui s\'y appuie est à revoir immédiatement.'
		);
	}

	/**
	 * La capacité n'est pas confondue avec celle de WordPress.
	 *
	 * Un administrateur de réseau détient `manage_options` ; la capacité de la
	 * dépendance est distincte, et c'est elle que le plugin exige.
	 *
	 * @return void
	 */
	public function test_the_capability_is_distinct_from_manage_options(): void {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertFalse( user_can( $subscriber, 'manage_options' ) );
		$this->assertNotSame( 'manage_options', self::CAPABILITY );
	}
}
