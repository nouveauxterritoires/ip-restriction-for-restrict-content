<?php
/**
 * Libellés de l'état de la dépendance — exigence E-05.
 *
 * E-05 est une exigence de compréhensibilité autant que d'exactitude : l'avis
 * nomme la cause **constatée**, pas la cause supposée. Un administrateur qui
 * lit « Restrict Content, actif » dans la liste des prérequis absents alors que
 * l'extension figure, active, dans sa liste d'extensions, conclut que l'avis
 * est faux et cherche ailleurs.
 *
 * L'avis nomme donc aussi l'action de retour, et non seulement la cause : celui
 * qui vient de basculer est exactement celui qui peut revenir en arrière — la
 * bascule exige `manage_options` (`restrictcontent.php:680`), et l'avis de
 * prérequis n'est rendu qu'à qui détient `activate_plugins`.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Admin;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Domain\Dependency_Diagnosis;

/**
 * Traduction de l'état de la dépendance.
 */
final class Dependency_Labels {

	/**
	 * Libellé du prérequis absent, selon la cause constatée.
	 *
	 * @param Dependency_Diagnosis $diagnosis Diagnostic constaté.
	 * @return string
	 */
	public static function missing_requirement( Dependency_Diagnosis $diagnosis ): string {
		if ( $diagnosis->is_legacy_branch() ) {
			return __(
				'Restrict Content est actif, mais charge sa base de code historique, qui n\'expose pas l\'interface de programmation nécessaire.',
				'ip-restriction-for-restrict-content'
			);
		}

		return __( 'Restrict Content ou Restrict Content Pro, actif', 'ip-restriction-for-restrict-content' );
	}

	/**
	 * Libellé de l'état, pour l'écran d'état des capacités.
	 *
	 * @param Dependency_Diagnosis $diagnosis Diagnostic constaté.
	 * @return string
	 */
	public static function state( Dependency_Diagnosis $diagnosis ): string {
		if ( $diagnosis->is_usable() ) {
			return __( 'Active, et l\'interface de programmation attendue est en mémoire.', 'ip-restriction-for-restrict-content' );
		}

		if ( $diagnosis->is_legacy_branch() ) {
			return self::missing_requirement( $diagnosis );
		}

		return __( 'Aucune des deux variantes n\'est en mémoire sur cette requête.', 'ip-restriction-for-restrict-content' );
	}

	/**
	 * Action de retour, nommée à qui peut l'accomplir.
	 *
	 * @return string
	 */
	public static function remediation(): string {
		return __(
			'Pour y remédier : dans l\'administration de Restrict Content, repassez sur sa base de code moderne. Ce plugin repart dès la requête suivante, sans réactivation ni réenregistrement.',
			'ip-restriction-for-restrict-content'
		);
	}
}
