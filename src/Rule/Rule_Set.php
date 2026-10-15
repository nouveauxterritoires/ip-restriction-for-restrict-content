<?php
/**
 * Une liste analysée : les règles valides, et les entrées refusées.
 *
 * Les deux ensembles coexistent, et c'est D-02 : une règle invalide ne
 * correspond à rien et ne rend pas la liste invalide. Les refus sont conservés
 * parce que FNC-22 doit pouvoir les signaler à l'administrateur, avec leur
 * numéro de ligne.
 *
 * L'ordre d'origine est conservé (FNC-28), pour que le signalement renvoie vers
 * la bonne ligne du champ et que la liste se relise comme elle a été écrite.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Rule;

defined( 'ABSPATH' ) || exit;

/**
 * Ensemble immuable de règles et de refus.
 */
final class Rule_Set {

	/**
	 * Règles valides, dans l'ordre d'origine.
	 *
	 * @var list<Rule>
	 */
	private array $rules;

	/**
	 * Entrées refusées, dans l'ordre d'origine.
	 *
	 * @var list<Rule_Rejection>
	 */
	private array $rejections;

	/**
	 * Construit un ensemble.
	 *
	 * @param Rule[]           $rules      Règles valides.
	 * @param Rule_Rejection[] $rejections Entrées refusées.
	 */
	private function __construct( array $rules, array $rejections ) {
		$this->rules      = array_values( $rules );
		$this->rejections = array_values( $rejections );
	}

	/**
	 * Compose un ensemble depuis ses deux listes.
	 *
	 * @param Rule[]           $rules      Règles valides.
	 * @param Rule_Rejection[] $rejections Entrées refusées.
	 * @return self
	 */
	public static function of( array $rules, array $rejections ): self {
		return new self( $rules, $rejections );
	}

	/**
	 * Analyse un texte de réglage.
	 *
	 * @param string $text Texte du réglage, une entrée par ligne.
	 * @return self
	 */
	public static function from_text( string $text ): self {
		return Rule_Parser::parse( $text );
	}

	/**
	 * Ensemble vide.
	 *
	 * @return self
	 */
	public static function empty(): self {
		return new self( array(), array() );
	}

	/**
	 * Règles valides.
	 *
	 * @return list<Rule>
	 */
	public function rules(): array {
		return array_values( $this->rules );
	}

	/**
	 * Entrées refusées.
	 *
	 * @return list<Rule_Rejection>
	 */
	public function rejections(): array {
		return array_values( $this->rejections );
	}

	/**
	 * Indique si l'ensemble ne porte aucune règle valide.
	 *
	 * Une liste dont toute entrée est refusée est vide au sens de la décision
	 * (FNC-23) : elle ne correspond jamais, exactement comme une liste vide.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return array() === $this->rules;
	}
}
