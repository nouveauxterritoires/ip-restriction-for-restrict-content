<?php
/**
 * Contrat : les accroches de la boîte de restriction d'un contenu.
 *
 * C'est par elles que passera le champ de FNC-26, et par `rcp_save_post_meta`
 * que son enregistrement sera déclenché. Les deux vivent dans un fichier que
 * la dépendance ne charge qu'en administration
 * (`core/includes/class-restrict-content.php:247`), ce qui est aussi la raison
 * pour laquelle le catalogue de capacités les déclare en contexte
 * d'administration : les sonder sur une requête de façade conclurait à leur
 * absence, et annoncerait la perte d'une fonctionnalité intacte.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Contract;

use Ip_Restriction_For_Restrict_Content\Tests\Support\Hook_Witness;
use WP_UnitTestCase;

/**
 * Boîte de restriction : rendu et enregistrement.
 */
final class PostRestrictionContractTest extends WP_UnitTestCase {

	/**
	 * Charge le fichier d'administration qui porte les accroches.
	 *
	 * @return void
	 */
	private function load_metabox(): void {
		require_once RCP_PLUGIN_DIR . 'core/includes/admin/metabox.php';
	}

	/**
	 * Remet l'environnement de requête en état.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_POST = array();

		unset( $GLOBALS['post'] );

		parent::tear_down();
	}

	/**
	 * Le rendu de la boîte déclenche ses deux accroches, dans l'ordre.
	 *
	 * L'ordre compte : le champ de FNC-26 se place après les champs de la
	 * dépendance (§7.3), donc sur l'accroche de fin.
	 *
	 * @return void
	 */
	public function test_rendering_the_metabox_fires_both_hooks_in_order(): void {
		$this->load_metabox();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$post_id         = self::factory()->post->create();
		$GLOBALS['post'] = get_post( $post_id );
		$order           = array();

		foreach ( array( 'rcp_metabox_fields_before', 'rcp_metabox_fields_after' ) as $hook ) {
			add_action(
				$hook,
				static function () use ( &$order, $hook ): void {
					$order[] = $hook;
				},
				1
			);
		}

		ob_start();
		rcp_render_meta_box();
		$output = (string) ob_get_clean();

		$this->assertSame(
			array( 'rcp_metabox_fields_before', 'rcp_metabox_fields_after' ),
			$order,
			'L\'amont a changé : la boîte de restriction ne déclenche plus ses deux accroches '
			. 'dans l\'ordre constaté en core/includes/admin/metabox.php:77 et 81. '
			. 'L\'emplacement du champ de FNC-26 doit être réexaminé.'
		);
		$this->assertStringContainsString(
			'rcp_meta_box',
			$output,
			'La boîte ne rend plus son champ de nonce : l\'enregistrement de FNC-26 devra '
			. 'fournir le sien.'
		);
	}

	/**
	 * L'enregistrement de la boîte déclenche son accroche, et seulement après
	 * vérification du nonce et de la capacité.
	 *
	 * @return void
	 */
	public function test_saving_the_metabox_fires_its_save_hook(): void {
		$this->load_metabox();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$post_id = self::factory()->post->create();
		$witness = Hook_Witness::watch( 'rcp_save_post_meta', 1 );

		$_POST = array(
			'rcp_meta_box'    => wp_create_nonce( 'metabox.php' ),
			'post_type'       => 'post',
			'rcp_restrict_by' => 'unrestricted',
		);

		rcp_save_meta_data( $post_id );

		$this->assertTrue(
			$witness->fired(),
			'L\'amont a changé : rcp_save_meta_data() ne déclenche plus rcp_save_post_meta '
			. '(constaté en core/includes/admin/metabox.php:233). FNC-26 perd son point '
			. 'd\'enregistrement.'
		);
		$this->assertSame( $post_id, (int) $witness->first_call()[0] );
	}

	/**
	 * Sans nonce valide, l'accroche d'enregistrement n'est pas atteinte.
	 *
	 * Ce test n'établit pas une accroche : il établit que la dépendance garde
	 * son propre enregistrement. C'est ce qui permet au plugin de s'appuyer
	 * sur cette accroche sans y rejouer la vérification du nonce — tout en
	 * conservant la sienne pour son propre champ (SEC-02).
	 *
	 * @return void
	 */
	public function test_an_invalid_nonce_never_reaches_the_save_hook(): void {
		$this->load_metabox();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$post_id = self::factory()->post->create();
		$witness = Hook_Witness::watch( 'rcp_save_post_meta', 1 );

		$_POST = array(
			'rcp_meta_box'    => 'nonce-invalide',
			'post_type'       => 'post',
			'rcp_restrict_by' => 'unrestricted',
		);

		rcp_save_meta_data( $post_id );

		$this->assertFalse(
			$witness->fired(),
			'L\'amont a changé : l\'enregistrement de la boîte de restriction ne vérifie plus '
			. 'son nonce. Le plugin ne peut plus s\'appuyer sur cette garde.'
		);
	}

	/**
	 * Les restrictions d'un contenu sont lisibles par l'interface de la
	 * dépendance.
	 *
	 * C'est la donnée dont la décision d'accès aura besoin pour savoir à quels
	 * niveaux un contenu est restreint (FNC-02, FNC-04).
	 *
	 * @return void
	 */
	public function test_post_restrictions_are_readable(): void {
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, 'rcp_subscription_level', array( 1 ) );

		$restrictions = rcp_get_post_restrictions( $post_id );

		$this->assertIsArray(
			$restrictions,
			'L\'amont a changé : rcp_get_post_restrictions() ne renvoie plus un tableau '
			. '(constaté en core/includes/misc-functions.php:831).'
		);
		$this->assertArrayHasKey( 'membership_levels', $restrictions );
	}
}
