<?php
/**
 * L'avis d'entrées refusées — FNC-22, A-03, SEC-11.
 *
 * SEC-11 exige un avis **persistant**, et le mot est l'exigence : un avis
 * éphémère, affiché une fois après l'enregistrement, serait vu par qui vient
 * d'enregistrer et par personne d'autre. Une ligne inerte dans une liste
 * d'autorisation survit à la session de celui qui l'a saisie.
 *
 * A-03 explique pourquoi la liste de blocage y figure autant que la liste
 * d'autorisation : son échec est silencieux. Une entrée de blocage refusée ne
 * bloque personne, et personne ne s'en aperçoit — alors qu'une entrée
 * d'autorisation refusée se manifeste par un accès refusé, donc par une
 * réclamation.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Access;

use Ip_Restriction_For_Restrict_Content\Admin\Rejection_Notice;
use Ip_Restriction_For_Restrict_Content\Settings\Settings;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use Ip_Restriction_For_Restrict_Content\Tests\Support\Dependency_Capabilities;
use WP_UnitTestCase;

/**
 * Rendu de l'avis d'entrées refusées.
 */
final class RejectionNoticeTest extends WP_UnitTestCase {

	/**
	 * Dépôt des réglages.
	 *
	 * @var Settings_Repository
	 */
	private Settings_Repository $repository;

	/**
	 * Prépare un dépôt et les capacités de la dépendance.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		Dependency_Capabilities::grant();

		$this->repository = new Settings_Repository();
	}

	/**
	 * Connecte un utilisateur du rôle demandé.
	 *
	 * @param string $role Rôle WordPress.
	 * @return void
	 */
	private function log_in( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * Écrit des réglages.
	 *
	 * @param array<string, string> $values Réglages.
	 * @return void
	 */
	private function store( array $values ): void {
		$this->repository->save( Settings::from_array( $values ) );
	}

	/**
	 * Rend l'avis et renvoie sa sortie.
	 *
	 * Une instance neuve est construite à chaque appel : c'est ce qui simule
	 * une requête suivante, et donc ce qui établit la persistance.
	 *
	 * @return string
	 */
	private function render(): string {
		ob_start();
		( new Rejection_Notice( new Settings_Repository() ) )->render();

		return (string) ob_get_clean();
	}

	/**
	 * Une entrée refusée est signalée avec son champ, sa ligne et son motif.
	 *
	 * @return void
	 */
	public function test_a_rejected_entry_names_its_field_line_and_reason(): void {
		$this->log_in( 'administrator' );
		$this->store( array( 'allow_list' => "192.0.2.1\nexemple.test" ) );

		$output = $this->render();

		$this->assertStringContainsString( 'exemple.test', $output );
		$this->assertStringContainsString( 'ligne 2', $output );
		$this->assertStringContainsString( 'autorisation globale', $output );
		$this->assertStringContainsString( 'nom d', $output, 'Le motif nomme le nom d\'hôte.' );
	}

	/**
	 * L'avis est encore là à la requête suivante (SEC-11).
	 *
	 * Il n'est pas mémorisé à l'enregistrement : il se recalcule depuis les
	 * réglages stockés, et reste donc exact aussi longtemps que la ligne
	 * fautive est dans le champ.
	 *
	 * @return void
	 */
	public function test_the_notice_survives_the_next_request(): void {
		$this->log_in( 'administrator' );
		$this->store( array( 'allow_list' => 'exemple.test' ) );

		$this->assertStringContainsString( 'exemple.test', $this->render() );
		$this->assertStringContainsString( 'exemple.test', $this->render() );
		$this->assertStringContainsString( 'exemple.test', $this->render() );
	}

	/**
	 * L'avis disparaît dès que la ligne est corrigée.
	 *
	 * La contrepartie de la persistance : un avis qui resterait après
	 * correction serait un avis qu'on apprend à ignorer.
	 *
	 * @return void
	 */
	public function test_the_notice_disappears_once_corrected(): void {
		$this->log_in( 'administrator' );
		$this->store( array( 'allow_list' => 'exemple.test' ) );

		$this->assertNotSame( '', $this->render() );

		$this->store( array( 'allow_list' => '192.0.2.1' ) );

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Une entrée refusée de la liste de **blocage** est signalée aussi (A-03).
	 *
	 * @return void
	 */
	public function test_a_rejected_block_list_entry_is_reported(): void {
		$this->log_in( 'administrator' );
		$this->store( array( 'block_list' => '10.0.0.9-10.0.0.1' ) );

		$output = $this->render();

		$this->assertStringContainsString( 'blocage', $output );
		$this->assertStringContainsString( '10.0.0.9-10.0.0.1', $output );
	}

	/**
	 * Une entrée refusée de la liste de proxys est signalée aussi.
	 *
	 * Son échec est silencieux par un autre chemin : un proxy qui n'est pas
	 * déclaré fait lire l'adresse de connexion, et la liste d'autorisation
	 * cesse de correspondre sans que rien ne le dise.
	 *
	 * @return void
	 */
	public function test_a_rejected_proxy_entry_is_reported(): void {
		$this->log_in( 'administrator' );
		$this->store( array( 'trusted_proxies' => '*' ) );

		$output = $this->render();

		$this->assertStringContainsString( 'Proxys de confiance', $output );
		$this->assertStringContainsString( 'toutes les adresses', $output );
	}

	/**
	 * Des réglages valides ne produisent aucun avis.
	 *
	 * @return void
	 */
	public function test_valid_settings_produce_no_notice(): void {
		$this->log_in( 'administrator' );
		$this->store(
			array(
				'allow_list'      => "192.0.2.1\n198.51.100.0/24",
				'block_list'      => '203.0.113.*',
				'trusted_proxies' => '2001:db8::1',
			)
		);

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Rien n'est affiché à qui ne peut pas corriger les réglages.
	 *
	 * @return void
	 */
	public function test_nothing_is_shown_to_users_who_cannot_fix_it(): void {
		$this->log_in( 'subscriber' );
		$this->store( array( 'allow_list' => 'exemple.test' ) );

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Une entrée refusée porteuse de balises est rendue inerte (SEC-03).
	 *
	 * La ligne vient d'une saisie, et une saisie est une donnée hostile
	 * jusqu'à preuve du contraire — d'autant qu'elle est ici réaffichée
	 * telle quelle pour que l'administrateur la reconnaisse.
	 *
	 * @return void
	 */
	public function test_a_rejected_entry_carrying_markup_is_rendered_inert(): void {
		$this->log_in( 'administrator' );
		$this->store( array( 'allow_list' => '<script>alert(1)</script>' ) );

		$output = $this->render();

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
	}
}
