<?php
/**
 * Interruption de requête observée au lieu d'être subie.
 *
 * La dépendance termine le traitement d'une inscription par
 * `wp_send_json_success()` ou `wp_send_json_error()`, donc par `wp_die()`,
 * donc par `die()`. Dans un processus de test, `die()` arrête tout **en
 * silence et avec le code de sortie 0** : PHPUnit n'écrit aucun récapitulatif,
 * et l'intégration continue lit un succès là où la moitié des tests n'a pas été
 * exécutée. C'est le constat JE-007, et `bin/run-suite.sh` existe pour le
 * rattraper après coup.
 *
 * Mieux vaut ne pas en arriver là : un test qui provoque ce chemin remplace le
 * gestionnaire de `wp_die()` par un gestionnaire qui lève cette exception. Le
 * chemin est alors observable, et le processus survit.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Support;

use RuntimeException;

/**
 * Levée à la place d'un `die()` de la dépendance.
 */
final class Request_Halted extends RuntimeException {

	/**
	 * Installe le gestionnaire pour la durée du test.
	 *
	 * @return void
	 */
	public static function intercept_wp_die(): void {
		/*
		 * `wp_send_json()` n'appelle `wp_die()` — donc le gestionnaire
		 * filtrable — que si la requête est une requête AJAX ; sinon elle
		 * appelle `die` directement, et aucun filtre ne peut l'intercepter.
		 * Déclarer la requête AJAX est donc la condition pour que
		 * l'interruption soit observable, et non seulement subie.
		 */
		add_filter( 'wp_doing_ajax', '__return_true' );

		/*
		 * `wp_die()` ne choisit pas son gestionnaire par un seul filtre : il en
		 * existe un par nature de requête, et c'est `wp_die_ajax_handler` qui
		 * s'applique dès que la requête est déclarée AJAX. Brancher le seul
		 * `wp_die_handler` laissait donc passer le `die()`, et le processus
		 * s'arrêtait en silence. Les cinq sont branchés : aucune nature de
		 * requête ne doit pouvoir emporter le processus.
		 */
		$handlers = array(
			'wp_die_handler',
			'wp_die_ajax_handler',
			'wp_die_json_handler',
			'wp_die_jsonp_handler',
			'wp_die_xmlrpc_handler',
		);

		foreach ( $handlers as $handler ) {
			add_filter(
				$handler,
				static function (): callable {
					return static function ( $message = '' ): void {
						throw new self( is_string( $message ) ? $message : '' );
					};
				},
				99
			);
		}
	}
}
