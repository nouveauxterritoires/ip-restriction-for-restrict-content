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

use Ip_Restriction_For_Restrict_Content\Domain\Capability;
use Ip_Restriction_For_Restrict_Content\Domain\Capability_Catalogue;
use Ip_Restriction_For_Restrict_Content\Integration\Capability_Probe;
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
	 * Toutes les capacités déclarées au catalogue sont en mémoire.
	 *
	 * C'est le test qui relie le catalogue à la réalité. Les classes du
	 * domaine disent ce que le plugin attend ; ce test dit si l'amont le
	 * fournit. Les capacités que la dépendance ne charge qu'en administration
	 * sont chargées explicitement ici, pour qu'aucune ne reste hors de portée
	 * de la vérification.
	 *
	 * @return void
	 */
	public function test_every_catalogued_capability_is_available(): void {
		require_once RCP_PLUGIN_DIR . 'core/includes/admin/metabox.php';

		$report = Capability_Probe::observe( true )->report();

		$this->assertSame(
			array(),
			$report->missing_symbols(),
			'L\'amont ne fournit plus toutes les capacités déclarées au catalogue : '
			. implode( ', ', $report->missing_symbols() ) . '. '
			. 'Chaque symbole absent désactive la fonctionnalité qui en dépend (E-04).'
		);
	}

	/**
	 * Hors administration, aucune capacité de contexte d'administration n'est
	 * rapportée absente.
	 *
	 * C'est la contrepartie du test précédent, et elle protège d'un avis faux :
	 * sur une requête de façade, la boîte de restriction n'est pas chargée, et
	 * ce n'est pas une rupture amont.
	 *
	 * @return void
	 */
	public function test_admin_only_capabilities_are_not_reported_missing_on_the_front(): void {
		$report = Capability_Probe::observe( false )->report();

		$this->assertSame( array(), $report->missing_symbols() );

		foreach ( Capability_Catalogue::declared() as $capability ) {
			if ( Capability::CONTEXT_ADMIN !== $capability->context() ) {
				continue;
			}

			$this->assertSame(
				\Ip_Restriction_For_Restrict_Content\Domain\Capability_Report::UNOBSERVABLE,
				$report->state_of( $capability ),
				$capability->symbol() . ' doit être rapportée non observable hors administration.'
			);
		}
	}

	/**
	 * La sonde conclut que la dépendance est utilisable dans cet
	 * environnement.
	 *
	 * @return void
	 */
	public function test_the_probe_concludes_the_dependency_is_usable(): void {
		$diagnosis = Capability_Probe::observe( false )->dependency();

		$this->assertTrue(
			$diagnosis->is_usable(),
			'La sonde ne conclut pas à une dépendance utilisable : état « ' . $diagnosis->state() . ' ».'
		);
	}
}
