<?php
/**
 * Tests d'amorçage contre un WordPress réel.
 *
 * Ce que ces tests établissent n'est pas que le code existe, mais qu'il
 * s'exécute : le plugin est chargé, la dépendance est détectée par capacité, et
 * aucune erreur PHP n'est émise au passage.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Integration;

use Ip_Restriction_For_Restrict_Content\Plugin;
use WP_UnitTestCase;

/**
 * Amorçage du plugin dans l'environnement de test.
 */
final class BootTest extends WP_UnitTestCase {

	/**
	 * Le fichier principal a été chargé, et ses constantes sont définies.
	 *
	 * @return void
	 */
	public function test_plugin_constants_are_defined(): void {
		$this->assertTrue( defined( 'RCIPR_PLUGIN_FILE' ) );
		$this->assertTrue( defined( 'RCIPR_PLUGIN_DIR' ) );
		$this->assertSame( RCIPR_VERSION, constant( 'RCIPR_VERSION' ) );
	}

	/**
	 * La version déclarée dans l'en-tête du plugin et la constante coïncident.
	 *
	 * La construction de l'archive vérifie déjà cette égalité, mais elle ne
	 * s'exécute qu'au moment de livrer. Ici, la divergence est vue tout de suite.
	 *
	 * @return void
	 */
	public function test_header_version_matches_constant(): void {
		$header = get_file_data(
			RCIPR_PLUGIN_DIR . 'ip-restriction-for-restrict-content.php',
			array( 'version' => 'Version' )
		);

		$this->assertSame( RCIPR_VERSION, $header['version'] );
	}

	/**
	 * La dépendance d'adhésions est présente dans l'environnement de test, et
	 * le plugin la détecte.
	 *
	 * @return void
	 */
	public function test_environment_satisfies_requirements(): void {
		$plugin = Plugin::from_environment();

		$this->assertSame(
			array(),
			$plugin->missing_requirements(),
			'Prérequis absents dans l\'environnement de test : '
			. implode( ', ', $plugin->missing_requirements() )
		);
		$this->assertTrue( $plugin->is_satisfied() );
	}

	/**
	 * L'enregistrement n'émet aucune erreur PHP.
	 *
	 * Un avertissement au chargement est une erreur fatale en puissance : il
	 * signale un appel à une fonction dont la signature a changé en amont.
	 *
	 * @return void
	 */
	public function test_registering_emits_no_php_error(): void {
		$errors = array();

		set_error_handler(
			static function ( int $severity, string $message ) use ( &$errors ): bool {
				$errors[] = $severity . ' : ' . $message;

				return true;
			}
		);

		Plugin::from_environment()->register();

		restore_error_handler();

		$this->assertSame( array(), $errors, implode( "\n", $errors ) );
	}

	/**
	 * Le plugin s'annonce absent de prérequis plutôt que de provoquer une
	 * erreur fatale quand la dépendance manque.
	 *
	 * La dépendance étant chargée dans ce processus, le scénario est reproduit
	 * en construisant l'avis directement : c'est la seule branche que
	 * `register()` emprunte alors, et elle doit rester atteignable sans
	 * désinstaller la dépendance.
	 *
	 * @return void
	 */
	public function test_missing_dependency_produces_a_notice_not_a_fatal(): void {
		$notice = new \Ip_Restriction_For_Restrict_Content\Admin\Requirements_Notice( array( 'Restrict Content, actif' ) );
		$notice->register();

		$this->assertNotFalse(
			has_action( 'admin_notices', array( $notice, 'render' ) ),
			'L\'avis de prérequis doit s\'accrocher à admin_notices.'
		);
	}

	/**
	 * L'avis de prérequis échappe réellement ce qu'il affiche.
	 *
	 * La suite `unit` établit que `esc_html()` est appelée ; ici, WordPress est
	 * chargé, et c'est la sortie effective qui est examinée. Les deux tests
	 * sont nécessaires : le premier attrape un oubli d'échappement, le second
	 * attrape un échappement qui n'échapperait pas.
	 *
	 * @return void
	 */
	public function test_requirements_notice_escapes_its_labels(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$notice = new \Ip_Restriction_For_Restrict_Content\Admin\Requirements_Notice( array( '<script>alert(1)</script>' ) );

		ob_start();
		$notice->render();
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
	}

	/**
	 * L'amorçage ne tente aucune requête HTTP sortante.
	 *
	 * SEC-16 est déjà couverte à la relecture par `bin/scan-secrets.sh`, qui
	 * attrape l'intention dans le code livré. Ce test établit le fait à
	 * l'exécution : le bouchon échoue à la moindre tentative, y compris celle
	 * qu'une dépendance transitive déclencherait pour notre compte.
	 *
	 * @return void
	 */
	public function test_booting_attempts_no_outbound_request(): void {
		$attempts = array();

		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$attempts ) {
				unset( $preempt, $args );

				$attempts[] = (string) $url;

				return new \WP_Error( 'rcipr_no_network', 'Aucun appel réseau n\'est autorisé.' );
			},
			1,
			3
		);

		$plugin = Plugin::from_environment();
		$plugin->register();

		/*
		 * La dépendance rend ses propres avis sur cette accroche : la sortie
		 * est absorbée, sans quoi PHPUnit déclare le test « risqué » pour un
		 * balisage qui n'est pas le nôtre.
		 */
		ob_start();
		do_action( 'admin_notices' );
		ob_end_clean();

		$this->assertSame(
			array(),
			$attempts,
			'Le plugin a tenté une requête HTTP sortante vers : ' . implode( ', ', $attempts )
			. '. SEC-16 l\'interdit sans exception.'
		);
	}
}
