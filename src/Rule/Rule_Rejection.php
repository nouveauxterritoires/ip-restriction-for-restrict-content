<?php
/**
 * Une entrée refusée, avec son numéro de ligne et le motif du refus.
 *
 * FNC-22 exige que l'administrateur sache **quelle** ligne a été ignorée et
 * **pourquoi**. Un analyseur qui rejetterait tout avec le même motif
 * satisferait FNC-20 et rendrait FNC-22 inutilisable : « ligne 11 ignorée :
 * syntaxe » n'apprend rien à qui doit corriger.
 *
 * Les motifs sont des clés, pas des phrases : composer un libellé exigerait les
 * fonctions de traduction de WordPress, dont cette couche ne dépend pas. La
 * couche d'application traduit.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Rule;

defined( 'ABSPATH' ) || exit;

/**
 * Motif de refus d'une entrée.
 */
final class Rule_Rejection {

	/**
	 * L'entrée ne relève d'aucune forme de la grammaire (FNC-20).
	 *
	 * @var string
	 */
	public const SYNTAX = 'syntax';

	/**
	 * Les deux bornes d'une plage ne relèvent pas de la même famille (G-01).
	 *
	 * @var string
	 */
	public const MIXED_FAMILIES = 'mixed_families';

	/**
	 * La borne basse d'une plage est supérieure à sa borne haute (G-02).
	 *
	 * @var string
	 */
	public const INVERTED_RANGE = 'inverted_range';

	/**
	 * Le préfixe d'un CIDR sort des bornes de sa famille (G-03).
	 *
	 * @var string
	 */
	public const PREFIX_OUT_OF_RANGE = 'prefix_out_of_range';

	/**
	 * Un joker précède un segment littéral (G-04).
	 *
	 * @var string
	 */
	public const WILDCARD_BEFORE_LITERAL = 'wildcard_before_literal';

	/**
	 * La règle désignerait l'ensemble des adresses (G-05, SEC-12).
	 *
	 * @var string
	 */
	public const COVERS_EVERY_ADDRESS = 'covers_every_address';

	/**
	 * Un CIDR porte un joker (G-06).
	 *
	 * @var string
	 */
	public const WILDCARD_IN_CIDR = 'wildcard_in_cidr';

	/**
	 * L'entrée est un nom d'hôte et non une adresse (G-07).
	 *
	 * @var string
	 */
	public const HOSTNAME = 'hostname';

	/**
	 * Un joker porte sur une adresse IPv6 (ADR-0005).
	 *
	 * @var string
	 */
	public const IPV6_WILDCARD = 'ipv6_wildcard';

	/**
	 * Un octet porte un zéro de tête, écriture ambiguë (FNC-20).
	 *
	 * @var string
	 */
	public const AMBIGUOUS_OCTET = 'ambiguous_octet';

	/**
	 * Texte de l'entrée refusée.
	 *
	 * @var string
	 */
	private string $line;

	/**
	 * Numéro de la ligne dans le texte d'origine, à partir de 1.
	 *
	 * @var int
	 */
	private int $number;

	/**
	 * Motif du refus.
	 *
	 * @var string
	 */
	private string $reason;

	/**
	 * Construit un refus.
	 *
	 * @param string $line   Texte de l'entrée.
	 * @param int    $number Numéro de ligne.
	 * @param string $reason Motif, parmi les constantes de cette classe.
	 */
	private function __construct( string $line, int $number, string $reason ) {
		$this->line   = $line;
		$this->number = $number;
		$this->reason = $reason;
	}

	/**
	 * Refuse une entrée pour un motif donné.
	 *
	 * @param string $line   Texte de l'entrée.
	 * @param int    $number Numéro de ligne.
	 * @param string $reason Motif, parmi les constantes de cette classe.
	 * @return self
	 */
	public static function because( string $line, int $number, string $reason ): self {
		return new self( $line, $number, $reason );
	}

	/**
	 * Texte de l'entrée refusée.
	 *
	 * @return string
	 */
	public function line(): string {
		return $this->line;
	}

	/**
	 * Numéro de la ligne dans le texte d'origine.
	 *
	 * @return int
	 */
	public function number(): int {
		return $this->number;
	}

	/**
	 * Motif du refus.
	 *
	 * @return string
	 */
	public function reason(): string {
		return $this->reason;
	}
}
