<?php
/**
 * Tests des scanners bloquants en intégration continue.
 *
 * Couvre SEC-05 (aucun secret dans le dépôt) et §11.5 (aucune fausse
 * complétion). Les scanners sont exécutés réellement : un scanner qui ne
 * s'exécute pas ne protège rien, et un test qui se contente de lire son code
 * source le laisserait passer.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Tooling;

use PHPUnit\Framework\TestCase;

/**
 * Exécution effective des scanners sur le dépôt courant.
 */
final class ScannersTest extends TestCase {

	/**
	 * Racine du dépôt.
	 *
	 * @return string
	 */
	private function root(): string {
		return dirname( __DIR__, 3 );
	}

	/**
	 * Exécute un script et renvoie son code de sortie et sa sortie combinée.
	 *
	 * @param string $script Chemin relatif à la racine du dépôt.
	 * @return array{0:int,1:string}
	 */
	private function execute( string $script ): array {
		$path = $this->root() . '/' . $script;

		$this->assertFileExists( $path, "Scanner absent : {$script}" );
		$this->assertTrue( is_executable( $path ), "Scanner non exécutable : {$script}" );

		$output = array();
		$code   = 0;

		exec( 'cd ' . escapeshellarg( $this->root() ) . ' && bash ' . escapeshellarg( $path ) . ' 2>&1', $output, $code );

		return array( $code, implode( "\n", $output ) );
	}

	/**
	 * SEC-05 : le dépôt ne contient aucun secret.
	 *
	 * @return void
	 */
	public function test_secret_scanner_passes(): void {
		list( $code, $output ) = $this->execute( 'bin/scan-secrets.sh' );

		$this->assertSame( 0, $code, "bin/scan-secrets.sh a échoué :\n" . $output );
	}

	/**
	 * §11.5 : aucun test ignoré, aucun marqueur de substitution, aucune chaîne
	 * de la liste noire du développement en salle blanche.
	 *
	 * @return void
	 */
	public function test_placeholder_scanner_passes(): void {
		list( $code, $output ) = $this->execute( 'bin/scan-placeholders.sh' );

		$this->assertSame( 0, $code, "bin/scan-placeholders.sh a échoué :\n" . $output );
	}
}
