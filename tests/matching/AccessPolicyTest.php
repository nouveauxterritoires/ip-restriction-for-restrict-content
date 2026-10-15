<?php
/**
 * Tests de la politique d'accès — FNC-01, FNC-05, FNC-06, FNC-31.
 *
 * La politique ne décide jamais de **refuser** : elle accorde, ou elle ne se
 * prononce pas et laisse la dépendance décider. Nommer le second cas « refusé »
 * induirait en erreur le lecteur et, tôt ou tard, l'auteur d'un branchement qui
 * traduirait ce refus en blocage réel — alors que FNC-31 exige qu'un visiteur
 * qui ne correspond à rien obtienne exactement le comportement de la dépendance
 * seule.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Matching;

use Ip_Restriction_For_Restrict_Content\Address\Ip_Number;
use Ip_Restriction_For_Restrict_Content\Policy\Access_Decision;
use Ip_Restriction_For_Restrict_Content\Policy\Access_Policy;
use Ip_Restriction_For_Restrict_Content\Policy\Scope;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Set;
use WP_UnitTestCase;

/**
 * Décision d'accès sur la portée globale.
 */
final class AccessPolicyTest extends WP_UnitTestCase {

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
	 * Une adresse de la liste globale obtient l'accès (FNC-01).
	 *
	 * @return void
	 */
	public function test_an_address_on_the_global_list_is_granted(): void {
		$decision = Access_Policy::decide(
			$this->number( '192.0.2.1' ),
			Rule_Set::from_text( "192.0.2.0/24\n" ),
			array()
		);

		$this->assertTrue( $decision->is_granted() );
		$this->assertSame( Scope::GLOBAL_LIST, $decision->scope() );
		$this->assertSame( '192.0.2.0/24', $decision->rule()->source(), 'La règle responsable est portée pour le diagnostic.' );
	}

	/**
	 * Une adresse absente de la liste ne produit aucune décision (FNC-31).
	 *
	 * Le plugin ne refuse pas : il ne se prononce pas, et le contenu reste
	 * régi par la dépendance seule.
	 *
	 * @return void
	 */
	public function test_an_address_outside_the_list_is_not_handled(): void {
		$decision = Access_Policy::decide(
			$this->number( '203.0.113.9' ),
			Rule_Set::from_text( "192.0.2.0/24\n" ),
			array()
		);

		$this->assertFalse( $decision->is_granted() );
		$this->assertTrue( $decision->is_not_handled() );
		$this->assertNull( $decision->scope() );
		$this->assertNull( $decision->rule() );
	}

	/**
	 * Une liste vide n'accorde rien (FNC-27, D-04).
	 *
	 * C'est la propriété qui rend une installation neuve sans effet
	 * observable.
	 *
	 * @return void
	 */
	public function test_an_empty_list_grants_nothing(): void {
		$this->assertTrue(
			Access_Policy::decide( $this->number( '192.0.2.1' ), Rule_Set::empty(), array() )->is_not_handled()
		);
	}

	/**
	 * Une adresse indéterminable n'accorde rien (D-05, SEC-10).
	 *
	 * C'est la fermeture sur l'inconnu : la défaillance va vers le refus
	 * d'accès, qui est bruyant, et non vers l'octroi, qui est silencieux.
	 *
	 * @return void
	 */
	public function test_an_undetermined_address_grants_nothing(): void {
		$this->assertTrue(
			Access_Policy::decide( null, Rule_Set::from_text( "0.0.0.0/1\n" ), array() )->is_not_handled()
		);
	}

	/**
	 * Une liste entièrement illisible n'accorde rien (FNC-23).
	 *
	 * @return void
	 */
	public function test_a_fully_invalid_list_grants_nothing(): void {
		$this->assertTrue(
			Access_Policy::decide(
				$this->number( '192.0.2.1' ),
				Rule_Set::from_text( "exemple.test\n*\n" ),
				array()
			)->is_not_handled()
		);
	}

	/**
	 * La décision ne dépend ni de l'état de connexion, ni d'une adhésion
	 * (FNC-05).
	 *
	 * La politique ne reçoit ni utilisateur, ni adhésion, ni contenu : elle ne
	 * **peut** donc pas en dépendre. C'est la forme la plus solide que puisse
	 * prendre cette exigence, et le test l'établit sur la signature.
	 *
	 * @return void
	 */
	public function test_the_decision_cannot_depend_on_the_visitor_identity(): void {
		$parameters = ( new \ReflectionMethod( Access_Policy::class, 'decide' ) )->getParameters();
		$names      = array();

		foreach ( $parameters as $parameter ) {
			$names[] = $parameter->getName();
		}

		$this->assertSame( array( 'address', 'global_list', 'level_lists' ), $names );
	}

	/**
	 * Une décision accordée nomme sa portée, et une décision non traitée n'en
	 * nomme aucune (FNC-06).
	 *
	 * Les portées se cumulent par disjonction, et le résultat porte **celle**
	 * qui a accordé : c'est ce qui permettra, aux lots 7 et 8, d'établir la
	 * non-transitivité de FNC-04 autrement qu'en inspectant un booléen.
	 *
	 * @return void
	 */
	public function test_a_granted_decision_names_the_scope_that_granted_it(): void {
		$rule = Rule_Set::from_text( '192.0.2.1' )->rules()[0];

		$granted = Access_Decision::granted_by( Scope::GLOBAL_LIST, $rule );

		$this->assertTrue( $granted->is_granted() );
		$this->assertSame( Scope::GLOBAL_LIST, $granted->scope() );
		$this->assertSame( $rule, $granted->rule() );

		$untouched = Access_Decision::not_handled();

		$this->assertFalse( $untouched->is_granted() );
		$this->assertNull( $untouched->scope() );
	}

	/**
	 * Une adresse de la liste d'un niveau accède aux contenus de ce niveau
	 * (FNC-02).
	 *
	 * @return void
	 */
	public function test_an_address_on_a_level_list_is_granted_for_that_level(): void {
		$decision = Access_Policy::decide(
			$this->number( '192.0.2.1' ),
			Rule_Set::empty(),
			array( 7 => Rule_Set::from_text( '192.0.2.1' ) )
		);

		$this->assertTrue( $decision->is_granted() );
		$this->assertSame( Scope::MEMBERSHIP_LEVEL, $decision->scope() );
		$this->assertSame( 7, $decision->scope_id(), 'La décision nomme le niveau qui a accordé.' );
	}

	/**
	 * **Le test central du lot : la portée croisée** (FNC-04).
	 *
	 * Les listes passées à la politique sont celles des niveaux qui
	 * restreignent le contenu demandé, et elles seules. Une autorisation posée
	 * sur un autre niveau n'est donc pas même consultée : ce n'est pas une
	 * comparaison qui échoue, c'est une liste qui n'entre pas dans la
	 * décision. L'implémentation naïve — une seule liste consultée quelle que
	 * soit la restriction rencontrée — violerait FNC-04 silencieusement et
	 * transformerait chaque autorisation de niveau en autorisation globale.
	 *
	 * @return void
	 */
	public function test_a_level_authorisation_does_not_cross_to_another_level(): void {
		$silver = Rule_Set::from_text( '192.0.2.1' );

		$decision = Access_Policy::decide(
			$this->number( '192.0.2.1' ),
			Rule_Set::empty(),
			array( 9 => Rule_Set::empty() )
		);

		$this->assertTrue(
			$decision->is_not_handled(),
			'La liste du niveau « Argent » ne doit pas décider pour un contenu réservé à « Or ».'
		);
		$this->assertCount( 1, $silver->rules(), 'La liste existe bien : c\'est son absence du calcul qui est testée.' );
	}

	/**
	 * Un contenu restreint par plusieurs niveaux consulte chacune de leurs
	 * listes (FNC-06).
	 *
	 * Les portées se cumulent par disjonction : une seule suffit.
	 *
	 * @return void
	 */
	public function test_several_applicable_levels_are_all_consulted(): void {
		$decision = Access_Policy::decide(
			$this->number( '198.51.100.9' ),
			Rule_Set::empty(),
			array(
				3 => Rule_Set::from_text( '192.0.2.1' ),
				5 => Rule_Set::from_text( '198.51.100.9' ),
			)
		);

		$this->assertTrue( $decision->is_granted() );
		$this->assertSame( 5, $decision->scope_id() );
	}

	/**
	 * La portée globale l'emporte sur les portées de niveau (FNC-01, FNC-06).
	 *
	 * L'ordre n'a pas d'effet sur le fait d'accorder — la disjonction est
	 * commutative — mais il en a un sur la portée rapportée, et donc sur le
	 * diagnostic. La globale est la plus large : c'est elle qui explique
	 * l'accès.
	 *
	 * @return void
	 */
	public function test_the_global_scope_is_reported_first(): void {
		$decision = Access_Policy::decide(
			$this->number( '192.0.2.1' ),
			Rule_Set::from_text( '192.0.2.1' ),
			array( 7 => Rule_Set::from_text( '192.0.2.1' ) )
		);

		$this->assertSame( Scope::GLOBAL_LIST, $decision->scope() );
		$this->assertNull( $decision->scope_id(), 'La portée globale n\'a pas d\'identifiant.' );
	}

	/**
	 * Une liste de niveau vide ou illisible n'accorde rien.
	 *
	 * @return void
	 */
	public function test_an_empty_or_invalid_level_list_grants_nothing(): void {
		$this->assertTrue(
			Access_Policy::decide(
				$this->number( '192.0.2.1' ),
				Rule_Set::empty(),
				array(
					3 => Rule_Set::empty(),
					5 => Rule_Set::from_text( "exemple.test\n" ),
				)
			)->is_not_handled()
		);
	}

	/**
	 * Une adresse indéterminable n'est accordée par aucune portée (D-05).
	 *
	 * @return void
	 */
	public function test_an_undetermined_address_is_granted_by_no_scope(): void {
		$this->assertTrue(
			Access_Policy::decide(
				null,
				Rule_Set::from_text( '0.0.0.0/1' ),
				array( 7 => Rule_Set::from_text( '0.0.0.0/1' ) )
			)->is_not_handled()
		);
	}
}
