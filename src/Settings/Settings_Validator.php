<?php
/**
 * Validation des réglages à l'écriture — A-02, FNC-22, FNC-28.
 *
 * A-02 exige que toute règle soit validée **à l'écriture avec la même sévérité
 * qu'à la lecture**. C'est obtenu de la seule façon qui ne puisse pas dériver :
 * le même analyseur, appelé aux deux moments. Deux analyseurs, même écrits le
 * même jour, finissent par ne plus dire la même chose — et celui de l'écriture
 * serait le plus permissif, parce que personne ne le relit en pensant à la
 * sécurité.
 *
 * FNC-22 impose une asymétrie qu'il faut tenir : une entrée refusée est
 * **conservée telle quelle** dans le réglage, et signalée. Refuser
 * l'enregistrement entier ferait perdre à l'administrateur les quarante lignes
 * valides qu'il venait de coller à cause d'une faute de frappe sur la onzième ;
 * une ligne inerte dans un champ, elle, est visible et corrigeable.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Settings;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Rule\Rule_Set;

/**
 * Normalisation et contrôle des réglages soumis.
 */
final class Settings_Validator {

	/**
	 * Valide des réglages soumis.
	 *
	 * Les trois listes sont normalisées puis analysées séparément — aucun
	 * chemin commun paramétré par un nom de liste (A-01).
	 *
	 * @param array<string, mixed> $submitted Réglages soumis, déjà assainis par l'appelant.
	 * @return Validated_Settings
	 */
	public static function validate( array $submitted ): Validated_Settings {
		$allow_list      = self::normalise( $submitted['allow_list'] ?? '' );
		$block_list      = self::normalise( $submitted['block_list'] ?? '' );
		$trusted_proxies = self::normalise( $submitted['trusted_proxies'] ?? '' );

		$settings = Settings::from_array(
			array(
				'allow_list'            => $allow_list,
				'block_list'            => $block_list,
				'trusted_proxies'       => $trusted_proxies,
				'blocked_message'       => $submitted['blocked_message'] ?? null,
				'forwarded_header'      => $submitted['forwarded_header'] ?? null,
				'deletes_on_uninstall'  => ! empty( $submitted['deletes_on_uninstall'] ),
				'covers_taxonomy_terms' => ! empty( $submitted['covers_taxonomy_terms'] ),
			)
		);

		return new Validated_Settings(
			$settings,
			Rule_Set::from_text( $allow_list )->rejections(),
			Rule_Set::from_text( $block_list )->rejections(),
			Rule_Set::from_text( $trusted_proxies )->rejections()
		);
	}

	/**
	 * Normalise un texte de liste (FNC-28).
	 *
	 * Les lignes sont ajustées, les lignes vides retirées, les doublons exacts
	 * fusionnés, et l'ordre d'origine conservé. Les entrées refusées, elles,
	 * **restent** : c'est FNC-22, et c'est ce qui permet de les signaler à la
	 * ligne où l'administrateur les voit.
	 *
	 * @param mixed $text Texte soumis.
	 * @return string Texte normalisé.
	 */
	private static function normalise( $text ): string {
		if ( ! is_string( $text ) ) {
			return '';
		}

		$lines = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $text ) );
		$kept  = array();

		foreach ( $lines as $line ) {
			$entry = trim( $line );

			if ( '' === $entry || in_array( $entry, $kept, true ) ) {
				continue;
			}

			$kept[] = $entry;
		}

		return implode( "\n", $kept );
	}
}
