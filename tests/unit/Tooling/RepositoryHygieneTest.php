<?php
/**
 * Contrôles d'hygiène du dépôt.
 *
 * Couvre SEC-15 (garde ABSPATH), la règle de style « declare(strict_types=1)
 * en tête de chaque fichier PHP » (§7.4), l'interdiction de `@phpstan-ignore`
 * (§12.4) et la liste noire du développement en salle blanche (§0.3).
 *
 * Ces règles sont vérifiables par lecture : les confier à un test évite
 * qu'elles ne survivent que dans la mémoire de celui qui les a posées.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Tooling;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Invariants que tout fichier du dépôt doit respecter.
 */
final class RepositoryHygieneTest extends TestCase {

	/**
	 * Répertoires exclus de l'analyse : ils ne contiennent pas de code du projet.
	 *
	 * `stubs/` en fait partie. Son contenu est **généré** depuis la source de
	 * la dépendance par `bin/generate-stubs.sh`, n'est jamais chargé à
	 * l'exécution, et n'entre pas dans l'archive distribuée : lui imposer les
	 * règles d'écriture du projet reviendrait à éditer un fichier que le
	 * cahier des charges §12.4 interdit précisément d'éditer.
	 *
	 * @var list<string>
	 */
	private const EXCLUDED = array( 'vendor', 'node_modules', 'dist', 'build', '.git', 'tests/coverage', 'vendor-plugins', 'stubs' );

	/**
	 * Chaînes interdites par le développement en salle blanche (§0.3).
	 *
	 * Aucun identifiant de l'add-on commercial ne doit apparaître dans le code
	 * livré. Le cahier des charges lui-même cite ces chaînes en annexe B à
	 * titre de correspondance de conception : `docs/` est donc hors périmètre.
	 *
	 * @var list<string>
	 */
	private const BLACKLIST = array( 'rcp-ip-restriction', 'rcpipr' );

	/**
	 * Racine du dépôt.
	 *
	 * @return string
	 */
	private function root(): string {
		return dirname( __DIR__, 3 );
	}

	/**
	 * Liste les fichiers PHP du projet.
	 *
	 * @return list<string> Chemins absolus.
	 */
	private function php_files(): array {
		$root  = $this->root();
		$files = array();

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			$path = (string) $file;

			if ( 'php' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
				continue;
			}

			$relative = ltrim( str_replace( $root, '', $path ), '/' );

			foreach ( self::EXCLUDED as $excluded ) {
				if ( 0 === strpos( $relative, $excluded . '/' ) ) {
					continue 2;
				}
			}

			$files[] = $path;
		}

		sort( $files );

		return $files;
	}

	/**
	 * SEC-15 : tout fichier PHP destiné à WordPress refuse l'accès direct.
	 *
	 * `uninstall.php` utilise la garde qui lui est propre, `WP_UNINSTALL_PLUGIN`,
	 * et les fichiers de tests ne sont jamais servis par le serveur web.
	 *
	 * @return void
	 */
	public function test_every_shipped_file_guards_direct_access(): void {
		foreach ( $this->php_files() as $path ) {
			$relative = ltrim( str_replace( $this->root(), '', $path ), '/' );

			if ( 0 === strpos( $relative, 'tests/' ) || 0 === strpos( $relative, 'bin/' ) ) {
				continue;
			}

			$contents = (string) file_get_contents( $path );

			$guarded = false !== strpos( $contents, "defined( 'ABSPATH' ) || exit;" )
				|| false !== strpos( $contents, "defined( 'WP_UNINSTALL_PLUGIN' ) || exit;" );

			$this->assertTrue( $guarded, "Garde d'accès direct absente : {$relative}" );
		}
	}

	/**
	 * §7.4 : le typage strict est déclaré en tête de chaque fichier PHP.
	 *
	 * @return void
	 */
	public function test_every_php_file_declares_strict_types(): void {
		foreach ( $this->php_files() as $path ) {
			$relative = ltrim( str_replace( $this->root(), '', $path ), '/' );

			$this->assertStringContainsString(
				'declare( strict_types = 1 );',
				(string) file_get_contents( $path ),
				"Typage strict non déclaré : {$relative}"
			);
		}
	}

	/**
	 * §12.4 : aucune erreur d'analyse statique n'est réduite au silence.
	 *
	 * @return void
	 */
	public function test_no_phpstan_ignore_anywhere(): void {
		foreach ( $this->php_files() as $path ) {
			// Ce fichier cite les motifs qu'il interdit : il est son propre faux positif.
			if ( __FILE__ === $path ) {
				continue;
			}

			$relative = ltrim( str_replace( $this->root(), '', $path ), '/' );

			$this->assertStringNotContainsString(
				'@phpstan-ignore',
				(string) file_get_contents( $path ),
				"Erreur d'analyse statique masquée : {$relative}"
			);
		}
	}

	/**
	 * §0.3 : aucun identifiant de l'add-on commercial dans le code.
	 *
	 * @return void
	 */
	public function test_no_blacklisted_identifier_in_code(): void {
		foreach ( $this->php_files() as $path ) {
			// Ce fichier cite les motifs qu'il interdit : il est son propre faux positif.
			if ( __FILE__ === $path ) {
				continue;
			}

			$relative = ltrim( str_replace( $this->root(), '', $path ), '/' );
			$contents = (string) file_get_contents( $path );

			foreach ( self::BLACKLIST as $forbidden ) {
				$this->assertStringNotContainsString(
					$forbidden,
					$contents,
					"Identifiant de l'add-on commercial dans {$relative} : {$forbidden}"
				);
			}
		}
	}
}
