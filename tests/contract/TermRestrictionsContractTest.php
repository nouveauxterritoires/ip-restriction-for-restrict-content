<?php
/**
 * Contrat : les restrictions de taxonomie, et l'absence d'accroche sur la décision.
 *
 * Ce fichier porte la contrainte majeure du cahier des charges §5.1.1.
 * `rcp_user_can_access_term()` n'applique **aucun** filtre : elle renvoie sa
 * décision sans point d'accroche, et renvoie même `false` sans filtre lorsque
 * aucun client ne correspond à l'utilisateur. Il est donc impossible
 * d'accorder l'accès à un contenu restreint par catégorie en filtrant la
 * décision.
 *
 * Deux tests de nature opposée sont donc nécessaires : l'un établit une
 * **absence** — aucune accroche plausible n'est atteinte —, l'autre établit que
 * la seule voie disponible fonctionne bien, c'est-à-dire que neutraliser les
 * restrictions du terme en amont de la décision suffit à la retourner.
 *
 * Le premier échouera le jour où la dépendance ajoutera enfin un filtre sur la
 * décision de terme. Ce sera une bonne nouvelle à traiter, pas une régression.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Contract;

use Ip_Restriction_For_Restrict_Content\Tests\Support\Hook_Witness;
use WP_UnitTestCase;

/**
 * Accroches disponibles sur les restrictions de terme.
 */
final class TermRestrictionsContractTest extends WP_UnitTestCase {

	/**
	 * Accroches qu'un développeur chercherait pour filtrer la décision de terme.
	 *
	 * La liste est volontairement large : établir une absence demande de
	 * chercher là où l'on s'attendrait à trouver.
	 *
	 * @var list<string>
	 */
	private const PLAUSIBLE_DECISION_HOOKS = array(
		'rcp_user_can_access_term',
		'rcp_member_can_access_term',
		'rcp_can_access_term',
		'rcp_term_can_access',
		'rcp_member_can_access',
	);

	/**
	 * Crée un terme restreint et renvoie son identifiant.
	 *
	 * @return int
	 */
	private function restricted_term(): int {
		$term_id = self::factory()->category->create();

		update_term_meta(
			$term_id,
			'rcp_restricted_meta',
			array(
				'paid_only'     => true,
				'subscriptions' => array( 1 ),
			)
		);

		return $term_id;
	}

	/**
	 * Le filtre sur les restrictions d'un terme est appliqué.
	 *
	 * @return void
	 */
	public function test_the_term_restrictions_filter_is_applied(): void {
		$term_id = $this->restricted_term();
		$witness = Hook_Witness::watch( 'rcp_get_term_restrictions', 2 );

		rcp_get_term_restrictions( $term_id );

		$this->assertTrue(
			$witness->fired(),
			'L\'amont a changé : rcp_get_term_restrictions() n\'applique plus son filtre '
			. '(constaté en core/includes/misc-functions.php:1002). La couverture des '
			. 'taxonomies perd sa seule accroche (cahier des charges §5.1.1).'
		);

		$arguments = $witness->first_call();

		$this->assertCount( 2, $arguments );
		$this->assertSame( $term_id, (int) $arguments[1], 'Le second argument est l\'identifiant du terme.' );
	}

	/**
	 * La décision d'accès à un terme n'offre aucune accroche.
	 *
	 * @return void
	 */
	public function test_the_term_decision_offers_no_hook(): void {
		$term_id = $this->restricted_term();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$witnesses = array();

		foreach ( self::PLAUSIBLE_DECISION_HOOKS as $hook ) {
			$witnesses[ $hook ] = Hook_Witness::watch( $hook );
		}

		$can_access = rcp_user_can_access_term( $user_id, $term_id );

		$this->assertFalse(
			$can_access,
			'Un utilisateur sans enregistrement client ne doit pas accéder à un terme restreint.'
		);

		foreach ( $witnesses as $hook => $witness ) {
			$this->assertFalse(
				$witness->fired(),
				sprintf(
					'L\'amont a changé : rcp_user_can_access_term() applique désormais « %s ». '
					. 'La contrainte du cahier des charges §5.1.1 doit être réexaminée — et '
					. 'la couverture des taxonomies peut cesser de passer par les restrictions.',
					$hook
				)
			);
		}
	}

	/**
	 * Neutraliser les restrictions du terme retourne la décision.
	 *
	 * C'est le mécanisme retenu au §5.1.1, et le seul disponible. Sans ce
	 * test, la contrainte serait documentée mais la solution ne serait pas
	 * établie.
	 *
	 * @return void
	 */
	public function test_emptying_the_restrictions_grants_access_to_the_term(): void {
		$term_id = $this->restricted_term();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertFalse( rcp_user_can_access_term( $user_id, $term_id ) );

		add_filter( 'rcp_get_term_restrictions', '__return_empty_array' );

		$this->assertTrue(
			rcp_user_can_access_term( $user_id, $term_id ),
			'Neutraliser les restrictions d\'un terme ne suffit plus à en accorder l\'accès : '
			. 'la couverture des taxonomies n\'a plus de mécanisme (cahier des charges §5.1.1).'
		);

		remove_filter( 'rcp_get_term_restrictions', '__return_empty_array' );
	}

	/**
	 * L'effet du filtre ne survit pas à son retrait.
	 *
	 * La portée de l'effet est la requête (§5.1.1) : c'est le risque qui a
	 * valu le point ouvert V-06, et il doit rester observable.
	 *
	 * @return void
	 */
	public function test_the_filter_effect_does_not_outlive_its_removal(): void {
		$term_id = $this->restricted_term();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		add_filter( 'rcp_get_term_restrictions', '__return_empty_array' );
		rcp_user_can_access_term( $user_id, $term_id );
		remove_filter( 'rcp_get_term_restrictions', '__return_empty_array' );

		$this->assertFalse(
			rcp_user_can_access_term( $user_id, $term_id ),
			'Les restrictions du terme restent neutralisées après le retrait du filtre : '
			. 'la dépendance a mis le résultat en cache quelque part, et le garde-fou du '
			. '§5.1.1 ne suffit plus.'
		);
	}
}
