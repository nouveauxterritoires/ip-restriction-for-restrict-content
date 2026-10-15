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
 * l'emporte, et la décision porte laquelle. La portée globale est consultée
 * d'abord — l'ordre ne change pas le fait d'accorder, la disjonction étant
 * commutative, mais il change la portée **rapportée**, et la plus large est
 * celle qui explique l'accès.
 *
 * **FNC-04 — la non-transitivité — tient à ce que cette méthode ne reçoit
 * pas.** Les listes qu'on lui passe sont celles des niveaux qui restreignent le
 * contenu demandé et celle de ce contenu, et elles seules : une autorisation
 * posée sur un autre niveau ou sur un autre contenu n'est pas même consultée.
 * Ce n'est pas une comparaison qui échoue, c'est une liste qui n'entre pas dans
 * le calcul. L'implémentation naïve — une seule liste consultée quelle que soit
 * la restriction rencontrée — violerait FNC-04 silencieusement, et
 * transformerait chaque autorisation de niveau ou de contenu en autorisation
 * globale.
 *
 * Les portées sont consultées de la plus large à la plus étroite : globale,
 * niveau, contenu. C'est sans effet sur le fait d'accorder, mais la portée
 * rapportée est celle qu'il faudra retirer pour que l'accès cesse.
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
	 * @param Ip_Number|null       $address     Adresse évaluée, ou null si indéterminable.
	 * @param Rule_Set             $global_list Liste d'autorisation globale.
	 * @param array<int, Rule_Set> $level_lists Listes des niveaux qui restreignent le contenu demandé, indexées par identifiant de niveau.
	 * @param Rule_Set             $post_list   Liste du contenu demandé.
	 * @return Access_Decision
	 */
	public static function decide(
		?Ip_Number $address,
		Rule_Set $global_list,
		array $level_lists,
		Rule_Set $post_list
	): Access_Decision {
		$global = Matcher::match( $address, $global_list );

		if ( $global->is_match() ) {
			return Access_Decision::granted_by( Scope::GLOBAL_LIST, $global->rule() );
		}

		foreach ( $level_lists as $level_id => $level_list ) {
			$match = Matcher::match( $address, $level_list );

			if ( $match->is_match() ) {
				return Access_Decision::granted_by( Scope::MEMBERSHIP_LEVEL, $match->rule(), (int) $level_id );
			}
		}

		$post = Matcher::match( $address, $post_list );

		if ( $post->is_match() ) {
			return Access_Decision::granted_by( Scope::POST, $post->rule() );
		}

		return Access_Decision::not_handled();
	}
}
