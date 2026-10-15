<?php
/**
 * Point d'entrée du plugin : constat de l'environnement, puis enregistrement.
 *
 * La classe est délibérément stupide. Elle ne décide rien : elle constate
 * l'environnement et délègue le verdict à `Domain\Requirements`, qui est pure.
 * C'est la séparation posée par les conventions : constater est impur, décider
 * ne l'est pas.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Admin\Capability_Status_Page;
use Ip_Restriction_For_Restrict_Content\Admin\Dependency_Labels;
use Ip_Restriction_For_Restrict_Content\Admin\Rejection_Notice;
use Ip_Restriction_For_Restrict_Content\Admin\Requirements_Notice;
use Ip_Restriction_For_Restrict_Content\Admin\Settings_Page;
use Ip_Restriction_For_Restrict_Content\Domain\Capability_Report;
use Ip_Restriction_For_Restrict_Content\Domain\Dependency_Diagnosis;
use Ip_Restriction_For_Restrict_Content\Domain\Requirements;
use Ip_Restriction_For_Restrict_Content\Integration\Cache_Guard;
use Ip_Restriction_For_Restrict_Content\Integration\Capability_Probe;
use Ip_Restriction_For_Restrict_Content\Integration\Content_Access;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;

/**
 * Amorçage conditionnel du plugin.
 */
final class Plugin {

	/**
	 * Verdict sur les prérequis d'exécution.
	 *
	 * @var Requirements
	 */
	private Requirements $requirements;

	/**
	 * Sonde des capacités, conclue une fois pour la requête.
	 *
	 * @var Capability_Probe
	 */
	private Capability_Probe $probe;

	/**
	 * Construit une instance à partir d'un constat déjà fait.
	 *
	 * @param Requirements     $requirements Verdict sur les prérequis.
	 * @param Capability_Probe $probe        Sonde conclue sur cette requête.
	 */
	private function __construct( Requirements $requirements, Capability_Probe $probe ) {
		$this->requirements = $requirements;
		$this->probe        = $probe;
	}

	/**
	 * Constate l'environnement d'exécution.
	 *
	 * Les versions de PHP et de WordPress sont comparées aux planchers déclarés
	 * dans l'en-tête du plugin (ADR-0002) ; la présence de la dépendance
	 * d'adhésions est établie par capacité, jamais par numéro de version ni par
	 * nom de plugin (conventions §2).
	 *
	 * @return self
	 */
	public static function from_environment(): self {
		$probe      = Capability_Probe::observe();
		$dependency = $probe->dependency();

		return new self(
			Requirements::evaluate(
				PHP_VERSION,
				(string) get_bloginfo( 'version' ),
				$dependency->is_usable(),
				RCIPR_MIN_PHP,
				RCIPR_MIN_WP,
				array(
					'php'        => sprintf(
						/* translators: 1: version de PHP requise, 2: version installée. */
						__( 'PHP %1$s ou plus récent (version installée : %2$s)', 'ip-restriction-for-restrict-content' ),
						RCIPR_MIN_PHP,
						PHP_VERSION
					),
					'wp'         => sprintf(
						/* translators: 1: version de WordPress requise, 2: version installée. */
						__( 'WordPress %1$s ou plus récent (version installée : %2$s)', 'ip-restriction-for-restrict-content' ),
						RCIPR_MIN_WP,
						(string) get_bloginfo( 'version' )
					),
					'dependency' => Dependency_Labels::missing_requirement( $dependency ),
				)
			),
			$probe
		);
	}

	/**
	 * Rapport sur les capacités constatées sur cette requête.
	 *
	 * @return Capability_Report
	 */
	public function capability_report(): Capability_Report {
		return $this->probe->report();
	}

	/**
	 * Diagnostic de la dépendance constaté sur cette requête.
	 *
	 * @return Dependency_Diagnosis
	 */
	public function dependency_diagnosis(): Dependency_Diagnosis {
		return $this->probe->dependency();
	}

	/**
	 * Prérequis absents.
	 *
	 * @return list<string>
	 */
	public function missing_requirements(): array {
		return $this->requirements->missing();
	}

	/**
	 * Indique si l'environnement permet d'enregistrer les fonctionnalités.
	 *
	 * @return bool
	 */
	public function is_satisfied(): bool {
		return $this->requirements->is_satisfied();
	}

	/**
	 * Enregistre le plugin, ou l'avis qui explique pourquoi il ne s'enregistre pas.
	 *
	 * Échouer proprement est une exigence, pas une politesse : un prérequis
	 * absent produit un avis d'administration nommant précisément ce qui manque,
	 * jamais une erreur fatale (conventions §2).
	 *
	 * @return void
	 */
	public function register(): void {
		load_plugin_textdomain( 'ip-restriction-for-restrict-content', false, dirname( plugin_basename( RCIPR_PLUGIN_FILE ) ) . '/languages' );

		/*
		 * L'écran d'état est enregistré dans tous les cas, y compris quand les
		 * prérequis manquent : c'est exactement la situation où il sert. Il ne
		 * dépend d'aucune capacité de la dépendance, et son menu est celui de
		 * WordPress, qui existe toujours.
		 */
		( new Capability_Status_Page( $this->capability_report(), $this->dependency_diagnosis() ) )->register();

		if ( ! $this->is_satisfied() ) {
			( new Requirements_Notice( $this->missing_requirements(), $this->remediation_hints() ) )->register();

			return;
		}

		/*
		 * À partir d'ici, la dépendance est utilisable : l'écran de réglages
		 * peut s'accrocher à son menu et exiger sa capacité. L'avis d'entrées
		 * refusées partage le même dépôt, parce qu'il se recalcule depuis les
		 * réglages stockés plutôt que d'être mémorisé à l'enregistrement.
		 */
		$repository = new Settings_Repository();

		( new Settings_Page( $repository ) )->register();
		( new Rejection_Notice( $repository ) )->register();

		/*
		 * La décision d'accès est branchée en dernier, et sur la seule
		 * accroche que la dépendance applique sur les trois chemins de sa
		 * décision (§5.1). Le marquage de non-cachabilité partage l'instance
		 * du branchement : une requête ne doit être marquée qu'une fois, quel
		 * que soit le nombre de contenus élargis (SEC-14).
		 */
		( new Content_Access( $repository, new Cache_Guard() ) )->register();
	}

	/**
	 * Indications d'action à joindre à l'avis de prérequis.
	 *
	 * @return list<string>
	 */
	private function remediation_hints(): array {
		if ( ! $this->dependency_diagnosis()->is_legacy_branch() ) {
			return array();
		}

		return array( Dependency_Labels::remediation() );
	}
}
