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

use Ip_Restriction_For_Restrict_Content\Admin\Requirements_Notice;
use Ip_Restriction_For_Restrict_Content\Domain\Requirements;

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
	 * Construit une instance à partir d'un verdict déjà rendu.
	 *
	 * @param Requirements $requirements Verdict sur les prérequis.
	 */
	private function __construct( Requirements $requirements ) {
		$this->requirements = $requirements;
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
		return new self(
			Requirements::evaluate(
				PHP_VERSION,
				(string) get_bloginfo( 'version' ),
				self::membership_dependency_present(),
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
					'dependency' => __( 'Restrict Content ou Restrict Content Pro, actif', 'ip-restriction-for-restrict-content' ),
				)
			)
		);
	}

	/**
	 * Détecte la dépendance d'adhésions par capacité.
	 *
	 * Ni le nom du plugin ni son numéro de version ne sont interrogés : les deux
	 * variantes partagent le même code et se renomment au fil des versions. Ce
	 * qui compte est la présence effective des fonctions que ce plugin appelle.
	 *
	 * @return bool
	 */
	private static function membership_dependency_present(): bool {
		return function_exists( 'rcp_get_membership_levels' );
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
		if ( ! $this->is_satisfied() ) {
			( new Requirements_Notice( $this->missing_requirements() ) )->register();

			return;
		}

		load_plugin_textdomain( 'ip-restriction-for-restrict-content', false, dirname( plugin_basename( RCIPR_PLUGIN_FILE ) ) . '/languages' );
	}
}
