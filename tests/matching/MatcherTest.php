<?php
/**
 * Tests de la décision sur un ensemble de règles — D-01 à D-05.
 *
 * Le moteur ne décide pas d'un accès : il dit si une adresse correspond à une
 * liste, et par quelle règle. Traduire cette correspondance en accès est
 * l'affaire des portées (§3.1), au lot 5.
 *
 * La fermeture sur l'inconnu du §4.5 se lit ici dans sa forme la plus brute :
 * une adresse indéterminable ne correspond à aucune liste, et une liste dont
 * toute entrée est illisible ne correspond à aucune adresse. Dans les deux cas
 * le moteur répond « non », jamais « peut-être ».
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Matching;

use Ip_Restriction_For_Restrict_Content\Address\Ip_Number;
use Ip_Restriction_For_Restrict_Content\Rule\Match_Result;
use Ip_Restriction_For_Restrict_Content\Rule\Matcher;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Set;
use WP_UnitTestCase;

/**
 * Décision du moteur sur une liste entière.
 */
final class MatcherTest extends WP_UnitTestCase {

	/**
	 * Raccourci de conversion.
	 *
	 * @param string $address Adresse littérale.
	 * @return Ip_Number
	 */
	private function number( string $address ): Ip_Number {
		$number = Ip_Number::from_address( $address );

		$this->assertInstanceOf( Ip_Number::class, $number, $address . ' doit être une adresse.' );

		return $number;
	}

	/**
	 * Une liste correspond si au moins une de ses règles correspond (D-01).
	 *
	 * @return void
	 */
	public function test_a_list_matches_when_one_of_its_rules_matches(): void {
		$set    = Rule_Set::from_text( "192.0.2.1\n198.51.100.0/24\n203.0.113.*\n" );
		$result = Matcher::match( $this->number( '198.51.100.7' ), $set );

		$this->assertTrue( $result->is_match() );
		$this->assertSame( '198.51.100.0/24', $result->rule()->source(), 'Le résultat porte la règle responsable (D-03).' );
	}

	/**
	 * Une liste ne correspond pas quand aucune règle ne correspond.
	 *
	 * @return void
	 */
	public function test_a_list_does_not_match_when_no_rule_matches(): void {
		$set    = Rule_Set::from_text( "192.0.2.1\n198.51.100.0/24\n" );
		$result = Matcher::match( $this->number( '203.0.113.9' ), $set );

		$this->assertFalse( $result->is_match() );
		$this->assertNull( $result->rule() );
	}

	/**
	 * La première règle qui correspond est celle qui est rapportée.
	 *
	 * Le diagnostic de D-03 doit être reproductible : deux règles qui
	 * couvriraient la même adresse ne doivent pas désigner tantôt l'une,
	 * tantôt l'autre.
	 *
	 * @return void
	 */
	public function test_the_first_matching_rule_is_reported(): void {
		$set    = Rule_Set::from_text( "198.51.100.0/24\n198.51.100.7\n" );
		$result = Matcher::match( $this->number( '198.51.100.7' ), $set );

		$this->assertSame( '198.51.100.0/24', $result->rule()->source() );
	}

	/**
	 * Une règle invalide ne correspond à rien et ne gêne pas les autres
	 * (D-02).
	 *
	 * @return void
	 */
	public function test_an_invalid_rule_does_not_prevent_the_others_from_matching(): void {
		$set = Rule_Set::from_text( "exemple.test\n*\n192.0.2.1\n" );

		$this->assertTrue( Matcher::match( $this->number( '192.0.2.1' ), $set )->is_match() );
		$this->assertCount( 2, $set->rejections() );
	}

	/**
	 * Une liste vide ne correspond jamais (D-04).
	 *
	 * @return void
	 */
	public function test_an_empty_list_never_matches(): void {
		$this->assertFalse( Matcher::match( $this->number( '192.0.2.1' ), Rule_Set::empty() )->is_match() );
		$this->assertFalse( Matcher::match( $this->number( '192.0.2.1' ), Rule_Set::from_text( '' ) )->is_match() );
	}

	/**
	 * Une liste dont toute entrée est rejetée se comporte comme une liste vide
	 * (FNC-23).
	 *
	 * @return void
	 */
	public function test_a_fully_invalid_list_never_matches(): void {
		$set = Rule_Set::from_text( "exemple.test\n0.0.0.0/0\n10.*.0.1\n" );

		$this->assertFalse( Matcher::match( $this->number( '10.0.0.1' ), $set )->is_match() );
		$this->assertFalse( Matcher::match( $this->number( '192.0.2.1' ), $set )->is_match() );
	}

	/**
	 * Une adresse indéterminable ne correspond jamais, quelle que soit la
	 * liste (D-05).
	 *
	 * C'est la fermeture sur l'inconnu du §4.5 : sur une liste
	 * d'autorisation, l'accès n'est pas accordé ; sur une liste de blocage, le
	 * blocage n'est pas appliqué.
	 *
	 * @return void
	 */
	public function test_an_undeterminable_address_never_matches(): void {
		$set = Rule_Set::from_text( "192.0.2.1\n0.0.0.0/1\n" );

		$this->assertFalse( Matcher::match( null, $set )->is_match() );
		$this->assertNull( Matcher::match( null, $set )->rule() );
	}

	/**
	 * Une règle d'une autre famille ne correspond pas, et ne produit pas
	 * d'erreur (§4.3).
	 *
	 * Le cas n'est pas théorique : sur un site à double pile, une liste IPv4
	 * rencontre des visiteurs IPv6 à chaque requête.
	 *
	 * @return void
	 */
	public function test_a_list_of_one_family_ignores_the_other(): void {
		$ipv4_list = Rule_Set::from_text( "0.0.0.0/1\n128.0.0.0/1\n" );
		$ipv6_list = Rule_Set::from_text( "::/1\n8000::/1\n" );

		$this->assertFalse( Matcher::match( $this->number( '2001:db8::1' ), $ipv4_list )->is_match() );
		$this->assertFalse( Matcher::match( $this->number( '10.0.0.1' ), $ipv6_list )->is_match() );
		$this->assertTrue( Matcher::match( $this->number( '10.0.0.1' ), $ipv4_list )->is_match() );
		$this->assertTrue( Matcher::match( $this->number( '2001:db8::1' ), $ipv6_list )->is_match() );
	}

	/**
	 * Une liste mêlant les deux familles sert les deux.
	 *
	 * C'est la recommandation d'exploitation d'ADR-0005 : inscrire les deux
	 * familles. Elle doit fonctionner.
	 *
	 * @return void
	 */
	public function test_a_dual_stack_list_serves_both_families(): void {
		$set = Rule_Set::from_text( "192.0.2.1\n2001:db8::1\n" );

		$this->assertTrue( Matcher::match( $this->number( '192.0.2.1' ), $set )->is_match() );
		$this->assertTrue( Matcher::match( $this->number( '2001:db8::1' ), $set )->is_match() );
		$this->assertFalse( Matcher::match( $this->number( '2001:db8::2' ), $set )->is_match() );
	}

	/**
	 * Le résultat d'absence de correspondance ne porte aucune règle.
	 *
	 * @return void
	 */
	public function test_a_no_match_result_carries_no_rule(): void {
		$result = Match_Result::no_match();

		$this->assertFalse( $result->is_match() );
		$this->assertNull( $result->rule() );
	}

	/**
	 * Le résultat de correspondance porte sa règle.
	 *
	 * @return void
	 */
	public function test_a_match_result_carries_its_rule(): void {
		$rule   = Rule_Set::from_text( '192.0.2.1' )->rules()[0];
		$result = Match_Result::matched( $rule );

		$this->assertTrue( $result->is_match() );
		$this->assertSame( $rule, $result->rule() );
	}

	/**
	 * Les bornes d'un intervalle correspondent, et leurs voisines non.
	 *
	 * Le test porte sur la liste entière plutôt que sur une règle seule : une
	 * faute de bord introduite dans le moteur et non dans la règle ne serait
	 * pas attrapée ailleurs.
	 *
	 * @return void
	 */
	public function test_the_bounds_match_and_their_neighbours_do_not(): void {
		$set = Rule_Set::from_text( '198.51.100.10-198.51.100.20' );

		$this->assertTrue( Matcher::match( $this->number( '198.51.100.10' ), $set )->is_match() );
		$this->assertTrue( Matcher::match( $this->number( '198.51.100.20' ), $set )->is_match() );
		$this->assertFalse( Matcher::match( $this->number( '198.51.100.9' ), $set )->is_match() );
		$this->assertFalse( Matcher::match( $this->number( '198.51.100.21' ), $set )->is_match() );
	}
}
