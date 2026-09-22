<?php
/**
 * Contrat : la branche de code chargée par la dépendance.
 *
 * Restrict Content embarque deux bases de code mutuellement exclusives et n'en
 * charge qu'une, choisie une seule fois à la première activation puis figée
 * dans une option. La branche historique n'expose aucune des fonctions sur
 * lesquelles ce plugin repose (JE-003).
 *
 * Sans ce contrat, un environnement mal orienté ne se signalerait que bien plus
 * loin, par une erreur sur une fonction absente, au milieu d'une suite métier —
 * c'est-à-dire à l'endroit le plus coûteux à diagnostiquer. Ici, l'échec nomme
 * sa cause.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Contract;

use WP_UnitTestCase;

/**
 * Capacités que la dépendance doit fournir à l'exécution.
 */
final class DependencyBranchContractTest extends WP_UnitTestCase {

	/**
	 * Option dans laquelle la dépendance fige la branche retenue.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'restrict_content_chosen_version';

	/**
	 * Fonctions dont l'absence caractérise la branche historique.
	 *
	 * Ce ne sont pas les seules que le plugin appellera : ce sont celles dont
	 * la présence atteste que la branche moderne est en mémoire.
	 *
	 * @var list<string>
	 */
	private const BRANCH_MARKERS = array(
		'rcp_get_membership_levels',
		'rcp_is_active',
	);

	/**
	 * La dépendance est chargée.
	 *
	 * @return void
	 */
	public function test_the_dependency_is_loaded(): void {
		$this->assertTrue(
			function_exists( 'rcp_get_membership_levels' ) || class_exists( 'RC_Requirements_Check' ),
			'Ni Restrict Content ni Restrict Content Pro n\'est chargé : '
			. 'exécutez « make prepare-tests ».'
		);
	}

	/**
	 * La branche moderne est chargée, et non la branche historique.
	 *
	 * @return void
	 */
	public function test_the_modern_branch_is_loaded(): void {
		$chosen = get_option( self::VERSION_OPTION );

		$this->assertNotSame(
			'legacy',
			$chosen,
			'La dépendance a figé sa branche historique dans l\'option « '
			. self::VERSION_OPTION . ' ». Cette branche n\'expose aucune fonction rcp_*, '
			. 'et aucune fonctionnalité de ce plugin ne peut s\'y exécuter (JE-003).'
		);
	}

	/**
	 * Les fonctions qui caractérisent la branche moderne sont définies.
	 *
	 * L'option ne suffit pas : elle dit ce que la dépendance a décidé, pas ce
	 * qu'elle a effectivement chargé. Seule la présence des fonctions l'établit,
	 * et c'est la règle « détecter les capacités, pas les versions ».
	 *
	 * @return void
	 */
	public function test_branch_markers_are_defined(): void {
		foreach ( self::BRANCH_MARKERS as $marker ) {
			$this->assertTrue(
				function_exists( $marker ),
				sprintf(
					'La fonction %s est absente. La dépendance est active mais charge une '
					. 'base de code qui n\'expose pas l\'API attendue (JE-003).',
					$marker
				)
			);
		}
	}

	/**
	 * Le défaut amont qui fausse la détection de branche est toujours présent.
	 *
	 * `restrict_content_check_posts_for_shortcode()` émet une requête SQL
	 * invalide et renvoie donc invariablement `false` (JE-003). Ce test ne
	 * protège rien : il **surveille**. Le jour où il échoue, c'est que le défaut
	 * a été corrigé en amont, et que des sites nouvellement installés seront
	 * orientés autrement. C'est une information, et elle doit arriver par un
	 * échec de suite plutôt que par un incident en production.
	 *
	 * @return void
	 */
	public function test_upstream_shortcode_detection_is_still_broken(): void {
		if ( ! class_exists( 'RC_Requirements_Check' ) ) {
			$this->assertTrue(
				function_exists( 'rcp_get_membership_levels' ),
				'Ni la classe d\'amorçage de la variante libre, ni l\'API attendue : '
				. 'l\'environnement de test n\'est pas celui qu\'on croit.'
			);

			return;
		}

		$reflection = new \ReflectionClass( 'RC_Requirements_Check' );
		$file       = (string) $reflection->getFileName();
		$source     = (string) file_get_contents( $file );

		$this->assertStringContainsString(
			'CONTAINS',
			$source,
			'La détection amont par code court n\'utilise plus l\'opérateur inexistant '
			. '« CONTAINS » : le défaut consigné en JE-003 a été corrigé. Relire le '
			. 'nouveau code — le routage des sites nouvellement installés a changé.'
		);
	}
}
