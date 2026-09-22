<?php
/**
 * Tests de l'honnêteté du Makefile.
 *
 * Le cahier des charges §12.2 pose que l'aide d'une cible décrit ce que la
 * cible fait réellement, et qu'aucune cible ne référence un script inexistant.
 * Une cible qui annonce un outil qu'elle n'exécute pas est un mensonge : c'est
 * le genre de dérive qu'un test attrape et qu'une relecture laisse passer.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Tooling;

use PHPUnit\Framework\TestCase;

/**
 * Contrôles statiques sur le Makefile et les scripts qu'il appelle.
 */
final class MakefileTest extends TestCase {

	/**
	 * Racine du dépôt.
	 *
	 * @return string
	 */
	private function root(): string {
		return dirname( __DIR__, 3 );
	}

	/**
	 * Contenu du Makefile.
	 *
	 * @return string
	 */
	private function makefile(): string {
		$path = $this->root() . '/Makefile';

		$this->assertFileExists( $path, 'Le Makefile est le point d\'entrée documenté du projet.' );

		return (string) file_get_contents( $path );
	}

	/**
	 * Chaque script appelé par le Makefile existe et est exécutable.
	 *
	 * @return void
	 */
	public function test_every_referenced_script_exists(): void {
		preg_match_all( '#bin/[a-z0-9-]+\.sh#', $this->makefile(), $matches );

		$scripts = array_unique( $matches[0] );

		$this->assertNotEmpty( $scripts, 'Le Makefile doit déléguer à des scripts de bin/.' );

		foreach ( $scripts as $script ) {
			$path = $this->root() . '/' . $script;

			$this->assertFileExists( $path, "Cible du Makefile pointant sur un script absent : {$script}" );
			$this->assertTrue( is_executable( $path ), "Script non exécutable : {$script}" );
		}
	}

	/**
	 * Chaque cible déclarée .PHONY est définie, et réciproquement.
	 *
	 * @return void
	 */
	public function test_phony_targets_match_defined_targets(): void {
		$makefile = $this->makefile();

		preg_match_all( '/^\.PHONY:((?:[^\n\\\\]|\\\\\n)*)/m', $makefile, $phony_matches );
		$phony = preg_split( '/\s+/', trim( str_replace( '\\', ' ', implode( ' ', $phony_matches[1] ) ) ) );
		$phony = array_values( array_filter( (array) $phony ) );

		preg_match_all( '/^([a-z][a-z0-9-]*):/m', $makefile, $target_matches );
		$targets = array_values( array_unique( $target_matches[1] ) );

		sort( $phony );
		sort( $targets );

		$this->assertSame( $targets, $phony, 'Les cibles définies et les cibles .PHONY doivent coïncider.' );
	}

	/**
	 * Chaque cible porte une aide, puisque `make help` est la documentation
	 * de premier niveau du projet.
	 *
	 * @return void
	 */
	public function test_every_target_is_documented(): void {
		preg_match_all( '/^([a-z][a-z0-9-]*):[^\n]*$/m', $this->makefile(), $matches, PREG_SET_ORDER );

		$this->assertNotEmpty( $matches );

		foreach ( $matches as $match ) {
			$this->assertStringContainsString(
				'## ',
				$match[0],
				"Cible sans aide : {$match[1]}"
			);
		}
	}
}
