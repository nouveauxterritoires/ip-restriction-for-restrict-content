<?php
/**
 * Tests de l'expurgation avant journalisation — SEC-06.
 *
 * L'adresse d'un visiteur est une donnée personnelle, et ce plugin n'a aucune
 * finalité de conservation. Elle n'est donc jamais persistée, et jamais écrite
 * en clair dans un message : ni dans un avis d'administration, ni dans un
 * argument d'action que l'exploitant pourrait journaliser sans y penser.
 *
 * Expurger n'est pas supprimer : ce qui sert au diagnostic doit rester. Le
 * réseau est ce qui permet à un administrateur de reconnaître « c'est bien un
 * visiteur de notre siège » ; l'hôte est ce qui désigne une personne.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Support;

use Ip_Restriction_For_Restrict_Content\Address\Client_Address;
use Ip_Restriction_For_Restrict_Content\Address\Ip_Number;
use Ip_Restriction_For_Restrict_Content\Support\Redactor;
use Ip_Restriction_For_Restrict_Content\Tests\Unit\UnitTestCase;

/**
 * Masquage de la partie identifiante d'une adresse.
 */
final class RedactorTest extends UnitTestCase {

	/**
	 * En IPv4, le dernier octet est masqué et le réseau conservé.
	 *
	 * @return void
	 */
	public function test_an_ipv4_address_keeps_its_network_and_loses_its_host(): void {
		$this->assertSame( '198.51.100.x', Redactor::redact( Ip_Number::from_address( '198.51.100.9' ) ) );
		$this->assertSame( '192.0.2.x', Redactor::redact( Ip_Number::from_address( '192.0.2.255' ) ) );
	}

	/**
	 * En IPv6, les 64 bits bas sont masqués et le préfixe conservé.
	 *
	 * Les 64 bits bas sont la partie qui identifie l'interface — souvent
	 * dérivée de l'adresse matérielle, parfois stable des années. Les 64 bits
	 * hauts désignent le réseau, qui est ce dont le diagnostic a besoin.
	 *
	 * @return void
	 */
	public function test_an_ipv6_address_keeps_its_prefix_and_loses_its_interface(): void {
		$this->assertSame(
			'2001:db8:1:2::/64',
			Redactor::redact( Ip_Number::from_address( '2001:db8:1:2:3:4:5:6' ) )
		);
		$this->assertSame(
			'2001:db8::/64',
			Redactor::redact( Ip_Number::from_address( '2001:db8::1' ) )
		);
	}

	/**
	 * Une adresse absente ne produit aucune fuite, et reste lisible.
	 *
	 * @return void
	 */
	public function test_an_absent_address_is_named_without_leaking(): void {
		$this->assertSame( '(adresse indéterminable)', Redactor::redact( null ) );
	}

	/**
	 * L'expurgation d'un objet d'adresse passe par la même règle.
	 *
	 * C'est la forme que les actions du §8.2 transporteront : un appelant qui
	 * journalise ce qu'il reçoit ne doit pas avoir à savoir qu'il faut
	 * expurger.
	 *
	 * @return void
	 */
	public function test_a_client_address_is_redacted_by_the_same_rule(): void {
		$known = Client_Address::from_connection( Ip_Number::from_address( '198.51.100.9' ) );

		$this->assertSame( '198.51.100.x', Redactor::redact_address( $known ) );
		$this->assertSame(
			'(adresse indéterminable)',
			Redactor::redact_address( Client_Address::undetermined( Client_Address::MISSING_HEADER ) )
		);
	}

	/**
	 * Aucune forme expurgée ne contient l'adresse complète.
	 *
	 * Le test est écrit comme une propriété et non comme un cas : c'est la
	 * seule formulation qui attrape une expurgation qui oublierait un cas de
	 * figure.
	 *
	 * @return void
	 */
	public function test_no_redacted_form_contains_the_full_address(): void {
		foreach ( array( '198.51.100.9', '192.0.2.1', '2001:db8:1:2:3:4:5:6', '2001:db8::1' ) as $literal ) {
			$redacted = Redactor::redact( Ip_Number::from_address( $literal ) );

			$this->assertStringNotContainsString(
				$literal,
				$redacted,
				sprintf( 'La forme expurgée de %s ne doit pas contenir l\'adresse complète.', $literal )
			);
		}
	}
}
