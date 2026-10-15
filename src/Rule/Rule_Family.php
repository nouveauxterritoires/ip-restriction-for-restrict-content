<?php
/**
 * Famille d'adresses, sans dépendance à WordPress.
 *
 * Deux familles, et la distinction n'est pas décorative : une règle et une
 * adresse de familles différentes ne correspondent pas, et ne produisent pas
 * d'erreur (cahier des charges §4.3). Toute la décision passe par des entiers
 * de longueur fixe, et c'est la famille qui donne cette longueur.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Rule;

defined( 'ABSPATH' ) || exit;

/**
 * Les deux familles d'adresses reconnues.
 */
enum Rule_Family: string {

	/**
	 * Adressage sur 32 bits.
	 */
	case IPV4 = 'ipv4';

	/**
	 * Adressage sur 128 bits.
	 */
	case IPV6 = 'ipv6';

	/**
	 * Longueur, en octets, de la forme binaire d'une adresse de cette famille.
	 *
	 * @return int
	 */
	public function byte_length(): int {
		return self::IPV4 === $this ? 4 : 16;
	}

	/**
	 * Préfixe CIDR maximal de cette famille (G-03).
	 *
	 * @return int
	 */
	public function max_prefix(): int {
		return $this->byte_length() * 8;
	}
}
