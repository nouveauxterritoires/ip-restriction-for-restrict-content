<?php
/**
 * Libellés traduisibles des fonctionnalités et des états de capacité.
 *
 * Les classes du domaine ne portent que des clés : composer un libellé
 * exigerait les fonctions de traduction de WordPress, dont elles ne dépendent
 * pas. La traduction est donc ici, dans la couche d'application, et en un seul
 * endroit — l'avis de prérequis et l'écran d'état nomment ainsi les mêmes
 * choses de la même façon.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Admin;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Domain\Capability_Catalogue;
use Ip_Restriction_For_Restrict_Content\Domain\Capability_Report;

/**
 * Traduction des clés du domaine.
 */
final class Capability_Labels {

	/**
	 * Libellé d'une fonctionnalité.
	 *
	 * @param string $feature Clé de fonctionnalité.
	 * @return string
	 */
	public static function feature( string $feature ): string {
		switch ( $feature ) {
			case Capability_Catalogue::FEATURE_CORE:
				return __( 'Amorçage du plugin', 'ip-restriction-for-restrict-content' );
			case Capability_Catalogue::FEATURE_CONTENT_ACCESS:
				return __( 'Contournement des restrictions de contenu', 'ip-restriction-for-restrict-content' );
			case Capability_Catalogue::FEATURE_TERM_ACCESS:
				return __( 'Couverture des restrictions de taxonomie', 'ip-restriction-for-restrict-content' );
			case Capability_Catalogue::FEATURE_REGISTRATION_BLOCK:
				return __( "Blocage d'inscription", 'ip-restriction-for-restrict-content' );
			case Capability_Catalogue::FEATURE_LEVEL_SCOPE:
				return __( "Portée par niveau d'adhésion", 'ip-restriction-for-restrict-content' );
			case Capability_Catalogue::FEATURE_POST_SCOPE:
				return __( 'Portée par contenu', 'ip-restriction-for-restrict-content' );
			case Capability_Catalogue::FEATURE_SETTINGS:
				return __( 'Écran de réglages', 'ip-restriction-for-restrict-content' );
			default:
				return $feature;
		}
	}

	/**
	 * Libellé d'un état de capacité.
	 *
	 * @param string $state État issu de `Capability_Report`.
	 * @return string
	 */
	public static function state( string $state ): string {
		switch ( $state ) {
			case Capability_Report::PRESENT:
				return __( 'présente', 'ip-restriction-for-restrict-content' );
			case Capability_Report::MISSING:
				return __( 'absente', 'ip-restriction-for-restrict-content' );
			case Capability_Report::UNOBSERVABLE:
				return __( 'non observable sur cette requête', 'ip-restriction-for-restrict-content' );
			default:
				return $state;
		}
	}
}
