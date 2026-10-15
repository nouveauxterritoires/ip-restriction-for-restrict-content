<?php
/**
 * Provenance de l'adresse retenue, sans dépendance à WordPress.
 *
 * R-07 exige que l'adresse porte sa source : l'écran de diagnostic de FNC-29
 * l'affiche, et un test peut l'asserter. Sans elle, un administrateur ne peut
 * pas distinguer « ma règle est fausse » de « l'adresse évaluée n'est pas celle
 * que je crois », qui est la panne la plus fréquente et la plus difficile à
 * observer derrière un proxy.
 *
 * Trois cas et non deux : une adresse indéterminable est une valeur, pas une
 * absence, et sa provenance l'est aussi. Rendre `source()` nullable aurait
 * obligé chaque appelant à se souvenir de tester, et le premier oubli serait
 * une erreur fatale sur le chemin d'un visiteur derrière un proxy mal
 * configuré — c'est-à-dire le chemin rare, celui qu'on ne voit qu'en
 * production.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Address;

defined( 'ABSPATH' ) || exit;

/**
 * Provenances possibles de l'adresse évaluée.
 */
enum Address_Source: string {

	/**
	 * `REMOTE_ADDR`, renseignée par le serveur web depuis la connexion TCP.
	 */
	case CONNECTION = 'connection';

	/**
	 * Un en-tête de transfert, lu derrière un proxy de confiance déclaré.
	 */
	case FORWARDED_HEADER = 'forwarded_header';

	/**
	 * Aucune adresse n'a pu être déterminée.
	 */
	case UNDETERMINED = 'undetermined';
}
