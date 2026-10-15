<?php
/**
 * Contrat : l'accroche de décision d'accès au contenu.
 *
 * C'est l'accroche dont tout le plugin dépend (FNC-01 à FNC-06). Le noyau
 * libre l'expose délibérément sur le chemin déconnecté, et son commentaire le
 * dit : « We need to apply the filter here so that it can be used on logged-out
 * users, like in IP Restriction. » (`core/includes/member-functions.php:100`).
 *
 * Que le code soit là est établi par lecture de la source, et le point ouvert
 * V-07 en est levé. Ce que la lecture n'établit pas, c'est que le filtre soit
 * **atteint** sur les trois chemins que la dépendance emprunte selon qu'un
 * client existe ou non. Seule l'exécution le dit, et c'est l'objet de ces
 * tests. Le jour où l'un d'eux échoue, le message doit dire « l'amont a
 * changé », et non « nous ne savions pas ».
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Contract;

use Ip_Restriction_For_Restrict_Content\Tests\Support\Hook_Witness;
use RCP_Member;
use WP_UnitTestCase;

/**
 * Les trois chemins d'application de `rcp_member_can_access`.
 */
final class ContentAccessContractTest extends WP_UnitTestCase {

	/**
	 * Accroche observée.
	 *
	 * @var string
	 */
	private const HOOK = 'rcp_member_can_access';

	/**
	 * Crée un contenu restreint à un niveau d'adhésion.
	 *
	 * La restriction est posée par la métadonnée que la dépendance lit
	 * elle-même : le test n'a pas à connaître sa logique, seulement son
	 * stockage.
	 *
	 * @return int Identifiant du contenu.
	 */
	private function restricted_post(): int {
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, 'rcp_subscription_level', array( 1 ) );
		update_post_meta( $post_id, 'rcp_access_level', 0 );

		return $post_id;
	}

	/**
	 * Chemin déconnecté : aucun client, le filtre est appliqué.
	 *
	 * C'est le chemin de FNC-05, celui qui permet d'accorder l'accès à un
	 * visiteur sans compte. S'il disparaissait, la fonctionnalité centrale du
	 * plugin n'aurait plus de point d'accroche.
	 *
	 * @return void
	 */
	public function test_the_filter_is_applied_on_the_logged_out_path(): void {
		$post_id = $this->restricted_post();

		wp_set_current_user( 0 );

		$witness = Hook_Witness::watch( self::HOOK );

		rcp_user_can_access( 0, $post_id );

		$this->assertTrue(
			$witness->fired(),
			'L\'amont a changé : rcp_user_can_access() n\'applique plus rcp_member_can_access '
			. 'sur le chemin déconnecté (constaté en core/includes/member-functions.php:101). '
			. 'FNC-05 n\'a plus de point d\'accroche.'
		);

		$arguments = $witness->first_call();

		$this->assertCount( 4, $arguments, 'L\'accroche doit transporter quatre arguments.' );
		$this->assertIsBool( $arguments[0], 'Le premier argument est la décision d\'accès.' );
		$this->assertSame( $post_id, (int) $arguments[2], 'Le troisième argument est l\'identifiant du contenu.' );
	}

	/**
	 * Chemin connecté avec client : le filtre est appliqué par l'objet client.
	 *
	 * @return void
	 */
	public function test_the_filter_is_applied_on_the_customer_path(): void {
		$post_id = $this->restricted_post();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$customer_id = rcp_add_customer( array( 'user_id' => $user_id ) );

		$this->assertNotEmpty( $customer_id, 'La dépendance n\'a pas créé l\'enregistrement client attendu.' );

		wp_set_current_user( $user_id );

		$witness = Hook_Witness::watch( self::HOOK );

		rcp_user_can_access( $user_id, $post_id );

		$this->assertTrue(
			$witness->fired(),
			'L\'amont a changé : RCP_Customer::can_access() n\'applique plus rcp_member_can_access '
			. '(constaté en core/includes/customers/class-rcp-customer.php:772, 777, 786, 791, 806).'
		);
	}

	/**
	 * Chemin historique : l'objet membre applique lui aussi le filtre.
	 *
	 * Ce chemin reste emprunté par du code tiers et par la compatibilité
	 * ascendante de la dépendance. Le plugin n'a pas à choisir lequel sera
	 * emprunté : il doit être accroché aux trois.
	 *
	 * @return void
	 */
	public function test_the_filter_is_applied_on_the_legacy_member_path(): void {
		$post_id = $this->restricted_post();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$witness = Hook_Witness::watch( self::HOOK );

		( new RCP_Member( $user_id ) )->can_access( $post_id );

		$this->assertTrue(
			$witness->fired(),
			'L\'amont a changé : RCP_Member::can_access() n\'applique plus rcp_member_can_access '
			. '(constaté en core/includes/class-rcp-member.php:970, 975, 987).'
		);
	}

	/**
	 * Le témoin n'altère pas la décision : il observe.
	 *
	 * Sans cette propriété, les tests de la suite `access` hériteraient d'un
	 * comportement introduit par la suite `contract`.
	 *
	 * @return void
	 */
	public function test_the_witness_leaves_the_decision_unchanged(): void {
		$post_id = $this->restricted_post();

		wp_set_current_user( 0 );

		$without = rcp_user_can_access( 0, $post_id );

		Hook_Witness::watch( self::HOOK );

		$with = rcp_user_can_access( 0, $post_id );

		$this->assertSame( $without, $with );
	}
}
