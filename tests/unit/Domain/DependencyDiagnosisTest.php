<?php
/**
 * Tests du diagnostic de la dépendance.
 *
 * Trois états, et c'est tout l'enjeu : « absente » et « active sur sa branche
 * historique » conduisent au même verdict et pas au même avis (E-05). Un
 * diagnostic qui les confondrait produirait un avis que l'administrateur
 * croirait faux, puisque l'extension figure, active, dans sa liste.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Domain;

use Ip_Restriction_For_Restrict_Content\Domain\Dependency_Diagnosis;
use Ip_Restriction_For_Restrict_Content\Tests\Unit\UnitTestCase;

/**
 * Qualification des trois états.
 */
final class DependencyDiagnosisTest extends UnitTestCase {

	/**
	 * L'interface présente suffit : la dépendance est utilisable.
	 *
	 * @return void
	 */
	public function test_the_api_being_present_makes_the_dependency_usable(): void {
		$diagnosis = Dependency_Diagnosis::from_observation( true, true );

		$this->assertTrue( $diagnosis->is_usable() );
		$this->assertFalse( $diagnosis->is_legacy_branch() );
		$this->assertFalse( $diagnosis->is_absent() );
		$this->assertSame( Dependency_Diagnosis::USABLE, $diagnosis->state() );
	}

	/**
	 * L'interface présente suffit même sans le marqueur d'amorçage : c'est le
	 * cas de la variante commerciale, qui n'embarque pas le mécanisme de
	 * bascule de la variante gratuite.
	 *
	 * @return void
	 */
	public function test_the_commercial_variant_is_usable_without_the_bootstrap_marker(): void {
		$diagnosis = Dependency_Diagnosis::from_observation( true, false );

		$this->assertTrue( $diagnosis->is_usable() );
	}

	/**
	 * Interface absente mais amorçage présent : branche historique.
	 *
	 * @return void
	 */
	public function test_a_missing_api_with_a_present_bootstrap_is_the_legacy_branch(): void {
		$diagnosis = Dependency_Diagnosis::from_observation( false, true );

		$this->assertTrue( $diagnosis->is_legacy_branch() );
		$this->assertFalse( $diagnosis->is_usable() );
		$this->assertFalse( $diagnosis->is_absent() );
		$this->assertSame( Dependency_Diagnosis::LEGACY_BRANCH, $diagnosis->state() );
	}

	/**
	 * Rien en mémoire : dépendance absente.
	 *
	 * @return void
	 */
	public function test_nothing_in_memory_is_an_absent_dependency(): void {
		$diagnosis = Dependency_Diagnosis::from_observation( false, false );

		$this->assertTrue( $diagnosis->is_absent() );
		$this->assertFalse( $diagnosis->is_usable() );
		$this->assertFalse( $diagnosis->is_legacy_branch() );
		$this->assertSame( Dependency_Diagnosis::ABSENT, $diagnosis->state() );
	}

	/**
	 * Les trois états sont mutuellement exclusifs, pour les quatre
	 * combinaisons d'observations possibles.
	 *
	 * @return void
	 */
	public function test_the_three_states_are_mutually_exclusive(): void {
		foreach ( array( true, false ) as $api ) {
			foreach ( array( true, false ) as $bootstrap ) {
				$diagnosis = Dependency_Diagnosis::from_observation( $api, $bootstrap );

				$held = array_filter(
					array(
						$diagnosis->is_usable(),
						$diagnosis->is_legacy_branch(),
						$diagnosis->is_absent(),
					)
				);

				$this->assertCount(
					1,
					$held,
					sprintf( 'Observations api=%d bootstrap=%d : un seul état doit être retenu.', (int) $api, (int) $bootstrap )
				);
			}
		}
	}
}
