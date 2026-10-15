<?php
/**
 * Adresse convertie en entier de longueur fixe, sans dépendance à WordPress.
 *
 * PHP n'a pas d'entier de 128 bits, et une adresse IPv6 n'entre pas dans un
 * entier natif. Passer par la virgule flottante perdrait des bits en silence —
 * exactement le genre de perte qui accorde un accès à qui ne devait pas
 * l'avoir. La représentation retenue est donc la forme binaire de longueur
 * fixe de `inet_pton()`, et la comparaison est lexicographique sur ces
 * octets : pour deux chaînes de même longueur en gros-boutien, l'ordre
 * lexicographique **est** l'ordre numérique. C'est exact, sans dépendance, et
 * identique en IPv4 et en IPv6.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Address;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Rule\Rule_Family;

/**
 * Entier de longueur fixe représentant une adresse.
 */
final class Ip_Number {

	/**
	 * Forme binaire de l'adresse.
	 *
	 * @var string
	 */
	private string $packed;

	/**
	 * Famille de l'adresse.
	 *
	 * @var Rule_Family
	 */
	private Rule_Family $family;

	/**
	 * Construit une adresse déjà validée.
	 *
	 * @param string      $packed Forme binaire.
	 * @param Rule_Family $family Famille.
	 */
	private function __construct( string $packed, Rule_Family $family ) {
		$this->packed = $packed;
		$this->family = $family;
	}

	/**
	 * Convertit une adresse textuelle.
	 *
	 * @param string $address Adresse littérale, IPv4 pointée ou IPv6.
	 * @return self|null L'adresse convertie, ou null si le texte n'est pas une adresse.
	 */
	public static function from_address( string $address ): ?self {
		if ( ! self::is_canonical( $address ) ) {
			return null;
		}

		$packed = inet_pton( $address );

		if ( false === $packed ) {
			return null;
		}

		/*
		 * `inet_pton()` ne renvoie que quatre ou seize octets : déduire la
		 * famille de la longueur est exact, et n'introduit pas de branche
		 * qu'aucun test ne pourrait prendre (§11.6).
		 */
		$family = Rule_Family::IPV4->byte_length() === strlen( $packed )
			? Rule_Family::IPV4
			: Rule_Family::IPV6;

		return new self( $packed, $family );
	}

	/**
	 * Première adresse de la famille.
	 *
	 * @param Rule_Family $family Famille.
	 * @return self
	 */
	public static function min_of( Rule_Family $family ): self {
		return new self( str_repeat( "\x00", $family->byte_length() ), $family );
	}

	/**
	 * Dernière adresse de la famille.
	 *
	 * @param Rule_Family $family Famille.
	 * @return self
	 */
	public static function max_of( Rule_Family $family ): self {
		return new self( str_repeat( "\xff", $family->byte_length() ), $family );
	}

	/**
	 * Refuse les écritures ambiguës avant toute conversion.
	 *
	 * `inet_pton()` lit `010.0.0.1` comme `10.0.0.1`, là où une bibliothèque
	 * qui interprète l'octal y lit `8.0.0.1`. L'écriture est donc ambiguë, et
	 * FNC-20 tranche : ce que l'analyseur ne comprend pas sans ambiguïté est
	 * rejeté, jamais interprété par approximation. Le même contrôle s'applique
	 * à la partie IPv4 d'une forme mixte.
	 *
	 * @param string $address Adresse textuelle.
	 * @return bool
	 */
	private static function is_canonical( string $address ): bool {
		if ( '' === $address ) {
			return false;
		}

		$dotted = strrchr( $address, ':' );
		$dotted = false === $dotted ? $address : substr( $dotted, 1 );

		if ( false === strpos( $dotted, '.' ) ) {
			return true;
		}

		foreach ( explode( '.', $dotted ) as $octet ) {
			if ( 1 !== preg_match( '/^(0|[1-9][0-9]{0,2})$/', $octet ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Forme binaire de l'adresse.
	 *
	 * @return string
	 */
	public function packed(): string {
		return $this->packed;
	}

	/**
	 * Famille de l'adresse.
	 *
	 * @return Rule_Family
	 */
	public function family(): Rule_Family {
		return $this->family;
	}

	/**
	 * Forme textuelle normalisée.
	 *
	 * @return string
	 */
	public function to_string(): string {
		/*
		 * La forme binaire est toujours de la longueur de sa famille : la
		 * conversion inverse ne peut pas échouer. La transtypage explicite
		 * évite une branche d'erreur qu'aucun test ne pourrait prendre, ce que
		 * le §11.5 proscrit.
		 */
		return (string) inet_ntop( $this->packed );
	}

	/**
	 * Compose une adresse IPv4 depuis ses quatre octets.
	 *
	 * Les octets sont supposés déjà validés par l'appelant : c'est le rôle de
	 * la grammaire (§4.2), et le dédoubler ici créerait une branche que rien
	 * ne pourrait prendre.
	 *
	 * @param array<int, int> $bytes Quatre octets de 0 à 255.
	 * @return self
	 */
	public static function from_ipv4_bytes( array $bytes ): self {
		$packed = '';

		foreach ( $bytes as $byte ) {
			$packed .= chr( $byte );
		}

		return new self( $packed, Rule_Family::IPV4 );
	}

	/**
	 * Adresse du réseau pour un préfixe donné.
	 *
	 * L'arithmétique est ici plutôt que dans la règle : c'est du calcul
	 * d'adresse, et le faire au niveau de l'octet dans deux classes
	 * différentes finirait par diverger.
	 *
	 * @param int $prefix Longueur du préfixe, supposée valide pour la famille.
	 * @return self
	 */
	public function network( int $prefix ): self {
		return new self( $this->masked( $prefix, false ), $this->family );
	}

	/**
	 * Adresse de diffusion pour un préfixe donné.
	 *
	 * @param int $prefix Longueur du préfixe, supposée valide pour la famille.
	 * @return self
	 */
	public function broadcast( int $prefix ): self {
		return new self( $this->masked( $prefix, true ), $this->family );
	}

	/**
	 * Applique le masque du préfixe, bits d'hôte à zéro ou à un.
	 *
	 * @param int  $prefix Longueur du préfixe.
	 * @param bool $fill   Porter les bits d'hôte à un.
	 * @return string Forme binaire masquée.
	 */
	private function masked( int $prefix, bool $fill ): string {
		$packed = '';
		$length = $this->family->byte_length();

		for ( $index = 0; $index < $length; $index++ ) {
			$mask = self::mask_for_byte( $prefix, $index );
			$byte = ord( $this->packed[ $index ] ) & $mask;

			$packed .= chr( $fill ? $byte | ( ~$mask & 0xff ) : $byte );
		}

		return $packed;
	}

	/**
	 * Masque du nième octet pour un préfixe donné.
	 *
	 * @param int $prefix Longueur du préfixe.
	 * @param int $index  Rang de l'octet.
	 * @return int Masque de huit bits.
	 */
	private static function mask_for_byte( int $prefix, int $index ): int {
		$remaining = $prefix - ( $index * 8 );

		if ( $remaining >= 8 ) {
			return 0xff;
		}

		if ( $remaining <= 0 ) {
			return 0x00;
		}

		return ( 0xff << ( 8 - $remaining ) ) & 0xff;
	}

	/**
	 * Indique si deux adresses relèvent de la même famille.
	 *
	 * @param self $other Autre adresse.
	 * @return bool
	 */
	public function is_same_family( self $other ): bool {
		return $this->family === $other->family;
	}

	/**
	 * Compare deux adresses de la même famille.
	 *
	 * @param self $other Autre adresse.
	 * @return int Négatif, nul ou positif, comme `strcmp()`.
	 */
	public function compare( self $other ): int {
		return strcmp( $this->packed, $other->packed );
	}

	/**
	 * Égalité d'adresse, famille comprise.
	 *
	 * @param self $other Autre adresse.
	 * @return bool
	 */
	public function equals( self $other ): bool {
		return $this->is_same_family( $other ) && 0 === $this->compare( $other );
	}

	/**
	 * Appartenance à un intervalle, bornes incluses (FNC-15).
	 *
	 * Une adresse d'une autre famille que l'intervalle n'y appartient pas, et
	 * ce n'est pas une anomalie : sur un site dual-stack, une règle IPv4
	 * rencontre des visiteurs IPv6 à chaque requête (§4.3).
	 *
	 * @param self $low  Borne basse.
	 * @param self $high Borne haute.
	 * @return bool
	 */
	public function is_between( self $low, self $high ): bool {
		if ( ! $this->is_same_family( $low ) || ! $this->is_same_family( $high ) ) {
			return false;
		}

		return $this->compare( $low ) >= 0 && $this->compare( $high ) <= 0;
	}
}
