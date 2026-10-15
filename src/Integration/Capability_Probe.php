<?php
/**
 * Sonde des capacités de la dépendance — une contrainte de conception.
 *
 * Cette classe est celle dont on aura le plus envie de « corriger » le
 * comportement, et il faut donc dire pourquoi elle est écrite ainsi.
 *
 * Elle n'écrit rien : ni option, ni métadonnée, ni transient, ni fichier. Elle
 * ne conclut rien au crochet d'activation. Elle sonde à chaque requête, parce
 * que la branche de code chargée par la dépendance est un **état mutable**, que
 * l'administrateur retourne depuis un écran, sans désactiver ni mettre à jour
 * quoi que ce soit (`restrictcontent.php:676`, JE-003). Une présence constatée
 * à une requête peut être fausse à la suivante.
 *
 * Un résultat persisté serait faux dès la première bascule, et faux
 * silencieusement : le plugin continuerait d'enregistrer ses filtres sur une
 * interface absente, ce qui produit une erreur fatale au premier appel —
 * exactement ce que le cahier des charges §5.3 existe pour empêcher.
 * L'économie d'une poignée d'appels à `function_exists()` se paierait en écran
 * blanc chez quelqu'un qui a cliqué sur un bouton documenté.
 *
 * La mémorisation est en revanche portée par l'**instance** : une sonde conclut
 * une fois et transporte son rapport en mémoire. La frontière est la requête,
 * et elle n'est pas négociable (P-01 à P-04, cahier des charges §5.2.1).
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Integration;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Domain\Capability;
use Ip_Restriction_For_Restrict_Content\Domain\Capability_Catalogue;
use Ip_Restriction_For_Restrict_Content\Domain\Capability_Report;
use Ip_Restriction_For_Restrict_Content\Domain\Dependency_Diagnosis;

/**
 * Constat des capacités en mémoire.
 */
final class Capability_Probe {

	/**
	 * Rapport constaté à la construction.
	 *
	 * @var Capability_Report
	 */
	private Capability_Report $report;

	/**
	 * Diagnostic de la dépendance constaté à la construction.
	 *
	 * @var Dependency_Diagnosis
	 */
	private Dependency_Diagnosis $dependency;

	/**
	 * Construit une sonde à partir d'un constat déjà fait.
	 *
	 * @param Capability_Report    $report     Rapport sur les capacités.
	 * @param Dependency_Diagnosis $dependency Diagnostic de la dépendance.
	 */
	private function __construct( Capability_Report $report, Dependency_Diagnosis $dependency ) {
		$this->report     = $report;
		$this->dependency = $dependency;
	}

	/**
	 * Constate les capacités présentes dans le processus en cours.
	 *
	 * Le contexte d'administration est reçu en argument plutôt que lu ici :
	 * `is_admin()` est une information de requête, et la passer explicitement
	 * rend la sonde exerçable dans les deux contextes sans bricoler l'état
	 * global de WordPress.
	 *
	 * @param bool|null $is_admin Contexte d'administration, ou null pour le constater.
	 * @return self
	 */
	public static function observe( ?bool $is_admin = null ): self {
		$capabilities = Capability_Catalogue::declared();
		$observed     = array();

		foreach ( $capabilities as $capability ) {
			$observed[ $capability->symbol() ] = self::symbol_exists( $capability );
		}

		return new self(
			Capability_Report::from_observations(
				$capabilities,
				$observed,
				null === $is_admin ? is_admin() : $is_admin
			),
			Dependency_Diagnosis::from_observation(
				function_exists( Capability_Catalogue::API_MARKER ),
				function_exists( Capability_Catalogue::BOOTSTRAP_MARKER )
			)
		);
	}

	/**
	 * Constate la présence d'un symbole.
	 *
	 * @param Capability $capability Capacité attendue.
	 * @return bool
	 */
	private static function symbol_exists( Capability $capability ): bool {
		if ( Capability::CLASS_SYMBOL === $capability->kind() ) {
			return class_exists( $capability->symbol() );
		}

		return function_exists( $capability->symbol() );
	}

	/**
	 * Rapport sur les capacités.
	 *
	 * @return Capability_Report
	 */
	public function report(): Capability_Report {
		return $this->report;
	}

	/**
	 * Diagnostic de la dépendance.
	 *
	 * @return Dependency_Diagnosis
	 */
	public function dependency(): Dependency_Diagnosis {
		return $this->dependency;
	}
}
