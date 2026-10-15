<?php
/**
 * Décision sur un ensemble de règles — D-01 à D-05.
 *
 * Le moteur ne décide pas d'un accès : il dit si une adresse correspond à une
 * liste, et par quelle règle. Traduire cette correspondance en accès est
 * l'affaire des portées (§3.1).
 *
 * L'adresse est reçue nullable, et ce n'est pas une facilité d'appel : une
 * adresse indéterminable est un état normal du système — un visiteur derrière
 * un proxy mal déclaré en produit un à chaque requête —, et D-05 impose qu'elle
 * ne corresponde à rien. Laisser l'appelant tester aurait fait du premier oubli
 * une erreur fatale sur ce chemin rare, ou pire, une correspondance.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Rule;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Address\Ip_Number;

/**
 * Évaluation d'une adresse contre une liste.
 */
final class Matcher {

	/**
	 * Évalue une adresse contre un ensemble de règles.
	 *
	 * La première règle qui correspond l'emporte (D-01), et c'est elle que le
	 * résultat rapporte (D-03) : le diagnostic doit être reproductible.
	 *
	 * @param Ip_Number|null $address Adresse évaluée, ou null si indéterminable.
	 * @param Rule_Set       $rules   Liste analysée.
	 * @return Match_Result
	 */
	public static function match( ?Ip_Number $address, Rule_Set $rules ): Match_Result {
		if ( null === $address ) {
			return Match_Result::no_match();
		}

		foreach ( $rules->rules() as $rule ) {
			if ( $rule->matches( $address ) ) {
				return Match_Result::matched( $rule );
			}
		}

		return Match_Result::no_match();
	}
}
