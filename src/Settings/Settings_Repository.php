<?php
/**
 * Lecture et écriture des réglages globaux.
 *
 * Une seule option porte les cinq réglages (§6.2) : elle est lue à chaque
 * requête, et une option unique est chargée avec les options automatiques
 * plutôt que de coûter cinq lectures. Les deux listes y sont rangées sous des
 * clés distinctes, et aucun code commun paramétré par un nom de clé ne les
 * manipule (A-01).
 *
 * Les réglages sont **propres à chaque site** d'un réseau : `get_option()` et
 * `update_option()` le sont par construction. C'est la décision O-08, rendue au
 * lot 4 et motivée par ADR-0009.
 *
 * Aucune requête SQL directe : tout passe par l'API d'options (SEC-04), et un
 * test de la suite `unit` échoue si `$wpdb` apparaît dans `src/`.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Dépôt des réglages globaux.
 */
final class Settings_Repository {

	/**
	 * Option qui porte les réglages.
	 *
	 * @var string
	 */
	public const OPTION = 'ip_restriction_for_restrict_content_settings';

	/**
	 * Option qui porte la version de la forme des réglages.
	 *
	 * Elle permettra une migration de forme sans deviner l'état antérieur
	 * (§6.2) : sans elle, une future migration devrait inférer la version
	 * depuis la présence des clés, ce qui est une heuristique.
	 *
	 * @var string
	 */
	public const VERSION_OPTION = 'ip_restriction_for_restrict_content_version';

	/**
	 * Lit les réglages.
	 *
	 * @return Settings Les réglages stockés, ou les valeurs par défaut.
	 */
	public function load(): Settings {
		$stored = get_option( self::OPTION, array() );

		return Settings::from_array( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Écrit les réglages.
	 *
	 * @param Settings $settings Réglages à écrire.
	 * @return void
	 */
	public function save( Settings $settings ): void {
		update_option( self::OPTION, $settings->to_array() );
		update_option( self::VERSION_OPTION, RCIPR_VERSION );
	}
}
