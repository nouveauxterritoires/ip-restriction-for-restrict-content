<?php
/**
 * Verdict sur les capacités constatées, sans dépendance à WordPress.
 *
 * Le rapport reçoit un catalogue et une liste d'observations, et n'observe
 * rien lui-même : c'est ce qui permet de l'exercer dans la suite `unit`, y
 * compris sur des combinaisons qu'aucune installation réelle ne présenterait.
 *
 * Trois états et non deux, pour la même raison qu'en §5.3 : une capacité que la
 * dépendance ne charge qu'en administration n'est pas absente sur une requête
 * de façade, elle n'y est pas observable. Les compter comme absentes
 * produirait, à chaque requête de façade, un avis annonçant la perte d'une
 * fonctionnalité intacte.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Rapport immuable sur les capacités.
 */
final class Capability_Report {

	/**
	 * La capacité est en mémoire.
	 *
	 * @var string
	 */
	public const PRESENT = 'present';

	/**
	 * La capacité est attendue et absente.
	 *
	 * @var string
	 */
	public const MISSING = 'missing';

	/**
	 * La capacité n'est pas observable dans le contexte de la requête.
	 *
	 * @var string
	 */
	public const UNOBSERVABLE = 'unobservable';

	/**
	 * Capacités du catalogue, dans l'ordre.
	 *
	 * @var list<Capability>
	 */
	private array $capabilities;

	/**
	 * État de chaque capacité, indexé par symbole.
	 *
	 * @var array<string, string>
	 */
	private array $states;

	/**
	 * Construit un rapport.
	 *
	 * @param Capability[]          $capabilities Capacités du catalogue.
	 * @param array<string, string> $states       État par symbole.
	 */
	private function __construct( array $capabilities, array $states ) {
		$this->capabilities = $capabilities;
		$this->states       = $states;
	}

	/**
	 * Qualifie un catalogue à partir d'observations.
	 *
	 * @param Capability[]        $capabilities Capacités attendues.
	 * @param array<string, bool> $observed     Présence constatée, par symbole.
	 * @param bool                $is_admin     La requête en cours est une requête d'administration.
	 * @return self
	 */
	public static function from_observations( array $capabilities, array $observed, bool $is_admin ): self {
		$states = array();

		foreach ( $capabilities as $capability ) {
			$symbol = $capability->symbol();

			if ( ! $capability->is_observable_in_admin_context( $is_admin ) ) {
				$states[ $symbol ] = self::UNOBSERVABLE;

				continue;
			}

			$states[ $symbol ] = true === ( $observed[ $symbol ] ?? false )
				? self::PRESENT
				: self::MISSING;
		}

		return new self( array_values( $capabilities ), $states );
	}

	/**
	 * Capacités du catalogue, dans l'ordre.
	 *
	 * @return list<Capability>
	 */
	public function capabilities(): array {
		return array_values( $this->capabilities );
	}

	/**
	 * État d'une capacité.
	 *
	 * @param Capability $capability Capacité interrogée.
	 * @return string Un des trois états déclarés par cette classe.
	 */
	public function state_of( Capability $capability ): string {
		return $this->states[ $capability->symbol() ] ?? self::MISSING;
	}

	/**
	 * Symboles attendus et absents.
	 *
	 * @return list<string>
	 */
	public function missing_symbols(): array {
		$missing = array();

		foreach ( $this->capabilities as $capability ) {
			if ( self::MISSING === $this->state_of( $capability ) ) {
				$missing[] = $capability->symbol();
			}
		}

		return $missing;
	}

	/**
	 * Indique si une fonctionnalité dispose de toutes ses capacités.
	 *
	 * Une fonctionnalité dont aucune capacité n'est observable est réputée
	 * disponible : rien ne permet d'affirmer le contraire, et affirmer la perte
	 * sur une absence d'information serait exactement l'erreur que E-05
	 * proscrit.
	 *
	 * @param string $feature Clé de la fonctionnalité.
	 * @return bool
	 */
	public function is_feature_available( string $feature ): bool {
		foreach ( $this->capabilities as $capability ) {
			if ( $capability->feature() !== $feature ) {
				continue;
			}

			if ( self::MISSING === $this->state_of( $capability ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Clés des fonctionnalités dont une capacité manque.
	 *
	 * @return list<string>
	 */
	public function unavailable_features(): array {
		$unavailable = array();

		foreach ( $this->capabilities as $capability ) {
			$feature = $capability->feature();

			if ( in_array( $feature, $unavailable, true ) ) {
				continue;
			}

			if ( ! $this->is_feature_available( $feature ) ) {
				$unavailable[] = $feature;
			}
		}

		return $unavailable;
	}

	/**
	 * Indique si toutes les capacités observables sont présentes.
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		return array() === $this->missing_symbols();
	}
}
