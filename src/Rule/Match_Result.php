<?php
/**
 * Résultat d'une correspondance : s'il y en a une, et par quelle règle.
 *
 * La règle responsable est portée par le résultat parce que D-03 l'exige pour
 * le diagnostic : un administrateur qui constate un accès inattendu doit
 * pouvoir savoir **quelle** ligne de sa liste l'a produit. Sans cela, une liste
 * de cinquante entrées se débogue par dichotomie.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Rule;

defined( 'ABSPATH' ) || exit;

/**
 * Résultat immuable d'une correspondance.
 */
final class Match_Result {

	/**
	 * Règle responsable de la correspondance, s'il y en a une.
	 *
	 * @var Rule|null
	 */
	private ?Rule $rule;

	/**
	 * Construit un résultat.
	 *
	 * @param Rule|null $rule Règle responsable, ou null.
	 */
	private function __construct( ?Rule $rule ) {
		$this->rule = $rule;
	}

	/**
	 * Correspondance, par une règle donnée.
	 *
	 * @param Rule $rule Règle responsable.
	 * @return self
	 */
	public static function matched( Rule $rule ): self {
		return new self( $rule );
	}

	/**
	 * Absence de correspondance.
	 *
	 * @return self
	 */
	public static function no_match(): self {
		return new self( null );
	}

	/**
	 * Indique s'il y a correspondance.
	 *
	 * @return bool
	 */
	public function is_match(): bool {
		return null !== $this->rule;
	}

	/**
	 * Règle responsable de la correspondance.
	 *
	 * @return Rule|null
	 */
	public function rule(): ?Rule {
		return $this->rule;
	}
}
