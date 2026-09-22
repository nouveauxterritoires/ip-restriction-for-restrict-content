<?php
/**
 * Tests de la règle de prérequis.
 *
 * La règle étant pure, la suite peut l'exercer sur toutes les combinaisons,
 * y compris celles qu'un environnement réel ne présenterait pas. C'est la
 * raison d'être de la séparation : un environnement ne se fabrique pas, une
 * décision se vérifie.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Domain;

use Ip_Restriction_For_Restrict_Content\Domain\Requirements;
use Ip_Restriction_For_Restrict_Content\Tests\Unit\UnitTestCase;

/**
 * Combinaisons de prérequis satisfaits et absents.
 */
final class RequirementsTest extends UnitTestCase {

	/**
	 * Libellés utilisés par les cas de test.
	 *
	 * @var array<string, string>
	 */
	private const LABELS = array(
		'php'        => 'PHP trop ancien',
		'wp'         => 'WordPress trop ancien',
		'dependency' => 'Dépendance absente',
	);

	/**
	 * Évalue une combinaison avec les planchers du projet.
	 *
	 * @param string $php        Version de PHP constatée.
	 * @param string $wp         Version de WordPress constatée.
	 * @param bool   $dependency La dépendance est détectée.
	 * @return Requirements
	 */
	private function evaluate( string $php, string $wp, bool $dependency ): Requirements {
		return Requirements::evaluate( $php, $wp, $dependency, '8.1', '6.4', self::LABELS );
	}

	/**
	 * Un environnement conforme ne signale rien.
	 *
	 * @return void
	 */
	public function test_a_satisfied_environment_reports_nothing(): void {
		$verdict = $this->evaluate( '8.2.0', '6.7', true );

		$this->assertTrue( $verdict->is_satisfied() );
		$this->assertSame( array(), $verdict->missing() );
	}

	/**
	 * La version exigée elle-même satisfait le plancher : le plancher est
	 * inclusif, et une comparaison stricte exclurait la version annoncée dans
	 * l'en-tête du plugin.
	 *
	 * @return void
	 */
	public function test_the_declared_floor_is_inclusive(): void {
		$this->assertTrue( $this->evaluate( '8.1', '6.4', true )->is_satisfied() );
	}

	/**
	 * Cas où un seul prérequis manque.
	 *
	 * @dataProvider provide_single_failures
	 * @param string $php        Version de PHP constatée.
	 * @param string $wp         Version de WordPress constatée.
	 * @param bool   $dependency La dépendance est détectée.
	 * @param string $expected   Libellé attendu.
	 * @return void
	 */
	public function test_a_single_missing_requirement_is_named( string $php, string $wp, bool $dependency, string $expected ): void {
		$verdict = $this->evaluate( $php, $wp, $dependency );

		$this->assertFalse( $verdict->is_satisfied() );
		$this->assertSame( array( $expected ), $verdict->missing() );
	}

	/**
	 * Jeux de données du test précédent.
	 *
	 * @return array<string, array{0:string,1:string,2:bool,3:string}>
	 */
	public static function provide_single_failures(): array {
		return array(
			'PHP trop ancien'       => array( '8.0.30', '6.7', true, 'PHP trop ancien' ),
			'WordPress trop ancien' => array( '8.2.0', '6.3', true, 'WordPress trop ancien' ),
			'dépendance absente'    => array( '8.2.0', '6.7', false, 'Dépendance absente' ),
		);
	}

	/**
	 * Les prérequis absents sont tous signalés, pas seulement le premier.
	 *
	 * Un avis qui n'annoncerait qu'un manque à la fois obligerait
	 * l'administrateur à corriger, recharger, découvrir le suivant.
	 *
	 * @return void
	 */
	public function test_all_missing_requirements_are_reported_at_once(): void {
		$verdict = $this->evaluate( '8.0.30', '6.3', false );

		$this->assertSame(
			array( 'PHP trop ancien', 'WordPress trop ancien', 'Dépendance absente' ),
			$verdict->missing()
		);
	}

	/**
	 * Une version de WordPress porteuse d'un suffixe reste comparable.
	 *
	 * `get_bloginfo( 'version' )` renvoie par exemple « 6.8-RC1 » sur une
	 * préversion. `version_compare()` traite « RC » comme antérieur à la
	 * version finale : 6.4-RC1 est donc en deçà du plancher 6.4, et c'est le
	 * comportement voulu — une préversion n'est pas la version.
	 *
	 * @return void
	 */
	public function test_prerelease_versions_fall_below_the_floor(): void {
		$this->assertFalse( $this->evaluate( '8.2.0', '6.4-RC1', true )->is_satisfied() );
		$this->assertTrue( $this->evaluate( '8.2.0', '6.5-RC1', true )->is_satisfied() );
	}

	/**
	 * Le verdict est immuable : modifier la liste renvoyée ne le change pas.
	 *
	 * @return void
	 */
	public function test_the_verdict_cannot_be_altered_through_its_accessor(): void {
		$verdict = $this->evaluate( '8.0.30', '6.7', true );

		$missing   = $verdict->missing();
		$missing[] = 'Ajouté après coup';

		$this->assertSame( array( 'PHP trop ancien' ), $verdict->missing() );
	}

	/**
	 * Un libellé absent de la table ne produit pas d'erreur : la règle reste
	 * utilisable sans traductions, ce dont dépend son testabilité.
	 *
	 * @return void
	 */
	public function test_missing_labels_fall_back_to_a_key(): void {
		$verdict = Requirements::evaluate( '8.0.0', '6.7', true, '8.1', '6.4', array() );

		$this->assertSame( array( 'php' ), $verdict->missing() );
	}
}
