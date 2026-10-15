<?php
/**
 * Une règle valide : un intervalle borné dans une famille d'adresses.
 *
 * Les quatre formes du cahier des charges §3.3 — littéral, joker, CIDR, plage —
 * se ramènent toutes au même objet. C'est ce qui rend la combinaison joker +
 * plage définissable (FNC-17) et la décision uniforme : une seule comparaison
 * d'intervalle, quelle que soit l'écriture qui l'a produit.
 *
 * La normalisation du §4.3 est portée par les fabriques, et non par
 * l'analyseur. Si l'analyseur calculait les bornes, chaque forme aurait sa
 * propre arithmétique, et une faute sur l'une n'aurait aucune chance d'être
 * attrapée par les tests de l'autre.
 *
 * Aucune fabrique ne lève : elles renvoient `null`. Une règle illisible
 * n'élargit jamais l'accès (§4.5), et une exception sur le chemin de décision
 * d'un contrôle d'accès est une erreur fatale en puissance.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Rule;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Address\Ip_Number;

/**
 * Règle immuable, normalisée en intervalle.
 */
final class Rule {

	/**
	 * Nombre de segments d'une adresse IPv4 pointée.
	 *
	 * @var int
	 */
	private const IPV4_SEGMENTS = 4;

	/**
	 * Valeur maximale d'un segment IPv4.
	 *
	 * @var int
	 */
	private const IPV4_SEGMENT_MAX = 255;


	/**
	 * Borne basse de l'intervalle.
	 *
	 * @var Ip_Number
	 */
	private Ip_Number $low;

	/**
	 * Borne haute de l'intervalle.
	 *
	 * @var Ip_Number
	 */
	private Ip_Number $high;

	/**
	 * Texte d'origine de la règle, conservé pour le diagnostic (D-03).
	 *
	 * @var string
	 */
	private string $source;

	/**
	 * Construit une règle déjà normalisée et validée.
	 *
	 * @param Ip_Number $low    Borne basse.
	 * @param Ip_Number $high   Borne haute.
	 * @param string    $source Texte d'origine.
	 */
	private function __construct( Ip_Number $low, Ip_Number $high, string $source ) {
		$this->low    = $low;
		$this->high   = $high;
		$this->source = $source;
	}

	/**
	 * Compose une règle à partir de ses bornes, sous réserve de validité.
	 *
	 * Trois conditions, et chacune correspond à une contrainte du §4.2 : même
	 * famille (G-01), borne basse inférieure ou égale à la borne haute (G-02),
	 * et intervalle qui ne couvre pas l'ensemble des adresses (G-05).
	 *
	 * Le contrôle de G-05 porte sur l'**intervalle** et non sur l'écriture :
	 * la propriété dangereuse est sémantique, et un intervalle qui va d'un
	 * extrême à l'autre ouvre tout le contenu restreint, que l'auteur l'ait
	 * écrit `*`, `0.0.0.0/0` ou `0.0.0.0-255.255.255.255`.
	 *
	 * @param Ip_Number $low    Borne basse.
	 * @param Ip_Number $high   Borne haute.
	 * @param string    $source Texte d'origine.
	 * @return self|null
	 */
	private static function bounded( Ip_Number $low, Ip_Number $high, string $source ): ?self {
		if ( ! $low->is_same_family( $high ) ) {
			return null;
		}

		if ( $low->compare( $high ) > 0 ) {
			return null;
		}

		$family = $low->family();

		if ( $low->equals( Ip_Number::min_of( $family ) ) && $high->equals( Ip_Number::max_of( $family ) ) ) {
			return null;
		}

		return new self( $low, $high, $source );
	}

	/**
	 * Règle d'une seule adresse (FNC-13, FNC-18).
	 *
	 * @param Ip_Number $address Adresse littérale.
	 * @param string    $source  Texte d'origine.
	 * @return self
	 */
	public static function literal( Ip_Number $address, string $source ): self {
		return new self( $address, $address, $source );
	}

	/**
	 * Règle à segments jokers (FNC-14).
	 *
	 * Les jokers sont toujours IPv4 : le joker IPv6 est refusé par ADR-0005,
	 * faute de sémantique documentée. Chaque segment vaut un entier, ou `null`
	 * pour un joker ; les jokers prennent zéro en borne basse et le maximum en
	 * borne haute (§4.3).
	 *
	 * @param array<int, int|null> $segments Quatre segments, `null` pour un joker.
	 * @param string               $source   Texte d'origine.
	 * @return self|null
	 */
	public static function wildcard( array $segments, string $source ): ?self {
		if ( self::IPV4_SEGMENTS !== count( $segments ) ) {
			return null;
		}

		$low  = array();
		$high = array();

		foreach ( $segments as $segment ) {
			if ( null === $segment ) {
				$low[]  = 0;
				$high[] = self::IPV4_SEGMENT_MAX;

				continue;
			}

			if ( $segment < 0 || $segment > self::IPV4_SEGMENT_MAX ) {
				return null;
			}

			$low[]  = $segment;
			$high[] = $segment;
		}

		return self::bounded(
			Ip_Number::from_ipv4_bytes( $low ),
			Ip_Number::from_ipv4_bytes( $high ),
			$source
		);
	}

	/**
	 * Règle de bloc CIDR (FNC-16, FNC-18).
	 *
	 * La borne basse est l'adresse du réseau, la borne haute l'adresse de
	 * diffusion (§4.3). Les bits d'hôte de l'adresse fournie sont ignorés :
	 * `192.0.2.5/24` désigne sans ambiguïté le réseau `192.0.2.0/24`, et le
	 * refuser ferait perdre une saisie compréhensible.
	 *
	 * @param Ip_Number $address Adresse du bloc.
	 * @param int       $prefix  Longueur du préfixe.
	 * @param string    $source  Texte d'origine.
	 * @return self|null
	 */
	public static function cidr( Ip_Number $address, int $prefix, string $source ): ?self {
		$family = $address->family();

		if ( $prefix < 0 || $prefix > $family->max_prefix() ) {
			return null;
		}

		return self::bounded( $address->network( $prefix ), $address->broadcast( $prefix ), $source );
	}

	/**
	 * Règle de plage entre deux motifs (FNC-15, FNC-17).
	 *
	 * La borne basse est la borne **basse** du motif gauche, la borne haute la
	 * borne **haute** du motif droit. C'est la définition de FNC-17, et la
	 * seule lecture compatible avec le caractère inclusif des plages exigé par
	 * FNC-15 (§4.3, point ouvert O-06).
	 *
	 * @param self   $left   Motif gauche, déjà normalisé.
	 * @param self   $right  Motif droit, déjà normalisé.
	 * @param string $source Texte d'origine.
	 * @return self|null
	 */
	public static function range( self $left, self $right, string $source ): ?self {
		return self::bounded( $left->low, $right->high, $source );
	}

	/**
	 * Borne basse.
	 *
	 * @return Ip_Number
	 */
	public function low(): Ip_Number {
		return $this->low;
	}

	/**
	 * Borne haute.
	 *
	 * @return Ip_Number
	 */
	public function high(): Ip_Number {
		return $this->high;
	}

	/**
	 * Famille de la règle.
	 *
	 * @return Rule_Family
	 */
	public function family(): Rule_Family {
		return $this->low->family();
	}

	/**
	 * Texte d'origine de la règle.
	 *
	 * @return string
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * Indique si une adresse correspond à la règle.
	 *
	 * @param Ip_Number $address Adresse évaluée.
	 * @return bool
	 */
	public function matches( Ip_Number $address ): bool {
		return $address->is_between( $this->low, $this->high );
	}
}
