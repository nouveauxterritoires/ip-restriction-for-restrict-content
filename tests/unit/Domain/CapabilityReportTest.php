<?php
/**
 * Tests du rapport de capacités.
 *
 * Le rapport porte la distinction qui évite un avis faux : une capacité que la
 * dépendance ne charge qu'en administration n'est pas absente sur une requête
 * de façade, elle n'y est pas observable. Sans cette nuance, chaque requête de
 * façade annoncerait la perte de la portée par contenu, qui est intacte.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Domain;

use Ip_Restriction_For_Restrict_Content\Domain\Capability;
use Ip_Restriction_For_Restrict_Content\Domain\Capability_Report;
use Ip_Restriction_For_Restrict_Content\Tests\Unit\UnitTestCase;

/**
 * Qualification d'un catalogue par des observations.
 */
final class CapabilityReportTest extends UnitTestCase {

	/**
	 * Capacité de façade, rattachée à la fonctionnalité « alpha ».
	 *
	 * @return Capability
	 */
	private function front_capability(): Capability {
		return new Capability(
			'rcipr_test_front',
			Capability::FUNCTION_SYMBOL,
			'alpha',
			Capability::CONTEXT_ANY,
			'un_filtre',
			'fichier/amont.php:12'
		);
	}

	/**
	 * Capacité que la dépendance ne charge qu'en administration, rattachée à
	 * la fonctionnalité « beta ».
	 *
	 * @return Capability
	 */
	private function admin_capability(): Capability {
		return new Capability(
			'rcipr_test_admin',
			Capability::FUNCTION_SYMBOL,
			'beta',
			Capability::CONTEXT_ADMIN,
			'une_action',
			'fichier/amont-admin.php:34'
		);
	}

	/**
	 * Une capacité observée présente est rapportée présente.
	 *
	 * @return void
	 */
	public function test_an_observed_symbol_is_reported_present(): void {
		$front  = $this->front_capability();
		$report = Capability_Report::from_observations(
			array( $front ),
			array( 'rcipr_test_front' => true ),
			false
		);

		$this->assertSame( Capability_Report::PRESENT, $report->state_of( $front ) );
		$this->assertSame( array(), $report->missing_symbols() );
		$this->assertTrue( $report->is_feature_available( 'alpha' ) );
		$this->assertTrue( $report->is_complete() );
	}

	/**
	 * Une capacité observable et absente est rapportée absente, et la
	 * fonctionnalité qui en dépend devient indisponible.
	 *
	 * @return void
	 */
	public function test_a_missing_observable_symbol_disables_its_feature(): void {
		$front  = $this->front_capability();
		$report = Capability_Report::from_observations(
			array( $front ),
			array( 'rcipr_test_front' => false ),
			false
		);

		$this->assertSame( Capability_Report::MISSING, $report->state_of( $front ) );
		$this->assertSame( array( 'rcipr_test_front' ), $report->missing_symbols() );
		$this->assertFalse( $report->is_feature_available( 'alpha' ) );
		$this->assertSame( array( 'alpha' ), $report->unavailable_features() );
		$this->assertFalse( $report->is_complete() );
	}

	/**
	 * Hors administration, une capacité d'administration n'est ni présente ni
	 * absente : elle n'est pas observable, et sa fonctionnalité n'est pas
	 * déclarée perdue.
	 *
	 * @return void
	 */
	public function test_an_admin_only_symbol_is_not_missing_on_a_front_request(): void {
		$admin  = $this->admin_capability();
		$report = Capability_Report::from_observations(
			array( $admin ),
			array( 'rcipr_test_admin' => false ),
			false
		);

		$this->assertSame( Capability_Report::UNOBSERVABLE, $report->state_of( $admin ) );
		$this->assertSame( array(), $report->missing_symbols() );
		$this->assertTrue( $report->is_feature_available( 'beta' ) );
		$this->assertSame( array(), $report->unavailable_features() );
	}

	/**
	 * En administration, la même capacité absente est bien rapportée absente.
	 *
	 * @return void
	 */
	public function test_the_same_symbol_is_missing_on_an_admin_request(): void {
		$admin  = $this->admin_capability();
		$report = Capability_Report::from_observations(
			array( $admin ),
			array( 'rcipr_test_admin' => false ),
			true
		);

		$this->assertSame( Capability_Report::MISSING, $report->state_of( $admin ) );
		$this->assertSame( array( 'rcipr_test_admin' ), $report->missing_symbols() );
		$this->assertFalse( $report->is_feature_available( 'beta' ) );
	}

	/**
	 * Une fonctionnalité absente n'en désactive pas une autre : c'est E-04.
	 *
	 * @return void
	 */
	public function test_one_lost_feature_does_not_disable_the_others(): void {
		$report = Capability_Report::from_observations(
			array( $this->front_capability(), $this->admin_capability() ),
			array(
				'rcipr_test_front' => false,
				'rcipr_test_admin' => true,
			),
			true
		);

		$this->assertFalse( $report->is_feature_available( 'alpha' ) );
		$this->assertTrue( $report->is_feature_available( 'beta' ) );
		$this->assertSame( array( 'alpha' ), $report->unavailable_features() );
	}

	/**
	 * Une observation absente du tableau vaut absence, et non présence : c'est
	 * la fermeture sur l'inconnu appliquée à la sonde.
	 *
	 * @return void
	 */
	public function test_an_unobserved_symbol_counts_as_missing(): void {
		$front  = $this->front_capability();
		$report = Capability_Report::from_observations( array( $front ), array(), false );

		$this->assertSame( Capability_Report::MISSING, $report->state_of( $front ) );
	}

	/**
	 * Le rapport ne peut pas être altéré par son accesseur.
	 *
	 * @return void
	 */
	public function test_the_report_cannot_be_altered_through_its_accessor(): void {
		$report       = Capability_Report::from_observations(
			array( $this->front_capability() ),
			array( 'rcipr_test_front' => true ),
			false
		);
		$capabilities = $report->capabilities();

		$capabilities[] = $this->admin_capability();

		$this->assertCount( 1, $report->capabilities() );
	}

	/**
	 * Une fonctionnalité privée de deux capacités n'est nommée qu'une fois.
	 *
	 * L'avis de E-04 nomme des fonctionnalités, pas des symboles : la répéter
	 * autant de fois qu'elle a de capacités absentes donnerait un avis illisible
	 * là où il doit être précis.
	 *
	 * @return void
	 */
	public function test_a_feature_missing_two_capabilities_is_named_once(): void {
		$first  = new Capability(
			'rcipr_test_one',
			Capability::FUNCTION_SYMBOL,
			'alpha',
			Capability::CONTEXT_ANY,
			'',
			'fichier/amont.php:1'
		);
		$second = new Capability(
			'rcipr_test_two',
			Capability::FUNCTION_SYMBOL,
			'alpha',
			Capability::CONTEXT_ANY,
			'',
			'fichier/amont.php:2'
		);

		$report = Capability_Report::from_observations(
			array( $first, $second ),
			array(
				'rcipr_test_one' => false,
				'rcipr_test_two' => false,
			),
			false
		);

		$this->assertSame( array( 'alpha' ), $report->unavailable_features() );
		$this->assertSame( array( 'rcipr_test_one', 'rcipr_test_two' ), $report->missing_symbols() );
	}
}
