<?php
/**
 * Résolution de l'adresse du visiteur — R-01 à R-07.
 *
 * C'est le principal risque de sécurité du plugin, et il est invisible : une
 * liste d'autorisation évaluée sur une valeur que le visiteur contrôle n'est
 * pas un contrôle d'accès dégradé, c'est un contournement d'authentification à
 * une ligne d'en-tête. Seul `REMOTE_ADDR` est renseigné par le serveur web
 * depuis la connexion TCP ; tout en-tête HTTP est écrit par le client.
 *
 * Cette classe ne lit **rien** : ni `$_SERVER`, ni réglage, ni option. Elle
 * reçoit l'adresse de connexion, la liste de proxys déclarée et la valeur de
 * l'en-tête, et renvoie un objet de valeur. C'est ce qui permet d'exercer les
 * onze scénarios d'attaque du §11.3 en quelques millisecondes, et il faut que
 * chacun coûte une milliseconde pour qu'ils soient tous écrits.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Address;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Rule\Matcher;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Set;

/**
 * Détermination de l'adresse à évaluer.
 */
final class Client_Address_Resolver {

	/**
	 * Détermine l'adresse du visiteur.
	 *
	 * L'ordre des contrôles **est** la règle de sécurité : l'en-tête n'est
	 * consulté qu'après avoir établi que des proxys sont déclarés (R-02) et
	 * que la connexion vient de l'un d'eux (R-03). Sur une installation par
	 * défaut, aucun en-tête n'est lu, quoi que le visiteur envoie (R-01).
	 *
	 * @param string      $connection_address Valeur de `REMOTE_ADDR`.
	 * @param Rule_Set    $trusted_proxies    Proxys de confiance déclarés.
	 * @param string|null $header_value       Valeur de l'en-tête déclaré, ou null si aucun.
	 * @return Client_Address
	 */
	public static function resolve(
		string $connection_address,
		Rule_Set $trusted_proxies,
		?string $header_value
	): Client_Address {
		$connection = Ip_Number::from_address( trim( $connection_address ) );

		if ( null === $connection ) {
			return Client_Address::undetermined( Client_Address::NO_CONNECTION_ADDRESS );
		}

		if ( ! Matcher::match( $connection, $trusted_proxies )->is_match() ) {
			return Client_Address::from_connection( $connection );
		}

		return self::walk_chain( (string) $header_value, $trusted_proxies );
	}

	/**
	 * Remonte la chaîne de transfert de droite à gauche (R-04).
	 *
	 * `X-Forwarded-For` s'accroît par la gauche à chaque saut : un client qui
	 * envoie déjà une valeur fait arriver « <injectée>, <réelle> ». Lire le
	 * premier élément — ce que fait la dépendance — retiendrait l'injectée.
	 * Remonter depuis la droite en sautant les proxys déclarés retient la
	 * dernière valeur qu'aucun proxy de confiance n'a pu inventer.
	 *
	 * La lecture s'arrête au premier élément qui n'est pas un proxy déclaré :
	 * ce qui se trouve à sa gauche est du texte que le client a pu écrire
	 * lui-même, et s'en servir pour fermer la résolution donnerait à n'importe
	 * quel visiteur le moyen de neutraliser la liste d'autorisation en envoyant
	 * un en-tête absurde.
	 *
	 * @param string   $header_value    Valeur de l'en-tête.
	 * @param Rule_Set $trusted_proxies Proxys de confiance déclarés.
	 * @return Client_Address
	 */
	private static function walk_chain( string $header_value, Rule_Set $trusted_proxies ): Client_Address {
		if ( '' === trim( $header_value ) ) {
			return Client_Address::undetermined( Client_Address::MISSING_HEADER );
		}

		$elements = explode( ',', $header_value );

		for ( $index = count( $elements ) - 1; $index >= 0; $index-- ) {
			$number = self::element_to_number( trim( $elements[ $index ] ) );

			if ( null === $number ) {
				return Client_Address::undetermined( Client_Address::HEADER_UNPARSABLE );
			}

			if ( ! Matcher::match( $number, $trusted_proxies )->is_match() ) {
				return Client_Address::from_header( $number, $index );
			}
		}

		return Client_Address::undetermined( Client_Address::PROXY_CHAIN_EXHAUSTED );
	}

	/**
	 * Convertit un élément de la chaîne, port éventuel compris.
	 *
	 * Les proxys réels émettent l'adresse avec son port : `192.0.2.10:52000`
	 * en IPv4, `[2001:db8::1]:443` en IPv6 (§11.4). Refuser ces formes rendrait
	 * le plugin inutilisable derrière les CDN qui les produisent ; les accepter
	 * n'ajoute aucune ambiguïté, puisque la séparation y est sans équivoque.
	 * Tout le reste est refusé : la grammaire est close ici comme ailleurs.
	 *
	 * @param string $element Élément de la chaîne, déjà débarrassé de ses espaces.
	 * @return Ip_Number|null
	 */
	private static function element_to_number( string $element ): ?Ip_Number {
		if ( 0 === strpos( $element, '[' ) ) {
			$closing = strpos( $element, ']' );

			if ( false === $closing ) {
				return null;
			}

			$remainder = substr( $element, $closing + 1 );

			if ( '' !== $remainder && 1 !== preg_match( '/^:[0-9]+$/', $remainder ) ) {
				return null;
			}

			return Ip_Number::from_address( substr( $element, 1, $closing - 1 ) );
		}

		if ( 1 === substr_count( $element, ':' ) ) {
			$parts = explode( ':', $element );

			if ( 1 !== preg_match( '/^[0-9]+$/', $parts[1] ) ) {
				return null;
			}

			return Ip_Number::from_address( $parts[0] );
		}

		return Ip_Number::from_address( $element );
	}
}
