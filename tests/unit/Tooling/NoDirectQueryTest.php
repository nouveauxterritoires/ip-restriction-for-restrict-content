<?php
/**
 * Aucune requête SQL directe dans le code livré — SEC-04.
 *
 * Tout passe par les API d'options et de métadonnées de WordPress. Ce n'est pas
 * une question de style : une requête écrite à la main contourne le cache
 * d'objets, les filtres que d'autres extensions posent sur ces lectures, et la
 * préparation des valeurs. Sur un plugin qui décide d'un accès, le dernier
 * point est le plus grave.
 *
 * Le contrôle est fait par analyse lexicale et non par expression régulière :
 * `$wpdb` cité dans un commentaire — par exemple celui qui explique pourquoi on
 * ne s'en sert pas — ne doit pas faire échouer le test.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Tooling;

use Ip_Restriction_For_Restrict_Content\Tests\Unit\UnitTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Absence d'accès direct à la base dans `src/`.
 */
final class NoDirectQueryTest extends UnitTestCase {

	/**
	 * Aucun fichier de `src/` ne mentionne la variable globale de base de
	 * données ni une fonction de requête directe.
	 *
	 * @return void
	 */
	public function test_no_delivered_file_queries_the_database_directly(): void {
		$offences = array();

		foreach ( $this->delivered_files() as $file ) {
			foreach ( $this->significant_tokens( $file ) as $token ) {
				if ( in_array( $token, array( '$wpdb', 'mysqli_query', 'mysql_query' ), true ) ) {
					$offences[] = basename( $file ) . ' : ' . $token;
				}
			}
		}

		$this->assertSame(
			array(),
			$offences,
			"SEC-04 : tout doit passer par les API d'options et de métadonnées.\n" . implode( "\n", $offences )
		);
	}

	/**
	 * Fichiers PHP livrés.
	 *
	 * @return list<string>
	 */
	private function delivered_files(): array {
		$root     = dirname( __DIR__, 3 ) . '/src';
		$files    = array();
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$files[] = (string) $file->getPathname();
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * Jetons significatifs d'un fichier, commentaires et textes exclus.
	 *
	 * @param string $file Chemin du fichier.
	 * @return list<string>
	 */
	private function significant_tokens( string $file ): array {
		$ignored = array( T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML, T_CONSTANT_ENCAPSED_STRING );
		$kept    = array();

		foreach ( token_get_all( (string) file_get_contents( $file ) ) as $token ) {
			if ( ! is_array( $token ) || in_array( $token[0], $ignored, true ) ) {
				continue;
			}

			$kept[] = $token[1];
		}

		return $kept;
	}

	/**
	 * Le contrôle porte bien sur des fichiers.
	 *
	 * Sans cela, le test précédent passerait triomphalement sur un répertoire
	 * renommé.
	 *
	 * @return void
	 */
	public function test_the_check_covers_the_delivered_files(): void {
		$this->assertGreaterThan( 10, count( $this->delivered_files() ) );
	}
}
