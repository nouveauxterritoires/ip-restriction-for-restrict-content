<?php
/**
 * L'écran de réglages, dans un WordPress réel — SEC-01, SEC-03, SEC-11.
 *
 * C'est la suite qui porte le comportement observable, et elle commence par ce
 * qui garde l'écriture. Un écran de réglages qui accorde des accès est une
 * surface d'attaque : qui peut y écrire peut ouvrir tout le contenu restreint
 * du site.
 *
 * L'ordre des contrôles compte autant que leur présence. SEC-01 exige que la
 * capacité soit vérifiée **après** le nonce, et l'exige par un test précis :
 * une requête à la fois sans droit et sans nonce doit être refusée pour le
 * nonce.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Access;

use Ip_Restriction_For_Restrict_Content\Admin\Settings_Page;
use Ip_Restriction_For_Restrict_Content\Settings\Settings;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use Ip_Restriction_For_Restrict_Content\Tests\Support\Dependency_Capabilities;
use WP_UnitTestCase;

/**
 * Enregistrement et rendu des réglages globaux.
 */
final class SettingsScreenTest extends WP_UnitTestCase {

	/**
	 * Dépôt des réglages.
	 *
	 * @var Settings_Repository
	 */
	private Settings_Repository $repository;

	/**
	 * Écran étudié.
	 *
	 * @var Settings_Page
	 */
	private Settings_Page $page;

	/**
	 * Prépare un écran et un administrateur de la dépendance.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		Dependency_Capabilities::grant();

		$this->repository = new Settings_Repository();
		$this->page       = new Settings_Page( $this->repository );

		$_POST = array();
	}

	/**
	 * Remet l'environnement de requête en état.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_POST = array();

		parent::tear_down();
	}

	/**
	 * Connecte un utilisateur du rôle demandé.
	 *
	 * @param string $role Rôle WordPress.
	 * @return int Identifiant de l'utilisateur.
	 */
	private function log_in( string $role ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );

		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Soumet le formulaire avec les valeurs données.
	 *
	 * @param array<string, string> $fields Champs soumis.
	 * @param string|null           $nonce  Nonce à joindre, ou null pour n'en joindre aucun.
	 * @return void
	 */
	private function submit( array $fields, ?string $nonce ): void {
		$_POST = $fields;

		if ( null !== $nonce ) {
			$_POST[ Settings_Page::NONCE_FIELD ] = $nonce;
		}

		$this->page->maybe_save();
	}

	/**
	 * Un administrateur de la dépendance enregistre, et les réglages
	 * survivent à l'aller-retour.
	 *
	 * @return void
	 */
	public function test_a_valid_submission_is_stored(): void {
		$this->log_in( 'administrator' );

		$this->submit(
			array(
				'rcipr_allow_list'      => "192.0.2.1\n198.51.100.0/24",
				'rcipr_block_list'      => '203.0.113.*',
				'rcipr_trusted_proxies' => '203.0.113.7',
				'rcipr_blocked_message' => 'Non, pas depuis ici.',
			),
			wp_create_nonce( Settings_Page::NONCE_ACTION )
		);

		$this->assertTrue( $this->page->has_saved() );
		$this->assertSame( '', $this->page->refusal() );

		$stored = ( new Settings_Repository() )->load();

		$this->assertSame( "192.0.2.1\n198.51.100.0/24", $stored->allow_list() );
		$this->assertSame( '203.0.113.*', $stored->block_list() );
		$this->assertSame( '203.0.113.7', $stored->trusted_proxies() );
		$this->assertSame( 'Non, pas depuis ici.', $stored->blocked_message() );
	}

	/**
	 * Sans nonce, rien n'est enregistré (SEC-01).
	 *
	 * @return void
	 */
	public function test_a_submission_without_a_nonce_is_refused(): void {
		$this->log_in( 'administrator' );

		$this->submit( array( 'rcipr_allow_list' => '192.0.2.1' ), null );

		$this->assertFalse( $this->page->has_saved() );
		$this->assertSame( '', ( new Settings_Repository() )->load()->allow_list() );
	}

	/**
	 * Un nonce emprunté à une autre action est refusé (SEC-01).
	 *
	 * @return void
	 */
	public function test_a_nonce_borrowed_from_another_action_is_refused(): void {
		$this->log_in( 'administrator' );

		$this->submit( array( 'rcipr_allow_list' => '192.0.2.1' ), wp_create_nonce( 'une-autre-action' ) );

		$this->assertSame( Settings_Page::REFUSED_NONCE, $this->page->refusal() );
		$this->assertFalse( $this->page->has_saved() );
		$this->assertSame( '', ( new Settings_Repository() )->load()->allow_list() );
	}

	/**
	 * Sans la capacité de la dépendance, rien n'est enregistré (SEC-01).
	 *
	 * @return void
	 */
	public function test_a_submission_without_the_capability_is_refused(): void {
		$this->log_in( 'subscriber' );

		$this->submit( array( 'rcipr_allow_list' => '192.0.2.1' ), wp_create_nonce( Settings_Page::NONCE_ACTION ) );

		$this->assertSame( Settings_Page::REFUSED_CAPABILITY, $this->page->refusal() );
		$this->assertFalse( $this->page->has_saved() );
		$this->assertSame( '', ( new Settings_Repository() )->load()->allow_list() );
	}

	/**
	 * Sans droit **et** sans nonce, le motif du refus est le nonce.
	 *
	 * C'est l'ordre exigé par SEC-01, et c'est le seul test qui l'établisse :
	 * les deux contrôles refusent, mais le premier à parler doit être celui
	 * qui vaut pour tout le monde. Vérifier la capacité d'abord apprendrait à
	 * un attaquant qu'il n'a pas le droit, là où il doit seulement apprendre
	 * que sa requête n'est pas valide.
	 *
	 * @return void
	 */
	public function test_without_rights_and_without_a_nonce_the_nonce_speaks_first(): void {
		$this->log_in( 'subscriber' );

		$this->submit( array( 'rcipr_allow_list' => '192.0.2.1' ), 'nonce-invalide' );

		$this->assertSame( Settings_Page::REFUSED_NONCE, $this->page->refusal() );
	}

	/**
	 * Une entrée refusée est conservée dans le réglage (FNC-22), et les
	 * doublons sont fusionnés (FNC-28).
	 *
	 * @return void
	 */
	public function test_a_rejected_entry_is_stored_and_duplicates_are_merged(): void {
		$this->log_in( 'administrator' );

		$this->submit(
			array( 'rcipr_allow_list' => "  192.0.2.1  \nexemple.test\n192.0.2.1\n" ),
			wp_create_nonce( Settings_Page::NONCE_ACTION )
		);

		$this->assertSame(
			"192.0.2.1\nexemple.test",
			( new Settings_Repository() )->load()->allow_list()
		);
	}

	/**
	 * Un message porteur de balises est rendu inerte (SEC-03).
	 *
	 * Le message est le seul réglage que le public verra : il est assaini à
	 * l'entrée et échappé à la sortie, et les deux sont nécessaires.
	 *
	 * @return void
	 */
	public function test_a_message_carrying_markup_is_rendered_inert(): void {
		$this->log_in( 'administrator' );

		$this->submit(
			array( 'rcipr_blocked_message' => '<script>alert(1)</script>Non.' ),
			wp_create_nonce( Settings_Page::NONCE_ACTION )
		);

		$stored = ( new Settings_Repository() )->load()->blocked_message();

		$this->assertStringNotContainsString( '<script>', $stored );

		ob_start();
		$this->page->render();
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<script>alert', $output );
	}

	/**
	 * L'écran ne rend rien à qui n'a pas la capacité.
	 *
	 * @return void
	 */
	public function test_nothing_is_rendered_without_the_capability(): void {
		$this->log_in( 'subscriber' );

		ob_start();
		$this->page->render();

		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * L'écran est enregistré sous le menu de la dépendance, avec sa capacité
	 * (FNC-24).
	 *
	 * @return void
	 */
	public function test_the_screen_lives_under_the_dependency_menu(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$GLOBALS['submenu'] = array();

		$this->log_in( 'administrator' );
		$this->page->add_page();

		$entries = $GLOBALS['submenu'][ Settings_Page::PARENT_SLUG ] ?? array();
		$found   = array();

		foreach ( $entries as $entry ) {
			if ( Settings_Page::SLUG === $entry[2] ) {
				$found[] = $entry[1];
			}
		}

		$this->assertSame( array( Settings_Page::CAPABILITY ), $found );
	}

	/**
	 * Les champs sont vides sur une installation neuve (FNC-27).
	 *
	 * @return void
	 */
	public function test_a_fresh_installation_has_empty_lists(): void {
		$this->log_in( 'administrator' );

		$settings = ( new Settings_Repository() )->load();

		$this->assertSame( '', $settings->allow_list() );
		$this->assertSame( '', $settings->block_list() );
		$this->assertSame( Settings::NO_HEADER, $settings->forwarded_header() );
	}

	/**
	 * L'écran affiche l'adresse retenue pour la requête en cours et sa source
	 * (FNC-29).
	 *
	 * @return void
	 */
	public function test_the_screen_shows_the_resolved_address_and_its_source(): void {
		$this->log_in( 'administrator' );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.9';

		ob_start();
		$this->page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '198.51.100.9', $output );

		/*
		 * Le fragment attendu ne porte pas d'apostrophe : la sortie est
		 * échappée, et `esc_html()` transforme l'apostrophe en entité. Affirmer
		 * sur le texte brut ferait échouer le test pour une raison qui n'a rien
		 * à voir avec ce qu'il établit.
		 */
		$this->assertStringContainsString( 'adresse de la connexion', $output );
	}

	/**
	 * Derrière un proxy déclaré, l'écran montre l'adresse de l'en-tête et son
	 * rang (FNC-29).
	 *
	 * C'est ce qui permet à un administrateur de distinguer « ma règle est
	 * fausse » de « l'adresse évaluée n'est pas celle que je crois ».
	 *
	 * @return void
	 */
	public function test_the_screen_shows_the_header_address_behind_a_proxy(): void {
		$this->log_in( 'administrator' );

		$this->submit(
			array(
				'rcipr_trusted_proxies'  => '203.0.113.7',
				'rcipr_forwarded_header' => 'X-Forwarded-For',
			),
			wp_create_nonce( Settings_Page::NONCE_ACTION )
		);

		$_SERVER['REMOTE_ADDR']          = '203.0.113.7';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.10, 198.51.100.9';

		ob_start();
		$this->page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '198.51.100.9', $output );
		$this->assertStringNotContainsString( '192.0.2.10', $output, 'La valeur injectée à gauche ne doit pas être retenue.' );
		$this->assertStringContainsString( 'X-Forwarded-For', $output );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * Le diagnostic nomme chaque raison d'une adresse indéterminable (FNC-29).
	 *
	 * C'est l'écran qui doit permettre de distinguer « ma règle est fausse »
	 * de « l'adresse évaluée n'est pas celle que je crois » : chaque raison
	 * d'échec de la résolution a donc sa phrase, et pas une seule pour les
	 * quatre.
	 *
	 * @return void
	 */
	public function test_the_diagnostics_name_each_undetermined_reason(): void {
		$this->log_in( 'administrator' );

		$this->submit(
			array(
				'rcipr_trusted_proxies'  => '203.0.113.7',
				'rcipr_forwarded_header' => 'X-Forwarded-For',
			),
			wp_create_nonce( Settings_Page::NONCE_ACTION )
		);

		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		$this->assertStringContainsString( 'absent de la requête', $this->rendered() );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'bonjour';
		$this->assertStringContainsString( 'analysable', $this->rendered() );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
		$this->assertStringContainsString( 'que des proxys', $this->rendered() );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR'] );
		$this->assertStringContainsString( 'aucune adresse de connexion', $this->rendered() );
	}

	/**
	 * Un nonce refusé le dit à l'écran, sans faire perdre la page.
	 *
	 * Une page de réglages expirée est le cas banal — un onglet laissé ouvert
	 * une journée. Mourir sur un `wp_die()` ferait perdre la saisie ; ne rien
	 * dire ferait croire à un enregistrement.
	 *
	 * @return void
	 */
	public function test_a_refused_nonce_is_reported_on_the_screen(): void {
		$this->log_in( 'administrator' );

		$this->submit( array( 'rcipr_allow_list' => '192.0.2.1' ), 'nonce-invalide' );

		$this->assertStringContainsString( 'avait expiré', $this->rendered() );
	}

	/**
	 * Rend l'écran et renvoie sa sortie.
	 *
	 * @return string
	 */
	private function rendered(): string {
		ob_start();
		$this->page->render();

		return (string) ob_get_clean();
	}
}
