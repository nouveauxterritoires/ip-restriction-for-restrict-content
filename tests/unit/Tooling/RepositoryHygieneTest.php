<?php
/**
 * Contrôles d'hygiène du dépôt.
 *
 * Couvre SEC-15 (garde ABSPATH), la règle de style « declare(strict_types=1)
 * en tête de chaque fichier PHP » (§7.4), l'interdiction de `@phpstan-ignore`
 * (§12.4), la liste noire du développement en salle blanche (§0.3) et les deux
 * exigences de livraison du §14.3 — conformité du slug et mention de
 * non-affiliation —, que leur ressemblance de sujet fait souvent confondre.
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
	 * Mention de non-affiliation, dans la formulation retenue au §14.3.
	 *
	 * @var string
	 */
	private const DISCLAIMER = 'ni développée ni approuvée par les éditeurs de';

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

	/**
	 * §14.1 : la version est la même aux trois endroits où elle est écrite.
	 *
	 * L'en-tête et la constante sont déjà comparés par la suite
	 * `integration` ; le `Stable tag` du `readme.txt` est le troisième, et une
	 * divergence produit un paquet que WordPress.org refuse. `bin/build.sh` le
	 * vérifie aussi, mais une construction échouée se découvre tard.
	 *
	 * @return void
	 */
	public function test_the_version_is_the_same_in_all_three_places(): void {
		$plugin = (string) file_get_contents( $this->root() . '/ip-restriction-for-restrict-content.php' );
		$readme = (string) file_get_contents( $this->root() . '/readme.txt' );

		$this->assertSame( 1, preg_match( '/^ \* Version:\s+(.+)$/m', $plugin, $header ) );
		$this->assertSame( 1, preg_match( "/^const RCIPR_VERSION = '([^']+)';$/m", $plugin, $constant ) );
		$this->assertSame( 1, preg_match( '/^Stable tag:\s+(.+)$/m', $readme, $tag ) );

		$version = trim( $header[1] );

		$this->assertSame( $version, trim( $constant[1] ), 'L\'en-tête et RCIPR_VERSION divergent.' );
		$this->assertSame( $version, trim( $tag[1] ), 'L\'en-tête et le Stable tag du readme.txt divergent.' );
	}

	/**
	 * §14.1 : le journal des modifications porte une entrée pour cette version.
	 *
	 * @return void
	 */
	public function test_the_changelog_carries_an_entry_for_this_version(): void {
		$plugin = (string) file_get_contents( $this->root() . '/ip-restriction-for-restrict-content.php' );

		$this->assertSame( 1, preg_match( '/^ \* Version:\s+(.+)$/m', $plugin, $header ) );

		$version = trim( $header[1] );

		$this->assertStringContainsString(
			'[' . $version . ']',
			(string) file_get_contents( $this->root() . '/CHANGELOG.md' ),
			'CHANGELOG.md ne porte aucune entrée pour la version déclarée.'
		);
		$this->assertStringContainsString(
			'= ' . $version . ' =',
			(string) file_get_contents( $this->root() . '/readme.txt' ),
			'Le readme.txt ne porte aucune entrée de journal pour la version déclarée.'
		);
	}

	/**
	 * §14.3, première exigence : le slug ne commence par aucune marque.
	 *
	 * La directive 17 du répertoire porte sur la **forme** du nom. Le slug
	 * commence par une fonction générique (ADR-0008), et ce test interdit de
	 * revenir dessus par inadvertance — un renommage se verrait alors ici
	 * plutôt qu'au dépôt du paquet. Le slug est lu sur le nom du fichier
	 * principal et non sur celui du dossier : le dossier d'une copie de travail
	 * n'est pas forcément nommé comme le plugin.
	 *
	 * @return void
	 */
	public function test_the_slug_does_not_start_with_a_trademark(): void {
		$candidates = array();

		foreach ( (array) glob( $this->root() . '/*.php' ) as $path ) {
			$contents = (string) file_get_contents( (string) $path );

			if ( false !== strpos( $contents, 'Plugin Name:' ) ) {
				$candidates[] = basename( (string) $path, '.php' );
			}
		}

		$this->assertCount( 1, $candidates, 'Un seul fichier de la racine doit porter un en-tête de plugin.' );

		$slug = $candidates[0];

		$this->assertStringStartsWith(
			'ip-restriction',
			$slug,
			'Directive 17 : le slug doit commencer par une fonction générique, pas par une marque (ADR-0008).'
		);
		$this->assertFileExists( $this->root() . '/readme.txt' );
	}

	/**
	 * §14.3, seconde exigence : la mention de non-affiliation est partout où
	 * elle doit être.
	 *
	 * Elle traite une autre question que le slug — l'impression produite sur
	 * qui installe, et non la forme du nom. Un slug conforme ne dit rien de
	 * l'éditeur du plugin : constater l'un n'autorise pas à retirer l'autre, et
	 * c'est précisément l'erreur dont le §14.3 avertit le successeur.
	 *
	 * @return void
	 */
	public function test_the_disclaimer_is_present_everywhere_it_must_be(): void {
		$files = array(
			'ip-restriction-for-restrict-content.php',
			'readme.txt',
			'README.md',
		);

		foreach ( $files as $file ) {
			$this->assertStringContainsString(
				self::DISCLAIMER,
				(string) file_get_contents( $this->root() . '/' . $file ),
				sprintf( 'La mention de non-affiliation du §14.3 manque dans %s.', $file )
			);
		}
	}
}
