<?php
/**
 * Message affiché lorsqu'une inscription est bloquée — FNC-10, FNC-11, I18N-04.
 *
 * Deux règles s'y croisent, et leur combinaison n'est pas évidente.
 *
 * FNC-10 veut un message de texte libre, avec une valeur par défaut fournie et
 * **traduisible**. I18N-04 précise que le message personnalisé par
 * l'administrateur, lui, n'est **pas** traduit : c'est sa chaîne, et la
 * traduire reviendrait à la réécrire. Seul le défaut l'est.
 *
 * La distinction se fait donc ici : un réglage vide ou resté au défaut est
 * rendu dans la langue du site ; tout autre texte est rendu tel quel.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Support;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Settings\Settings;

/**
 * Résolution du message de blocage.
 */
final class Blocked_Message {

	/**
	 * Message à afficher, non échappé.
	 *
	 * L'échappement est fait par l'appelant, après l'éventuel filtre : la
	 * dépendance interpole le message dans son balisage sans l'échapper
	 * (JE-023), et un filtre est du code tiers.
	 *
	 * @param Settings $settings Réglages courants.
	 * @return string
	 */
	public static function resolve( Settings $settings ): string {
		$stored = trim( $settings->blocked_message() );

		if ( '' === $stored || Settings::DEFAULT_BLOCKED_MESSAGE === $stored ) {
			return __( 'L\'inscription n\'est pas possible depuis cette connexion.', 'ip-restriction-for-restrict-content' );
		}

		return $stored;
	}
}
