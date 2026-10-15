<?php
/**
 * La couche de décision ne connaît pas WordPress.
 *
 * C'est la frontière du milieu de l'architecture, et la seule qu'un test peut
 * vérifier mécaniquement. Elle n'est pas de goût : le moteur de correspondance
 * porte le plancher de couverture renforcé du cahier des charges §11.6, et ce
 * plancher n'est atteignable que si chaque cas de la grammaire coûte une
 * milliseconde. Un seul appel à une fonction de WordPress dans ces répertoires
 * et la suite `unit` ne peut plus les exercer : il faudrait un site, une
 * requête, un contenu restreint par cas.
 *
 * Le contrôle est fait par analyse lexicale plutôt que par expression
 * régulière : un `// esc_html()` en commentaire ne doit pas faire échouer le
 * test, et un `esc_html(` réellement appelé ne doit pas y échapper.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Tooling;

use Ip_Restriction_For_Restrict_Content\Tests\Unit\UnitTestCase;

/**
 * Pureté des répertoires de décision.
 */
final class PurityTest extends UnitTestCase {

	/**
	 * Répertoires qui ne doivent contenir aucun appel à WordPress.
	 *
	 * @var list<string>
	 */
	private const PURE_DIRECTORIES = array( 'src/Address', 'src/Rule', 'src/Policy' );

	/**
	 * Fichiers PHP d'un répertoire du dépôt.
	 *
	 * @param string $relative Chemin relatif à la racine du dépôt.
	 * @return list<string> Chemins absolus.
	 */
	private function php_files( string $relative ): array {
		$directory = dirname( __DIR__, 3 ) . '/' . $relative;

		if ( ! is_dir( $directory ) ) {
			return array();
		}

		$files = glob( $directory . '/*.php' );

		return false === $files ? array() : array_values( $files );
	}

	/**
	 * Noms des fonctions appelées par un fichier, hors méthodes.
	 *
	 * Un appel de méthode — précédé de `->` ou de `::` — n'est pas un appel de
	 * fonction globale : c'est du code du plugin, et il est légitime. Seuls les
	 * appels de fonctions globales peuvent atteindre WordPress.
	 *
	 * @param string $file Chemin du fichier.
	 * @return list<string> Noms de fonctions, sans doublon.
	 */
	private function called_functions( string $file ): array {
		$tokens = token_get_all( (string) file_get_contents( $file ) );
		$called = array();
		$count  = count( $tokens );

		for ( $index = 0; $index < $count; $index++ ) {
			$token = $tokens[ $index ];

			if ( ! is_array( $token ) || T_STRING !== $token[0] ) {
				continue;
			}

			$previous = $this->significant_token( $tokens, $index, -1 );
			$next     = $this->significant_token( $tokens, $index, 1 );

			if ( '(' !== $next ) {
				continue;
			}

			if ( in_array( $previous, array( '->', '::', 'function', 'new', '?->' ), true ) ) {
				continue;
			}

			$called[ strtolower( $token[1] ) ] = true;
		}

		return array_keys( $called );
	}

	/**
	 * Jeton significatif voisin, espaces et commentaires ignorés.
	 *
	 * @param array<int, array{0: int, 1: string}|string> $tokens    Jetons du fichier.
	 * @param int                                         $index     Rang de départ.
	 * @param int                                         $direction Sens du parcours.
	 * @return string Texte du jeton, chaîne vide si aucun.
	 */
	private function significant_token( array $tokens, int $index, int $direction ): string {
		$ignored = array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT );

		for ( $cursor = $index + $direction; isset( $tokens[ $cursor ] ); $cursor += $direction ) {
			$token = $tokens[ $cursor ];

			if ( is_array( $token ) && in_array( $token[0], $ignored, true ) ) {
				continue;
			}

			return is_array( $token ) ? $token[1] : $token;
		}

		return '';
	}

	/**
	 * Aucun répertoire de décision n'appelle une fonction qui ne soit pas de
	 * PHP lui-même.
	 *
	 * Le critère est volontairement plus strict que « aucune fonction de
	 * WordPress » : tout appel à une fonction qui n'est pas interne à PHP est
	 * signalé. Une fonction de WordPress y tombe, et aussi une fonction
	 * globale que le plugin déclarerait ailleurs — ce qui serait un couplage
	 * tout aussi contraire à l'architecture.
	 *
	 * @return void
	 */
	public function test_the_decision_layer_calls_nothing_but_php(): void {
		$internal = array_map( 'strtolower', get_defined_functions()['internal'] );
		$offences = array();

		foreach ( self::PURE_DIRECTORIES as $directory ) {
			foreach ( $this->php_files( $directory ) as $file ) {
				foreach ( $this->called_functions( $file ) as $function ) {
					if ( in_array( $function, $internal, true ) ) {
						continue;
					}

					$offences[] = basename( $file ) . ' : ' . $function . '()';
				}
			}
		}

		$this->assertSame(
			array(),
			$offences,
			"La couche de décision doit rester pure :\n" . implode( "\n", $offences )
		);
	}

	/**
	 * Les répertoires surveillés existent, et portent du code.
	 *
	 * Sans ce contrôle, le test précédent passerait triomphalement sur un
	 * répertoire renommé ou vide — c'est-à-dire exactement quand il cesserait
	 * de protéger quoi que ce soit.
	 *
	 * @return void
	 */
	public function test_the_watched_directories_are_not_empty(): void {
		$this->assertNotSame( array(), $this->php_files( 'src/Address' ), 'src/Address est vide.' );
		$this->assertNotSame( array(), $this->php_files( 'src/Rule' ), 'src/Rule est vide.' );
	}
}
