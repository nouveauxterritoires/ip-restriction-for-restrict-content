<?php
/**
 * Tests de la politique de blocage d'inscription — FNC-07 à FNC-09, A-01.
 *
 * Cette politique est une classe **distincte** de celle de l'accès, et c'est
 * A-01 : les deux listes ne partagent ni stockage, ni cache, ni chemin de
 * décision. Elles se ressemblent — deux champs de texte, la même grammaire, le
 * même moteur — et n'ont pas du tout les mêmes conséquences en cas d'erreur.
 * Une erreur par excès sur la liste d'autorisation est une fuite d'accès
 * silencieuse ; la même sur la liste de blocage refuse des inscriptions
 * légitimes, ce qui est bruyant. Les confondre reviendrait à prendre le risque
 * de la première pour le confort de la seconde.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Matching;

use Ip_Restriction_For_Restrict_Content\Address\Ip_Number;
use Ip_Restriction_For_Restrict_Content\Policy\Registration_Decision;
use Ip_Restriction_For_Restrict_Content\Policy\Registration_Policy;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Set;
use WP_UnitTestCase;

/**
 * Décision de blocage sur la liste de blocage globale.
 */
final class RegistrationPolicyTest extends WP_UnitTestCase {

	/**
	 * Raccourci de conversion.
	 *
	 * @param string $address Adresse littérale.
	 * @return Ip_Number
	 */
	private function number( string $address ): Ip_Number {
		$number = Ip_Number::from_address( $address );

		$this->assertInstanceOf( Ip_Number::class, $number );

		return $number;
	}

	/**
	 * Une adresse de la liste de blocage est bloquée (FNC-07).
	 *
	 * @return void
	 */
	public function test_an_address_on_the_block_list_is_blocked(): void {
		$decision = Registration_Policy::decide(
			$this->number( '203.0.113.9' ),
			Rule_Set::from_text( "203.0.113.0/24\n" )
		);

		$this->assertTrue( $decision->is_blocked() );
		$this->assertSame( '203.0.113.0/24', $decision->rule()->source(), 'La règle responsable est portée pour le diagnostic.' );
	}

	/**
	 * Une adresse absente de la liste n'est pas bloquée.
	 *
	 * @return void
	 */
	public function test_an_address_outside_the_list_is_allowed(): void {
		$decision = Registration_Policy::decide(
			$this->number( '198.51.100.9' ),
			Rule_Set::from_text( "203.0.113.0/24\n" )
		);

		$this->assertFalse( $decision->is_blocked() );
		$this->assertNull( $decision->rule() );
	}

	/**
	 * Une liste vide ne bloque personne (FNC-27).
	 *
	 * @return void
	 */
	public function test_an_empty_list_blocks_nobody(): void {
		$this->assertFalse(
			Registration_Policy::decide( $this->number( '203.0.113.9' ), Rule_Set::empty() )->is_blocked()
		);
	}

	/**
	 * Une adresse indéterminable n'est pas bloquée (R-05, §4.5).
	 *
	 * C'est la face **inconfortable** de la fermeture sur l'inconnu : sur la
	 * liste de blocage, la défaillance laisse passer une inscription
	 * indésirable, et personne ne s'en aperçoit. Le §4.6 l'assume
	 * explicitement, parce que l'alternative — bloquer sur une adresse
	 * inconnue — refuserait des inscriptions légitimes en masse dès qu'un
	 * proxy est mal déclaré.
	 *
	 * @return void
	 */
	public function test_an_undetermined_address_is_not_blocked(): void {
		$this->assertFalse(
			Registration_Policy::decide( null, Rule_Set::from_text( "0.0.0.0/1\n" ) )->is_blocked()
		);
	}

	/**
	 * Une liste entièrement illisible ne bloque personne (FNC-23).
	 *
	 * @return void
	 */
	public function test_a_fully_invalid_list_blocks_nobody(): void {
		$this->assertFalse(
			Registration_Policy::decide(
				$this->number( '203.0.113.9' ),
				Rule_Set::from_text( "exemple.test\n*\n" )
			)->is_blocked()
		);
	}

	/**
	 * La politique de blocage et celle d'accès n'ont aucun chemin commun
	 * (A-01).
	 *
	 * Le test porte sur la forme du code, parce que c'est là que la confusion
	 * se produirait : une classe commune paramétrée par un nom de liste, ou
	 * une politique qui recevrait les deux listes et choisirait.
	 *
	 * @return void
	 */
	public function test_the_two_policies_share_no_path(): void {
		$parameters = ( new \ReflectionMethod( Registration_Policy::class, 'decide' ) )->getParameters();
		$names      = array();

		foreach ( $parameters as $parameter ) {
			$names[] = $parameter->getName();
		}

		$this->assertSame( array( 'address', 'block_list' ), $names );
		$this->assertFalse(
			method_exists( Registration_Policy::class, 'decide_access' ),
			'La politique de blocage ne doit pas savoir décider d\'un accès.'
		);
	}

	/**
	 * Une décision de blocage porte sa règle, une décision libre n'en porte
	 * aucune.
	 *
	 * @return void
	 */
	public function test_a_decision_carries_its_rule_or_none(): void {
		$rule = Rule_Set::from_text( '203.0.113.9' )->rules()[0];

		$blocked = Registration_Decision::blocked_with( $rule );

		$this->assertTrue( $blocked->is_blocked() );
		$this->assertSame( $rule, $blocked->rule() );

		$allowed = Registration_Decision::allowed();

		$this->assertFalse( $allowed->is_blocked() );
		$this->assertNull( $allowed->rule() );
	}
}
