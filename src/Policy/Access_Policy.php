<?php
/**
 * Politique d'accès — FNC-01, FNC-05, FNC-06, FNC-31.
 *
 * Cette classe ne reçoit ni utilisateur, ni adhésion, ni contenu : elle ne
 * **peut** donc pas en dépendre. C'est la forme la plus solide que puisse
 * prendre FNC-05 — « l'accès accordé ne dépend ni de l'état de connexion du
 * visiteur ni de l'existence d'un compte » — et c'est aussi ce qui règle par
 * avance la question de l'adhésion expirée : une adhésion expirée n'est pas un
 * cas particulier, c'est l'absence d'adhésion, et la politique n'en sait rien
 * (V-01).
 *
 * Les portées se cumulent par disjonction (FNC-06) : la première qui accorde
 * l'emporte, et la décision porte laquelle. À ce lot, seule la portée globale
 * existe ; les lots 7 et 8 ajouteront les deux autres, et la non-transitivité
 * de FNC-04 se vérifiera sur la portée rapportée.
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
 * Décision d'accès à partir des listes applicables.
 */
final class Access_Policy {

	/**
	 * Décide de l'accès pour une adresse.
	 *
	 * @param Ip_Number|null $address     Adresse évaluée, ou null si indéterminable.
	 * @param Rule_Set       $global_list Liste d'autorisation globale.
	 * @return Access_Decision
	 */
	public static function decide( ?Ip_Number $address, Rule_Set $global_list ): Access_Decision {
		$match = Matcher::match( $address, $global_list );

		if ( $match->is_match() ) {
			return Access_Decision::granted_by( Scope::GLOBAL_LIST, $match->rule() );
		}

		return Access_Decision::not_handled();
	}
}
