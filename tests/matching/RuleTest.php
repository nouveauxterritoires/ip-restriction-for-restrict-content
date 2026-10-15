<?php
/**
 * Tests de la normalisation d'une règle en intervalle borné.
 *
 * Le tableau du cahier des charges §4.3 est la spécification de ce fichier :
 * les quatre formes se ramènent au même objet — un intervalle borné dans une
 * famille —, et c'est ce qui rend la combinaison joker + plage définissable.
 *
 * Les quatre fabriques portent la normalisation, et non l'appelant. Si
 * l'analyseur calculait les bornes, chaque forme aurait sa propre
 * arithmétique, et une faute sur l'une n'aurait aucune chance d'être attrapée
 * par les tests de l'autre.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Matching;

use Ip_Restriction_For_Restrict_Content\Address\Ip_Number;
use Ip_Restriction_For_Restrict_Content\Rule\Rule;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Family;
use WP_UnitTestCase;

/**
 * Normalisation et correspondance d'une règle seule.
 */
final class RuleTest extends WP_UnitTestCase {

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
	 * Affirme les bornes d'une règle par leur forme textuelle.
	 *
	 * @param Rule   $rule     Règle normalisée.
	 * @param string $expected_low  Borne basse attendue.
	 * @param string $expected_high Borne haute attendue.
	 * @return void
	 */
	private function assert_bounds( Rule $rule, string $expected_low, string $expected_high ): void {
		$this->assertSame( $expected_low, $rule->low()->to_string(), 'Borne basse.' );
		$this->assertSame( $expected_high, $rule->high()->to_string(), 'Borne haute.' );
	}

	/**
	 * Un littéral se normalise en un intervalle d'une seule adresse.
	 *
	 * @return void
	 */
	public function test_a_literal_normalises_to_a_single_address(): void {
		$rule = Rule::literal( $this->number( '226.216.136.78' ), '226.216.136.78' );

		$this->assert_bounds( $rule, '226.216.136.78', '226.216.136.78' );
		$this->assertSame( Rule_Family::IPV4, $rule->family() );
		$this->assertSame( '226.216.136.78', $rule->source() );
		$this->assertTrue( $rule->matches( $this->number( '226.216.136.78' ) ) );
		$this->assertFalse( $rule->matches( $this->number( '226.216.136.79' ) ) );
	}

	/**
	 * Un joker porte ses segments jokers à zéro en bas, au maximum en haut.
	 *
	 * @return void
	 */
	public function test_a_wildcard_spans_its_joker_segments(): void {
		$rule = Rule::wildcard( array( 200, 122, 216, null ), '200.122.216.*' );

		$this->assertInstanceOf( Rule::class, $rule );
		$this->assert_bounds( $rule, '200.122.216.0', '200.122.216.255' );
		$this->assertTrue( $rule->matches( $this->number( '200.122.216.0' ) ) );
		$this->assertTrue( $rule->matches( $this->number( '200.122.216.255' ) ) );
		$this->assertFalse( $rule->matches( $this->number( '200.122.215.255' ) ) );
		$this->assertFalse( $rule->matches( $this->number( '200.122.217.0' ) ) );
	}

	/**
	 * Un joker sur trois segments couvre les trois.
	 *
	 * @return void
	 */
	public function test_a_wildcard_may_span_three_segments(): void {
		$rule = Rule::wildcard( array( 186, null, null, null ), '186.*.*.*' );

		$this->assertInstanceOf( Rule::class, $rule );
		$this->assert_bounds( $rule, '186.0.0.0', '186.255.255.255' );
	}

	/**
	 * Un CIDR se normalise en adresse de réseau et adresse de diffusion.
	 *
	 * Les préfixes testés sont les frontières : 0 est refusé par ailleurs
	 * (G-05), 1 et 31 encadrent le cas courant, 32 désigne une seule adresse,
	 * et 24 est l'exemple documenté.
	 *
	 * @return void
	 */
	public function test_a_cidr_normalises_to_network_and_broadcast(): void {
		$this->assert_bounds( Rule::cidr( $this->number( '10.0.0.0' ), 24, '10.0.0.0/24' ), '10.0.0.0', '10.0.0.255' );
		$this->assert_bounds( Rule::cidr( $this->number( '10.0.0.0' ), 32, '10.0.0.0/32' ), '10.0.0.0', '10.0.0.0' );
		$this->assert_bounds( Rule::cidr( $this->number( '10.0.0.0' ), 31, '10.0.0.0/31' ), '10.0.0.0', '10.0.0.1' );
		$this->assert_bounds( Rule::cidr( $this->number( '10.0.0.0' ), 1, '10.0.0.0/1' ), '0.0.0.0', '127.255.255.255' );
		$this->assert_bounds( Rule::cidr( $this->number( '192.0.2.128' ), 25, '192.0.2.128/25' ), '192.0.2.128', '192.0.2.255' );
	}

	/**
	 * Un CIDR dont les bits d'hôte ne sont pas nuls est normalisé sur son
	 * réseau, et non refusé.
	 *
	 * `192.0.2.5/24` désigne sans ambiguïté le réseau `192.0.2.0/24` : le
	 * refuser ferait perdre une saisie compréhensible, là où la normaliser ne
	 * perd rien.
	 *
	 * @return void
	 */
	public function test_a_cidr_with_host_bits_is_normalised_to_its_network(): void {
		$this->assert_bounds( Rule::cidr( $this->number( '192.0.2.5' ), 24, '192.0.2.5/24' ), '192.0.2.0', '192.0.2.255' );
	}

	/**
	 * Un CIDR IPv6 se normalise sur 128 bits.
	 *
	 * @return void
	 */
	public function test_an_ipv6_cidr_normalises_over_128_bits(): void {
		$rule = Rule::cidr( $this->number( '2001:db8::' ), 32, '2001:db8::/32' );

		$this->assert_bounds( $rule, '2001:db8::', '2001:db8:ffff:ffff:ffff:ffff:ffff:ffff' );
		$this->assertSame( Rule_Family::IPV6, $rule->family() );
		$this->assertTrue( $rule->matches( $this->number( '2001:db8:1234::1' ) ) );
		$this->assertFalse( $rule->matches( $this->number( '2001:db9::1' ) ) );
	}

	/**
	 * Un préfixe hors bornes ne produit pas de règle.
	 *
	 * L'analyseur refuse déjà ces entrées par G-03 ; la fabrique refuse aussi,
	 * pour qu'aucun chemin ne puisse fabriquer un intervalle faux.
	 *
	 * @return void
	 */
	public function test_a_prefix_out_of_range_produces_no_rule(): void {
		$this->assertNull( Rule::cidr( $this->number( '10.0.0.0' ), 33, '10.0.0.0/33' ) );
		$this->assertNull( Rule::cidr( $this->number( '10.0.0.0' ), -1, '10.0.0.0/-1' ) );
		$this->assertNull( Rule::cidr( $this->number( '2001:db8::' ), 129, '2001:db8::/129' ) );
	}

	/**
	 * Une plage entre deux littéraux est inclusive (FNC-15).
	 *
	 * @return void
	 */
	public function test_a_range_between_two_literals_is_inclusive(): void {
		$rule = Rule::range(
			Rule::literal( $this->number( '159.7.89.231' ), '159.7.89.231' ),
			Rule::literal( $this->number( '159.7.89.240' ), '159.7.89.240' ),
			'159.7.89.231-159.7.89.240'
		);

		$this->assertInstanceOf( Rule::class, $rule );
		$this->assert_bounds( $rule, '159.7.89.231', '159.7.89.240' );
		$this->assertTrue( $rule->matches( $this->number( '159.7.89.231' ) ) );
		$this->assertTrue( $rule->matches( $this->number( '159.7.89.240' ) ) );
		$this->assertFalse( $rule->matches( $this->number( '159.7.89.230' ) ) );
		$this->assertFalse( $rule->matches( $this->number( '159.7.89.241' ) ) );
	}

	/**
	 * Une plage à bornes jokers prend le minimum à gauche et le maximum à
	 * droite.
	 *
	 * C'est la définition de FNC-17, et la seule lecture compatible avec le
	 * caractère inclusif des plages. Elle porte le point ouvert O-06 : la
	 * documentation amont donne l'exemple sans le définir.
	 *
	 * @return void
	 */
	public function test_a_range_of_wildcards_takes_the_low_left_and_the_high_right(): void {
		$rule = Rule::range(
			Rule::wildcard( array( 149, 58, null, null ), '149.58.*' ),
			Rule::wildcard( array( 149, 60, null, null ), '149.60.*' ),
			'149.58.*-149.60.*'
		);

		$this->assertInstanceOf( Rule::class, $rule );
		$this->assert_bounds( $rule, '149.58.0.0', '149.60.255.255' );
		$this->assertTrue( $rule->matches( $this->number( '149.58.0.0' ) ) );
		$this->assertTrue( $rule->matches( $this->number( '149.59.1.1' ) ) );
		$this->assertTrue( $rule->matches( $this->number( '149.60.255.255' ) ) );
		$this->assertFalse( $rule->matches( $this->number( '149.57.255.255' ) ) );
		$this->assertFalse( $rule->matches( $this->number( '149.61.0.0' ) ) );
	}

	/**
	 * Une plage dont les bornes relèvent de familles différentes ne produit
	 * pas de règle (G-01).
	 *
	 * @return void
	 */
	public function test_a_range_across_families_produces_no_rule(): void {
		$this->assertNull(
			Rule::range(
				Rule::literal( $this->number( '10.0.0.1' ), '10.0.0.1' ),
				Rule::literal( $this->number( '::1' ), '::1' ),
				'10.0.0.1-::1'
			)
		);
	}

	/**
	 * Une plage à l'envers ne produit pas de règle (G-02).
	 *
	 * @return void
	 */
	public function test_an_inverted_range_produces_no_rule(): void {
		$this->assertNull(
			Rule::range(
				Rule::literal( $this->number( '10.0.0.9' ), '10.0.0.9' ),
				Rule::literal( $this->number( '10.0.0.1' ), '10.0.0.1' ),
				'10.0.0.9-10.0.0.1'
			)
		);
	}

	/**
	 * Une plage d'une seule adresse est acceptée.
	 *
	 * G-02 exige « inférieure ou égale » : la borne d'égalité doit être du bon
	 * côté du refus.
	 *
	 * @return void
	 */
	public function test_a_range_of_one_address_is_accepted(): void {
		$rule = Rule::range(
			Rule::literal( $this->number( '10.0.0.1' ), '10.0.0.1' ),
			Rule::literal( $this->number( '10.0.0.1' ), '10.0.0.1' ),
			'10.0.0.1-10.0.0.1'
		);

		$this->assertInstanceOf( Rule::class, $rule );
		$this->assert_bounds( $rule, '10.0.0.1', '10.0.0.1' );
	}

	/**
	 * Un joker dont les segments sont tous jokers n'est pas une règle (G-05).
	 *
	 * @return void
	 */
	public function test_a_wildcard_over_every_segment_is_not_a_rule(): void {
		$this->assertNull( Rule::wildcard( array( null, null, null, null ), '*.*.*.*' ) );
	}

	/**
	 * Une règle qui désigne l'ensemble des adresses se reconnaît à son
	 * intervalle.
	 *
	 * G-05 est énoncé sur des formes — `*`, `*.*.*.*`, `0.0.0.0/0`, `::/0` —
	 * mais la propriété dangereuse est sémantique : un intervalle qui va d'un
	 * extrême à l'autre ouvre tout le contenu restreint, quelle que soit
	 * l'écriture qui l'a produit. Le contrôle porte donc sur l'intervalle, ce
	 * qui couvre aussi `0.0.0.0-255.255.255.255`, que la lettre du §4.2 ne
	 * nommait pas.
	 *
	 * @return void
	 */
	public function test_a_rule_covering_every_address_is_refused_whatever_its_form(): void {
		$this->assertNull( Rule::cidr( $this->number( '0.0.0.0' ), 0, '0.0.0.0/0' ) );
		$this->assertNull( Rule::cidr( $this->number( '::' ), 0, '::/0' ) );
		$this->assertNull(
			Rule::range(
				Rule::literal( $this->number( '0.0.0.0' ), '0.0.0.0' ),
				Rule::literal( $this->number( '255.255.255.255' ), '255.255.255.255' ),
				'0.0.0.0-255.255.255.255'
			)
		);
	}

	/**
	 * Une règle ne correspond jamais à une adresse d'une autre famille (§4.3).
	 *
	 * @return void
	 */
	public function test_a_rule_never_matches_another_family(): void {
		$v4 = Rule::cidr( $this->number( '0.0.0.0' ), 1, '0.0.0.0/1' );
		$v6 = Rule::cidr( $this->number( '::' ), 1, '::/1' );

		$this->assertFalse( $v4->matches( $this->number( '2001:db8::1' ) ) );
		$this->assertFalse( $v6->matches( $this->number( '10.0.0.1' ) ) );
	}

	/**
	 * Un segment de joker hors bornes ne produit pas de règle.
	 *
	 * @return void
	 */
	public function test_a_wildcard_segment_out_of_range_produces_no_rule(): void {
		$this->assertNull( Rule::wildcard( array( 256, 0, 0, null ), '256.0.0.*' ) );
		$this->assertNull( Rule::wildcard( array( -1, 0, 0, null ), '-1.0.0.*' ) );
		$this->assertNull( Rule::wildcard( array( 10, 0, null ), '10.0.*' ) );
	}
}
