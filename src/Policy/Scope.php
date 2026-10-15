<?php
/**
 * Portée d'une autorisation, sans dépendance à WordPress.
 *
 * Les trois portées du §3.1 ne sont **pas transitives** : une autorisation
 * posée sur le niveau « Argent » ne donne aucun accès à un contenu réservé au
 * niveau « Or » (FNC-04). L'énumération existe pour que la décision porte la
 * portée qui l'a accordée, et donc pour que la non-transitivité se vérifie
 * autrement qu'en inspectant un booléen.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Policy;

defined( 'ABSPATH' ) || exit;

/**
 * Les trois portées d'une liste d'autorisation.
 */
enum Scope: string {

	/**
	 * Liste globale : tout contenu restreint du site (FNC-01).
	 */
	case GLOBAL_LIST = 'global';

	/**
	 * Liste d'un niveau d'adhésion : les seuls contenus restreints à ce
	 * niveau (FNC-02, FNC-04).
	 */
	case MEMBERSHIP_LEVEL = 'membership_level';

	/**
	 * Liste d'un contenu : ce seul contenu (FNC-03).
	 */
	case POST = 'post';
}
