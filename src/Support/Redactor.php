<?php
/**
 * Expurgation d'une adresse avant écriture — SEC-06.
 *
 * L'adresse d'un visiteur est une donnée personnelle, et ce plugin n'a aucune
 * finalité de conservation : elle n'est jamais persistée, et jamais écrite en
 * clair dans un message. Pas davantage dans un argument d'action que
 * l'exploitant pourrait journaliser sans y penser (§8.2) — ce qu'il journalise
 * ensuite relève de sa responsabilité, mais ce que le plugin lui remet relève
 * de la nôtre.
 *
 * Expurger n'est pas supprimer. Le réseau est ce qui permet à un administrateur
 * de reconnaître « c'est bien un visiteur de notre siège » ; l'hôte est ce qui
 * désigne une personne. On garde le premier et on masque le second.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Support;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Address\Client_Address;
use Ip_Restriction_For_Restrict_Content\Address\Ip_Number;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Family;

/**
 * Masquage de la partie identifiante d'une adresse.
 */
final class Redactor {

	/**
	 * Marque de l'octet masqué en IPv4.
	 *
	 * @var string
	 */
	private const IPV4_MASK = 'x';

	/**
	 * Préfixe conservé en IPv6, en bits.
	 *
	 * @var int
	 */
	private const IPV6_KEPT_PREFIX = 64;

	/**
	 * Mention d'une adresse qu'on n'a pas pu déterminer.
	 *
	 * Le texte n'est pas traduit : cette couche ne dépend pas de WordPress, et
	 * la chaîne est destinée à un journal, non à un écran. Ce que l'interface
	 * affiche est traduit ailleurs.
	 *
	 * @var string
	 */
	public const UNDETERMINED = '(adresse indéterminable)';

	/**
	 * Expurge une adresse.
	 *
	 * @param Ip_Number|null $number Adresse à expurger.
	 * @return string Forme expurgée, lisible, jamais complète.
	 */
	public static function redact( ?Ip_Number $number ): string {
		if ( null === $number ) {
			return self::UNDETERMINED;
		}

		if ( Rule_Family::IPV4 === $number->family() ) {
			$segments = explode( '.', $number->to_string() );

			array_pop( $segments );
			$segments[] = self::IPV4_MASK;

			return implode( '.', $segments );
		}

		return $number->network( self::IPV6_KEPT_PREFIX )->to_string() . '/' . self::IPV6_KEPT_PREFIX;
	}

	/**
	 * Expurge l'adresse portée par un objet d'adresse.
	 *
	 * @param Client_Address $address Adresse du visiteur.
	 * @return string
	 */
	public static function redact_address( Client_Address $address ): string {
		return self::redact( $address->number() );
	}
}
