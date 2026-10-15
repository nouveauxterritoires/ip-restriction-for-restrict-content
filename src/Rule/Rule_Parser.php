<?php
/**
 * Analyseur de la grammaire close du §4.2.
 *
 * « Close » est le mot qui compte : ce que la grammaire ne décrit pas est
 * rejeté, jamais interprété par approximation (FNC-20). Une règle que son
 * auteur et la machine lisent différemment est le défaut qui ouvre du contenu
 * qu'on croyait fermé, et il ne se constate qu'après.
 *
 * L'analyseur ne calcule aucune borne : il reconnaît une forme, en extrait ses
 * parties, et délègue la normalisation aux fabriques de `Rule` (§4.3). Son
 * travail propre est de nommer le motif du refus, parce que FNC-22 doit dire à
 * l'administrateur quoi corriger.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Rule;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Address\Ip_Number;

/**
 * Analyse d'un texte de réglage en ensemble de règles.
 */
final class Rule_Parser {

	/**
	 * Nombre de segments d'une adresse IPv4 pointée.
	 *
	 * @var int
	 */
	private const IPV4_SEGMENTS = 4;

	/**
	 * Analyse un texte, une entrée par ligne (FNC-19).
	 *
	 * @param string $text Texte du réglage.
	 * @return Rule_Set
	 */
	public static function parse( string $text ): Rule_Set {
		$rules      = array();
		$rejections = array();
		$number     = 0;

		/*
		 * Les fins de ligne sont normalisées avant découpage plutôt que
		 * confiées à une expression régulière : une liste collée depuis un
		 * tableur ou un courriel porte des retours chariot, et un découpage
		 * qui peut échouer ajouterait une branche que rien ne pourrait
		 * prendre (§11.6).
		 */
		$lines = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $text ) );

		foreach ( $lines as $raw ) {
			++$number;

			$entry = self::strip_comment( (string) $raw );

			if ( '' === $entry ) {
				continue;
			}

			$rule = self::parse_entry( $entry );

			if ( $rule instanceof Rule ) {
				$rules[] = $rule;

				continue;
			}

			$rejections[] = Rule_Rejection::because( $entry, $number, $rule );
		}

		return Rule_Set::of( $rules, $rejections );
	}

	/**
	 * Retire le commentaire et les espaces de bordure d'une ligne.
	 *
	 * @param string $line Ligne brute.
	 * @return string Entrée nettoyée, chaîne vide si la ligne n'en porte pas.
	 */
	private static function strip_comment( string $line ): string {
		$hash = strpos( $line, '#' );

		if ( false !== $hash ) {
			$line = substr( $line, 0, $hash );
		}

		return trim( $line );
	}

	/**
	 * Analyse une entrée.
	 *
	 * @param string $entry Entrée nettoyée, non vide.
	 * @return Rule|string La règle, ou le motif du refus.
	 */
	private static function parse_entry( string $entry ) {
		if ( false !== strpos( $entry, '-' ) ) {
			return self::parse_range( $entry );
		}

		return self::parse_pattern( $entry, $entry );
	}

	/**
	 * Analyse une plage (FNC-15, FNC-17).
	 *
	 * @param string $entry Entrée nettoyée.
	 * @return Rule|string La règle, ou le motif du refus.
	 */
	private static function parse_range( string $entry ) {
		$bounds = explode( '-', $entry );

		if ( 2 !== count( $bounds ) ) {
			return Rule_Rejection::SYNTAX;
		}

		$left = self::parse_pattern( trim( $bounds[0] ), $entry );

		if ( ! $left instanceof Rule ) {
			return $left;
		}

		$right = self::parse_pattern( trim( $bounds[1] ), $entry );

		if ( ! $right instanceof Rule ) {
			return $right;
		}

		if ( $left->family() !== $right->family() ) {
			return Rule_Rejection::MIXED_FAMILIES;
		}

		if ( $left->low()->compare( $right->high() ) > 0 ) {
			return Rule_Rejection::INVERTED_RANGE;
		}

		return Rule::range( $left, $right, $entry ) ?? Rule_Rejection::COVERS_EVERY_ADDRESS;
	}

	/**
	 * Analyse un motif : CIDR, joker ou littéral.
	 *
	 * @param string $pattern Motif.
	 * @param string $source  Texte d'origine de l'entrée entière.
	 * @return Rule|string La règle, ou le motif du refus.
	 */
	private static function parse_pattern( string $pattern, string $source ) {
		if ( '' === $pattern ) {
			return Rule_Rejection::SYNTAX;
		}

		if ( false !== strpos( $pattern, '/' ) ) {
			return self::parse_cidr( $pattern, $source );
		}

		if ( false !== strpos( $pattern, '*' ) ) {
			return self::parse_wildcard( $pattern, $source );
		}

		return self::parse_literal( $pattern, $source );
	}

	/**
	 * Analyse un bloc CIDR (FNC-16, G-03, G-06).
	 *
	 * @param string $pattern Motif.
	 * @param string $source  Texte d'origine de l'entrée entière.
	 * @return Rule|string La règle, ou le motif du refus.
	 */
	private static function parse_cidr( string $pattern, string $source ) {
		$parts = explode( '/', $pattern );

		if ( 2 !== count( $parts ) ) {
			return Rule_Rejection::SYNTAX;
		}

		if ( false !== strpos( $parts[0], '*' ) ) {
			return Rule_Rejection::WILDCARD_IN_CIDR;
		}

		if ( 1 !== preg_match( '/^(0|[1-9][0-9]{0,2})$/', $parts[1] ) ) {
			return Rule_Rejection::SYNTAX;
		}

		$address = Ip_Number::from_address( $parts[0] );

		if ( null === $address ) {
			return self::literal_reason( $parts[0] );
		}

		$prefix = (int) $parts[1];

		if ( $prefix > $address->family()->max_prefix() ) {
			return Rule_Rejection::PREFIX_OUT_OF_RANGE;
		}

		return Rule::cidr( $address, $prefix, $source ) ?? Rule_Rejection::COVERS_EVERY_ADDRESS;
	}

	/**
	 * Analyse un motif à jokers (FNC-14, G-04, G-05, ADR-0005).
	 *
	 * Les segments de fin absents sont des jokers implicites : FNC-14 énonce
	 * qu'un joker remplace « un ou plusieurs octets de fin », et c'est ce qui
	 * rend l'exemple documenté `149.58.*-149.60.*` lisible.
	 *
	 * @param string $pattern Motif.
	 * @param string $source  Texte d'origine de l'entrée entière.
	 * @return Rule|string La règle, ou le motif du refus.
	 */
	private static function parse_wildcard( string $pattern, string $source ) {
		if ( false !== strpos( $pattern, ':' ) ) {
			return Rule_Rejection::IPV6_WILDCARD;
		}

		$segments = explode( '.', $pattern );
		$parsed   = array();
		$wildcard = false;

		foreach ( $segments as $segment ) {
			if ( '*' === $segment ) {
				$wildcard = true;
				$parsed[] = null;

				continue;
			}

			if ( $wildcard ) {
				return Rule_Rejection::WILDCARD_BEFORE_LITERAL;
			}

			if ( 1 !== preg_match( '/^(0|[1-9][0-9]{0,2})$/', $segment ) ) {
				return self::segment_reason( $segment );
			}

			if ( (int) $segment > 255 ) {
				return Rule_Rejection::SYNTAX;
			}

			$parsed[] = (int) $segment;
		}

		/*
		 * Les segments de fin absents sont des jokers implicites. Le dernier
		 * segment est nécessairement un joker : un segment littéral placé
		 * après un joker a déjà été refusé par G-04, et un segment qui
		 * contiendrait un joker sans s'y réduire — `1*` — a déjà été refusé
		 * comme faute de forme.
		 */
		$parsed = array_pad( $parsed, self::IPV4_SEGMENTS, null );

		if ( self::IPV4_SEGMENTS !== count( $parsed ) ) {
			return Rule_Rejection::SYNTAX;
		}

		/*
		 * Le seul refus que la fabrique puisse encore prononcer est celui de
		 * G-05 : les segments sont validés un par un juste au-dessus.
		 */
		return Rule::wildcard( $parsed, $source ) ?? Rule_Rejection::COVERS_EVERY_ADDRESS;
	}

	/**
	 * Analyse une adresse littérale (FNC-13, FNC-18, G-07).
	 *
	 * @param string $pattern Motif.
	 * @param string $source  Texte d'origine de l'entrée entière.
	 * @return Rule|string La règle, ou le motif du refus.
	 */
	private static function parse_literal( string $pattern, string $source ) {
		$address = Ip_Number::from_address( $pattern );

		if ( null === $address ) {
			return self::literal_reason( $pattern );
		}

		return Rule::literal( $address, $source );
	}

	/**
	 * Nomme le motif du refus d'un littéral.
	 *
	 * Trois cas distincts, et les distinguer est tout l'intérêt de FNC-22 : un
	 * zéro de tête est une écriture ambiguë (`010.0.0.1` vaut 10 ou 8 selon la
	 * bibliothèque qui le lit), un nom d'hôte est une erreur de nature (G-07),
	 * et le reste est une faute de forme.
	 *
	 * @param string $pattern Motif refusé.
	 * @return string Motif du refus.
	 */
	private static function literal_reason( string $pattern ): string {
		foreach ( explode( '.', $pattern ) as $segment ) {
			if ( 1 === preg_match( '/^0[0-9]+$/', $segment ) ) {
				return Rule_Rejection::AMBIGUOUS_OCTET;
			}
		}

		if ( false === strpos( $pattern, ':' ) && 1 === preg_match( '/[a-z]/i', $pattern ) ) {
			return Rule_Rejection::HOSTNAME;
		}

		return Rule_Rejection::SYNTAX;
	}

	/**
	 * Nomme le motif du refus d'un segment de joker.
	 *
	 * @param string $segment Segment refusé.
	 * @return string Motif du refus.
	 */
	private static function segment_reason( string $segment ): string {
		if ( 1 === preg_match( '/^0[0-9]+$/', $segment ) ) {
			return Rule_Rejection::AMBIGUOUS_OCTET;
		}

		return Rule_Rejection::SYNTAX;
	}
}
