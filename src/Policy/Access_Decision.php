<?php
/**
 * Décision d'accès : accordé par une portée, ou non traité.
 *
 * Le plugin ne décide jamais de **refuser** un accès : il accorde, ou il ne se
 * prononce pas et laisse la dépendance décider (FNC-31). Nommer le second cas
 * « refusé » induirait en erreur le lecteur et, tôt ou tard, l'auteur d'un
 * branchement qui traduirait ce refus en blocage réel — et le plugin se
 * mettrait alors à retirer des accès que la dépendance accordait.
 *
 * La décision porte la portée qui l'a accordée et la règle responsable : la
 * première rend la non-transitivité de FNC-04 vérifiable, la seconde rend un
 * accès inattendu diagnosticable (D-03).
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Policy;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Rule\Rule;

/**
 * Décision immuable.
 */
final class Access_Decision {

	/**
	 * Portée qui a accordé l'accès, ou null.
	 *
	 * @var Scope|null
	 */
	private ?Scope $scope;

	/**
	 * Règle responsable de l'octroi, ou null.
	 *
	 * @var Rule|null
	 */
	private ?Rule $rule;

	/**
	 * Construit une décision.
	 *
	 * @param Scope|null $scope Portée ayant accordé l'accès.
	 * @param Rule|null  $rule  Règle responsable.
	 */
	private function __construct( ?Scope $scope, ?Rule $rule ) {
		$this->scope = $scope;
		$this->rule  = $rule;
	}

	/**
	 * Accès accordé par une portée.
	 *
	 * @param Scope $scope Portée ayant accordé l'accès.
	 * @param Rule  $rule  Règle responsable.
	 * @return self
	 */
	public static function granted_by( Scope $scope, Rule $rule ): self {
		return new self( $scope, $rule );
	}

	/**
	 * Le plugin ne se prononce pas.
	 *
	 * @return self
	 */
	public static function not_handled(): self {
		return new self( null, null );
	}

	/**
	 * Indique si l'accès est accordé.
	 *
	 * @return bool
	 */
	public function is_granted(): bool {
		return null !== $this->scope;
	}

	/**
	 * Indique si le plugin ne se prononce pas.
	 *
	 * @return bool
	 */
	public function is_not_handled(): bool {
		return null === $this->scope;
	}

	/**
	 * Portée ayant accordé l'accès.
	 *
	 * @return Scope|null
	 */
	public function scope(): ?Scope {
		return $this->scope;
	}

	/**
	 * Règle responsable de l'octroi.
	 *
	 * @return Rule|null
	 */
	public function rule(): ?Rule {
		return $this->rule;
	}
}
