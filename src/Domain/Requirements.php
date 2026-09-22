<?php
/**
 * Règle de prérequis, sans dépendance à WordPress.
 *
 * Cette classe ne lit rien : ni base, ni option, ni constante, ni version
 * installée. Elle reçoit un état et renvoie un verdict. C'est ce qui permet de
 * l'exercer entièrement dans la suite `unit`, y compris les combinaisons qu'un
 * environnement réel ne présenterait jamais — un WordPress à jour sur un PHP
 * périmé, par exemple.
 *
 * `Plugin::from_environment()` est l'adaptateur qui constate l'environnement et
 * délègue ici.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Verdict immuable sur les prérequis d'exécution.
 */
final class Requirements {

	/**
	 * Libellés des prérequis absents, dans l'ordre où ils sont évalués.
	 *
	 * @var list<string>
	 */
	private array $missing;

	/**
	 * Construit un verdict.
	 *
	 * Le constructeur est privé : un verdict ne se fabrique pas, il se constate.
	 * Seule `evaluate()` y conduit.
	 *
	 * @param string[] $missing Libellés des prérequis absents.
	 */
	private function __construct( array $missing ) {
		$this->missing = array_values( array_map( 'strval', $missing ) );
	}

	/**
	 * Évalue les prérequis à partir d'un état constaté.
	 *
	 * Les libellés sont fournis par l'appelant plutôt que construits ici : les
	 * composer exigerait les fonctions de traduction de WordPress, et cette
	 * classe n'en dépend pas. L'appelant sait traduire ; la règle sait décider.
	 *
	 * @param string   $php_version        Version de PHP constatée.
	 * @param string   $wp_version         Version de WordPress constatée.
	 * @param bool     $dependency_present La dépendance d'adhésions est détectée.
	 * @param string   $min_php            Version de PHP exigée.
	 * @param string   $min_wp             Version de WordPress exigée.
	 * @param string[] $labels             Libellés indexés par `php`, `wp` et `dependency`.
	 * @return self
	 */
	public static function evaluate(
		string $php_version,
		string $wp_version,
		bool $dependency_present,
		string $min_php,
		string $min_wp,
		array $labels
	): self {
		$missing = array();

		if ( version_compare( $php_version, $min_php, '<' ) ) {
			$missing[] = (string) ( $labels['php'] ?? 'php' );
		}

		if ( version_compare( $wp_version, $min_wp, '<' ) ) {
			$missing[] = (string) ( $labels['wp'] ?? 'wp' );
		}

		if ( ! $dependency_present ) {
			$missing[] = (string) ( $labels['dependency'] ?? 'dependency' );
		}

		return new self( $missing );
	}

	/**
	 * Libellés des prérequis absents.
	 *
	 * Une copie est renvoyée : l'appelant ne doit pas pouvoir modifier le
	 * verdict après coup.
	 *
	 * @return list<string>
	 */
	public function missing(): array {
		return array_values( $this->missing );
	}

	/**
	 * Indique si tous les prérequis sont satisfaits.
	 *
	 * @return bool
	 */
	public function is_satisfied(): bool {
		return array() === $this->missing;
	}
}
