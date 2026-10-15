<?php
/**
 * Décision de blocage d'inscription.
 *
 * Classe distincte de `Access_Decision`, et pas par symétrie de style : A-01
 * interdit tout chemin commun entre les deux listes. Une décision d'accès et
 * une décision de blocage n'ont ni le même effet, ni la même gravité en cas
 * d'erreur, et un objet commun finirait par être transporté de l'un vers
 * l'autre.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Policy;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Rule\Rule;

/**
 * Décision immuable sur une inscription.
 */
final class Registration_Decision {

	/**
	 * Règle qui a produit le blocage, ou null.
	 *
	 * @var Rule|null
	 */
	private ?Rule $rule;

	/**
	 * Construit une décision.
	 *
	 * @param Rule|null $rule Règle responsable du blocage.
	 */
	private function __construct( ?Rule $rule ) {
		$this->rule = $rule;
	}

	/**
	 * Inscription bloquée par une règle.
	 *
	 * @param Rule $rule Règle responsable.
	 * @return self
	 */
	public static function blocked_with( Rule $rule ): self {
		return new self( $rule );
	}

	/**
	 * Inscription non bloquée.
	 *
	 * @return self
	 */
	public static function allowed(): self {
		return new self( null );
	}

	/**
	 * Indique si l'inscription est bloquée.
	 *
	 * @return bool
	 */
	public function is_blocked(): bool {
		return null !== $this->rule;
	}

	/**
	 * Règle responsable du blocage.
	 *
	 * @return Rule|null
	 */
	public function rule(): ?Rule {
		return $this->rule;
	}
}
