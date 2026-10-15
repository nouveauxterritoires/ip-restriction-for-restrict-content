<?php
/**
 * Écran d'état des capacités de la dépendance, en lecture seule.
 *
 * Cet écran est le pendant visible de la sonde : il montre, capacité par
 * capacité, ce que le plugin a constaté **sur cette requête**, et la ligne
 * amont qui a servi de preuve. Il n'écrit rien et ne décide rien.
 *
 * Il est délibérément rangé sous l'outillage de WordPress et gardé par
 * `manage_options`, et non sous le menu de la dépendance gardé par
 * `rcp_manage_settings` : c'est précisément quand la dépendance est absente ou
 * revenue à sa branche historique que cet écran sert, et son menu n'existe
 * alors pas. L'écran de réglages du cahier des charges §7.1, lui, ira bien sous
 * le menu de la dépendance — il n'a de sens que si elle fonctionne.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Admin;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Domain\Capability_Catalogue;
use Ip_Restriction_For_Restrict_Content\Domain\Capability_Report;
use Ip_Restriction_For_Restrict_Content\Domain\Dependency_Diagnosis;

/**
 * Rendu de l'écran d'état.
 */
final class Capability_Status_Page {

	/**
	 * Identifiant de l'écran.
	 *
	 * @var string
	 */
	public const SLUG = 'rcipr-capabilities';

	/**
	 * Capacité exigée pour consulter l'écran.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Option amont citée à titre d'indication, jamais lue pour décider.
	 *
	 * @var string
	 */
	private const UPSTREAM_VERSION_OPTION = 'restrict_content_chosen_version';

	/**
	 * Rapport à afficher.
	 *
	 * @var Capability_Report
	 */
	private Capability_Report $report;

	/**
	 * Diagnostic de la dépendance.
	 *
	 * @var Dependency_Diagnosis
	 */
	private Dependency_Diagnosis $dependency;

	/**
	 * Construit l'écran.
	 *
	 * @param Capability_Report    $report     Rapport constaté sur cette requête.
	 * @param Dependency_Diagnosis $dependency Diagnostic de la dépendance.
	 */
	public function __construct( Capability_Report $report, Dependency_Diagnosis $dependency ) {
		$this->report     = $report;
		$this->dependency = $dependency;
	}

	/**
	 * Accroche l'écran à l'administration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
	}

	/**
	 * Déclare l'entrée de menu.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_management_page(
			__( 'Restriction par adresse IP — état des capacités', 'ip-restriction-for-restrict-content' ),
			__( 'Restriction par adresse IP', 'ip-restriction-for-restrict-content' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Affiche l'écran.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Restriction par adresse IP — état des capacités', 'ip-restriction-for-restrict-content' ) . '</h1>';
		echo '<p>' . esc_html__( 'Ce qui suit est constaté sur la requête en cours. Rien n\'est mémorisé : la base de code chargée par la dépendance peut changer entre deux requêtes.', 'ip-restriction-for-restrict-content' ) . '</p>';

		$this->render_dependency_state();
		$this->render_features();
		$this->render_capabilities();

		echo '</div>';
	}

	/**
	 * Affiche l'état de la dépendance et, à titre d'indication seulement, la
	 * valeur de son option de branche.
	 *
	 * @return void
	 */
	private function render_dependency_state(): void {
		echo '<h2>' . esc_html__( 'Dépendance', 'ip-restriction-for-restrict-content' ) . '</h2>';
		echo '<p>' . esc_html( Dependency_Labels::state( $this->dependency ) ) . '</p>';

		if ( $this->dependency->is_legacy_branch() ) {
			echo '<p>' . esc_html( Dependency_Labels::remediation() ) . '</p>';
		}

		$chosen = get_option( self::UPSTREAM_VERSION_OPTION );

		if ( is_scalar( $chosen ) && '' !== (string) $chosen ) {
			echo '<p><em>' . esc_html(
				sprintf(
					/* translators: 1: nom de l'option amont, 2: valeur de l'option. */
					__( 'Indication, non utilisée pour décider : l\'option amont « %1$s » vaut « %2$s ».', 'ip-restriction-for-restrict-content' ),
					self::UPSTREAM_VERSION_OPTION,
					(string) $chosen
				)
			) . '</em></p>';
		}
	}

	/**
	 * Affiche la disponibilité de chaque fonctionnalité.
	 *
	 * @return void
	 */
	private function render_features(): void {
		echo '<h2>' . esc_html__( 'Fonctionnalités', 'ip-restriction-for-restrict-content' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Fonctionnalité', 'ip-restriction-for-restrict-content' ) . '</th>';
		echo '<th>' . esc_html__( 'État', 'ip-restriction-for-restrict-content' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( Capability_Catalogue::features() as $feature ) {
			$available = $this->report->is_feature_available( $feature );

			echo '<tr><td>' . esc_html( Capability_Labels::feature( $feature ) ) . '</td><td>';
			echo esc_html(
				$available
					? __( 'disponible', 'ip-restriction-for-restrict-content' )
					: __( 'indisponible — capacité amont absente', 'ip-restriction-for-restrict-content' )
			);
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Affiche le détail des capacités et leur preuve.
	 *
	 * @return void
	 */
	private function render_capabilities(): void {
		echo '<h2>' . esc_html__( 'Capacités constatées', 'ip-restriction-for-restrict-content' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Symbole', 'ip-restriction-for-restrict-content' ) . '</th>';
		echo '<th>' . esc_html__( 'Accroche fournie', 'ip-restriction-for-restrict-content' ) . '</th>';
		echo '<th>' . esc_html__( 'État', 'ip-restriction-for-restrict-content' ) . '</th>';
		echo '<th>' . esc_html__( 'Preuve amont', 'ip-restriction-for-restrict-content' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $this->report->capabilities() as $capability ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $capability->symbol() ) . '</code></td>';
			echo '<td>' . ( '' === $capability->provides() ? '—' : '<code>' . esc_html( $capability->provides() ) . '</code>' ) . '</td>';
			echo '<td>' . esc_html( Capability_Labels::state( $this->report->state_of( $capability ) ) ) . '</td>';
			echo '<td><code>' . esc_html( $capability->evidence() ) . '</code></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
