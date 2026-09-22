<?php
/**
 * Plugin Name:       IP Restriction for Restrict Content
 * Plugin URI:        https://github.com/nouveaux-territoires/ip-restriction-for-restrict-content
 * Description:       Restriction par adresse IP pour Restrict Content et Restrict Content Pro : listes d'autorisation qui contournent les restrictions de contenu, et liste de blocage qui refuse l'inscription. Portées globale, par niveau d'adhésion et par contenu. Extension indépendante, ni développée ni approuvée par les éditeurs de Restrict Content ou de Restrict Content Pro.
 * Version:           0.1.0-dev
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Nouveaux Territoires
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ip-restriction-for-restrict-content
 * Domain Path:       /languages
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/*
 * Forme courte du préfixe pour les constantes, conformément à ADR-0001.
 */
const RCIPR_VERSION = '0.1.0-dev';
const RCIPR_MIN_PHP = '8.1';
const RCIPR_MIN_WP  = '6.4';

define( 'RCIPR_PLUGIN_FILE', __FILE__ );
define( 'RCIPR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RCIPR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enregistre le chargement automatique des classes du plugin.
 *
 * L'archive distribuable embarque `vendor/autoload.php` réduit au seul
 * autoloader (directive 8 de WordPress.org : aucun code chargé depuis un
 * serveur externe). En développement, un autoloader PSR-4 minimal prend le
 * relais tant que les dépendances ne sont pas installées, afin que le plugin
 * reste activable sur une copie de travail fraîche.
 *
 * @return void
 */
function ip_restriction_for_restrict_content_register_autoloader(): void {
	$composer = RCIPR_PLUGIN_DIR . 'vendor/autoload.php';

	if ( is_readable( $composer ) ) {
		require_once $composer;

		return;
	}

	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'Ip_Restriction_For_Restrict_Content\\';

			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}

			$relative = str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) );
			$path     = RCIPR_PLUGIN_DIR . 'src/' . $relative . '.php';

			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	);
}

ip_restriction_for_restrict_content_register_autoloader();

/**
 * Amorce le plugin une fois la dépendance chargée.
 *
 * La priorité 20 laisse à Restrict Content — variante libre comme commerciale —
 * le temps de définir ses classes et ses fonctions.
 *
 * Cette fonction ne fait rien d'autre que sonder puis enregistrer. Aucune
 * classe métier n'est chargée avant que la sonde n'ait conclu : c'est ce qui
 * garantit qu'une rupture amont ne peut pas produire d'erreur fatale dans un
 * constructeur du plugin (cahier des charges §5.3).
 *
 * @return void
 */
function ip_restriction_for_restrict_content_boot(): void {
	\Ip_Restriction_For_Restrict_Content\Plugin::from_environment()->register();
}

add_action( 'plugins_loaded', 'ip_restriction_for_restrict_content_boot', 20 );
