<?php
/**
 * Libellés des motifs de refus d'une entrée.
 *
 * FNC-22 exige que l'administrateur sache quelle ligne a été ignorée **et
 * pourquoi**. Un motif traduit par « syntaxe » pour les dix cas ne lui
 * apprendrait rien : chaque motif nomme donc ce qui a été refusé et, quand
 * c'est utile, la forme à employer à la place.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Admin;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Rule\Rule_Rejection;

/**
 * Traduction des motifs de refus.
 */
final class Rejection_Labels {

	/**
	 * Libellé d'un motif de refus.
	 *
	 * @param string $reason Motif issu de `Rule_Rejection`.
	 * @return string
	 */
	public static function reason( string $reason ): string {
		switch ( $reason ) {
			case Rule_Rejection::MIXED_FAMILIES:
				return __( 'les deux bornes de la plage ne sont pas de la même famille d\'adresses', 'ip-restriction-for-restrict-content' );
			case Rule_Rejection::INVERTED_RANGE:
				return __( 'la première borne de la plage est supérieure à la seconde', 'ip-restriction-for-restrict-content' );
			case Rule_Rejection::PREFIX_OUT_OF_RANGE:
				return __( 'le préfixe sort des bornes : de 0 à 32 en IPv4, de 0 à 128 en IPv6', 'ip-restriction-for-restrict-content' );
			case Rule_Rejection::WILDCARD_BEFORE_LITERAL:
				return __( 'un joker ne peut pas précéder un segment chiffré ; le joker remplace les octets de fin', 'ip-restriction-for-restrict-content' );
			case Rule_Rejection::COVERS_EVERY_ADDRESS:
				return __( 'cette règle désignerait toutes les adresses, ce qui ouvrirait tout le contenu restreint', 'ip-restriction-for-restrict-content' );
			case Rule_Rejection::WILDCARD_IN_CIDR:
				return __( 'un bloc CIDR ne peut pas porter de joker', 'ip-restriction-for-restrict-content' );
			case Rule_Rejection::HOSTNAME:
				return __( 'un nom d\'hôte n\'est pas une adresse ; indiquez une adresse IP', 'ip-restriction-for-restrict-content' );
			case Rule_Rejection::IPV6_WILDCARD:
				return __( 'le joker n\'est pas accepté en IPv6 ; employez un bloc CIDR, par exemple 2001:db8::/32', 'ip-restriction-for-restrict-content' );
			case Rule_Rejection::AMBIGUOUS_OCTET:
				return __( 'un octet commençant par zéro est ambigu ; écrivez 10 plutôt que 010', 'ip-restriction-for-restrict-content' );
			default:
				return __( 'cette entrée ne correspond à aucune forme acceptée', 'ip-restriction-for-restrict-content' );
		}
	}
}
