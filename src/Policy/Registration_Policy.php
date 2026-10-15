<?php
/**
 * Politique de blocage d'inscription — FNC-07 à FNC-09, FNC-12.
 *
 * Classe distincte de `Access_Policy`, et c'est A-01 : les deux listes ne
 * partagent ni stockage, ni cache, ni chemin de décision. Elles se ressemblent
 * — deux champs de texte, la même grammaire, le même moteur — et n'ont pas du
 * tout les mêmes conséquences en cas d'erreur. Une classe commune paramétrée
 * par un nom de liste serait le chemin par lequel une confusion de champ
 * transforme un blocage en autorisation.
 *
 * Elle ne reçoit que la liste de blocage, et ne connaît donc pas la liste
 * d'autorisation. FNC-12 est obtenu par la même économie : la liste de blocage
 * n'existe qu'à la portée globale, et cette classe n'a aucun moyen d'en
 * recevoir une autre.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Policy;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Address\Ip_Number;
use Ip_Restriction_For_Restrict_Content\Rule\Matcher;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Set;

/**
 * Décision de blocage à partir de la liste de blocage.
 */
final class Registration_Policy {

	/**
	 * Décide du blocage pour une adresse.
	 *
	 * Une adresse indéterminable n'est pas bloquée, et c'est la face
	 * inconfortable de la fermeture sur l'inconnu (§4.5) : la défaillance
	 * laisse passer une inscription indésirable, et personne ne s'en aperçoit.
	 * Le §4.6 l'assume, parce que l'alternative — bloquer sur une adresse
	 * inconnue — refuserait des inscriptions légitimes en masse dès qu'un
	 * proxy est mal déclaré.
	 *
	 * @param Ip_Number|null $address    Adresse évaluée, ou null si indéterminable.
	 * @param Rule_Set       $block_list Liste de blocage globale.
	 * @return Registration_Decision
	 */
	public static function decide( ?Ip_Number $address, Rule_Set $block_list ): Registration_Decision {
		$match = Matcher::match( $address, $block_list );

		if ( $match->is_match() ) {
			return Registration_Decision::blocked_with( $match->rule() );
		}

		return Registration_Decision::allowed();
	}
}
