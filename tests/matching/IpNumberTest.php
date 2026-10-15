<?php
/**
 * Tests de l'entier de longueur fixe.
 *
 * Toute la décision du §4.3 se ramène à une comparaison sur l'adresse convertie
 * en entier de longueur fixe : 32 bits en IPv4, 128 bits en IPv6. PHP n'a pas
 * d'entier de 128 bits, et l'arithmétique en virgule flottante perdrait des
 * bits en silence — exactement le genre de perte qui accorde un accès à qui ne
 * devait pas l'avoir. La représentation retenue est donc la forme binaire de
 * longueur fixe que produit `inet_pton()`, et la comparaison est lexicographique
 * sur ces octets : pour des chaînes de même longueur en gros-boutien, l'ordre
 * lexicographique est l'ordre numérique.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Matching;

use Ip_Restriction_For_Restrict_Content\Address\Ip_Number;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Family;
use WP_UnitTestCase;

/**
 * Conversion, comparaison et bornes.
 */
final class IpNumberTest extends WP_UnitTestCase {

	/**
	 * Une adresse IPv4 pointée est convertie, et sa famille reconnue.
	 *
	 * @return void
	 */
	public function test_an_ipv4_address_is_converted(): void {
		$number = Ip_Number::from_address( '192.0.2.1' );

		$this->assertInstanceOf( Ip_Number::class, $number );
		$this->assertSame( Rule_Family::IPV4, $number->family() );
		$this->assertSame( 4, strlen( $number->packed() ) );
	}

	/**
	 * Une adresse IPv6, compressée ou non, est convertie.
	 *
	 * @return void
	 */
	public function test_an_ipv6_address_is_converted(): void {
		$compressed = Ip_Number::from_address( '2001:db8::1' );
		$full       = Ip_Number::from_address( '2001:0db8:0000:0000:0000:0000:0000:0001' );

		$this->assertInstanceOf( Ip_Number::class, $compressed );
		$this->assertInstanceOf( Ip_Number::class, $full );
		$this->assertSame( Rule_Family::IPV6, $compressed->family() );
		$this->assertTrue( $compressed->equals( $full ), 'Les deux écritures désignent la même adresse.' );
	}

	/**
	 * Une forme mixte IPv4 dans IPv6 est normalisée en IPv6 (ADR-0005).
	 *
	 * @return void
	 */
	public function test_a_mapped_ipv4_address_is_normalised_to_ipv6(): void {
		$number = Ip_Number::from_address( '::ffff:192.0.2.1' );

		$this->assertInstanceOf( Ip_Number::class, $number );
		$this->assertSame( Rule_Family::IPV6, $number->family() );
	}

	/**
	 * Ce qui n'est pas une adresse n'est pas converti.
	 *
	 * @return void
	 */
	public function test_what_is_not_an_address_is_not_converted(): void {
		foreach ( array( '', ' ', '10.1', '10.0.0.256', 'exemple.test', '192.0.2.1 ', '*' ) as $candidate ) {
			$this->assertNull(
				Ip_Number::from_address( $candidate ),
				sprintf( '« %s » n\'est pas une adresse et ne doit pas être convertie.', $candidate )
			);
		}
	}

	/**
	 * Un octet à zéro de tête est refusé.
	 *
	 * `inet_pton()` lit `010.0.0.1` comme `10.0.0.1`, là où une bibliothèque
	 * qui interprète l'octal y lit `8.0.0.1`. C'est précisément le cas où
	 * l'auteur de la règle et la machine peuvent ne pas s'accorder, et FNC-20
	 * tranche : ce que l'analyseur ne comprend pas sans ambiguïté est rejeté,
	 * jamais interprété par approximation.
	 *
	 * @return void
	 */
	public function test_an_octet_with_a_leading_zero_is_refused(): void {
		$this->assertNull( Ip_Number::from_address( '010.0.0.1' ) );
		$this->assertNull( Ip_Number::from_address( '10.0.0.01' ) );
		$this->assertInstanceOf( Ip_Number::class, Ip_Number::from_address( '10.0.0.0' ) );
	}

	/**
	 * L'ordre des octets est l'ordre numérique.
	 *
	 * Le test porte sur une frontière d'octet, là où une comparaison
	 * lexicographique fautive se trahirait : `10.0.0.255` précède `10.0.1.0`.
	 *
	 * @return void
	 */
	public function test_the_byte_order_is_the_numeric_order(): void {
		$low  = Ip_Number::from_address( '10.0.0.255' );
		$high = Ip_Number::from_address( '10.0.1.0' );

		$this->assertLessThan( 0, $low->compare( $high ) );
		$this->assertGreaterThan( 0, $high->compare( $low ) );
		$this->assertSame( 0, $low->compare( Ip_Number::from_address( '10.0.0.255' ) ) );
	}

	/**
	 * L'appartenance à un intervalle est inclusive aux deux bornes (FNC-15).
	 *
	 * @return void
	 */
	public function test_an_interval_includes_both_bounds(): void {
		$low  = Ip_Number::from_address( '192.0.2.10' );
		$high = Ip_Number::from_address( '192.0.2.20' );

		$this->assertTrue( Ip_Number::from_address( '192.0.2.10' )->is_between( $low, $high ) );
		$this->assertTrue( Ip_Number::from_address( '192.0.2.20' )->is_between( $low, $high ) );
		$this->assertTrue( Ip_Number::from_address( '192.0.2.15' )->is_between( $low, $high ) );
		$this->assertFalse( Ip_Number::from_address( '192.0.2.9' )->is_between( $low, $high ) );
		$this->assertFalse( Ip_Number::from_address( '192.0.2.21' )->is_between( $low, $high ) );
	}

	/**
	 * Deux familles différentes ne se comparent pas : elles ne correspondent
	 * simplement pas (§4.3).
	 *
	 * Renvoyer `false` plutôt que lever : une règle IPv4 qui rencontre un
	 * visiteur IPv6 est un cas d'exploitation courant sur un site dual-stack,
	 * pas une anomalie de programmation.
	 *
	 * @return void
	 */
	public function test_two_families_never_match_and_never_throw(): void {
		$v4_low  = Ip_Number::from_address( '0.0.0.0' );
		$v4_high = Ip_Number::from_address( '255.255.255.255' );
		$v6      = Ip_Number::from_address( '2001:db8::1' );

		$this->assertFalse( $v6->is_between( $v4_low, $v4_high ) );
		$this->assertFalse( $v4_low->is_same_family( $v6 ) );
		$this->assertFalse( $v4_low->equals( $v6 ) );
	}

	/**
	 * Les bornes extrêmes de chaque famille sont disponibles.
	 *
	 * Elles servent à G-05 : une règle dont l'intervalle va d'un extrême à
	 * l'autre désigne l'ensemble des adresses.
	 *
	 * @return void
	 */
	public function test_each_family_exposes_its_extremes(): void {
		$this->assertTrue( Ip_Number::min_of( Rule_Family::IPV4 )->equals( Ip_Number::from_address( '0.0.0.0' ) ) );
		$this->assertTrue( Ip_Number::max_of( Rule_Family::IPV4 )->equals( Ip_Number::from_address( '255.255.255.255' ) ) );
		$this->assertTrue( Ip_Number::min_of( Rule_Family::IPV6 )->equals( Ip_Number::from_address( '::' ) ) );
		$this->assertTrue(
			Ip_Number::max_of( Rule_Family::IPV6 )->equals(
				Ip_Number::from_address( 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff' )
			)
		);
	}

	/**
	 * L'adresse se relit sous sa forme textuelle normalisée.
	 *
	 * C'est ce que FNC-29 affichera, et ce que les messages de diagnostic
	 * citeront après expurgation (SEC-06).
	 *
	 * @return void
	 */
	public function test_an_address_reads_back_in_its_normalised_form(): void {
		$this->assertSame( '192.0.2.1', Ip_Number::from_address( '192.0.2.1' )->to_string() );
		$this->assertSame( '2001:db8::1', Ip_Number::from_address( '2001:0db8::0001' )->to_string() );
	}
}
