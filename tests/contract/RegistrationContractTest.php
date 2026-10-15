<?php
/**
 * Contrat : l'accroche du formulaire d'inscription et son canal d'erreur.
 *
 * Deux faits à établir, et aucun des deux ne se lit dans un stub.
 *
 * Le premier est que `rcp_form_errors` est bien atteint pendant le traitement
 * d'une inscription (`core/includes/registration-functions.php:164`), après la
 * validation du niveau — c'est ce qui en fait le point d'accroche de FNC-07,
 * plutôt que `rcp_before_form_errors`, qui la précède.
 *
 * Le second est que l'erreur ajoutée depuis cette accroche **annule**
 * l'inscription et que son message revient par le canal de la dépendance :
 * `rcp_errors()` est relu juste après l'accroche, et un message non vide
 * déclenche `wp_send_json_error()` avec le balisage de
 * `rcp_get_error_messages_html( 'register' )`. C'est le point que la
 * documentation amont laissait indéterminé, et que seule l'exécution tranche.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Contract;

use Ip_Restriction_For_Restrict_Content\Tests\Support\Hook_Witness;
use Ip_Restriction_For_Restrict_Content\Tests\Support\Request_Halted;
use WP_UnitTestCase;

/**
 * Traitement réel d'une inscription.
 */
final class RegistrationContractTest extends WP_UnitTestCase {

	/**
	 * Message injecté depuis l'accroche, reconnaissable dans la réponse.
	 *
	 * @var string
	 */
	private const BLOCKED_MESSAGE = 'Inscription refusée par le test de contrat.';

	/**
	 * Prépare une inscription valide sur un niveau gratuit.
	 *
	 * Un niveau gratuit et à vie évite deux chemins qui n'ont rien à voir avec
	 * l'accroche observée : la validation de passerelle de paiement et le
	 * refus de second essai gratuit.
	 *
	 * @return void
	 */
	private function prepare_registration(): void {
		/*
		 * `rcp_errors()` est un `WP_Error` statique, partagé par tout le
		 * processus : une erreur ajoutée par un test survit au suivant, et le
		 * premier message enregistré pour un code masque les suivants
		 * (JE-024). Le collecteur est donc vidé avant chaque préparation.
		 */
		foreach ( rcp_errors()->get_error_codes() as $code ) {
			rcp_errors()->remove( $code );
		}

		$level_id = rcp_add_membership_level(
			array(
				'name'     => 'Contrat — niveau gratuit',
				'price'    => 0,
				'duration' => 0,
				'status'   => 'active',
			)
		);

		$this->assertNotEmpty( $level_id, 'La dépendance n\'a pas créé le niveau d\'adhésion attendu.' );

		rcp_setup_registration( (int) $level_id );

		$_POST = array(
			'rcp_register_nonce'    => wp_create_nonce( 'rcp-register-nonce' ),
			'rcp_level'             => (int) $level_id,
			'rcp_gateway'           => 'free',
			'rcp_user_login'        => 'contrat-inscription',
			'rcp_user_email'        => 'contrat@example.test',
			'rcp_user_pass'         => 'mot-de-passe-de-test',
			'rcp_user_pass_confirm' => 'mot-de-passe-de-test',
		);
	}

	/**
	 * Rejoue le traitement de l'inscription et renvoie ce qui a été émis.
	 *
	 * Le traitement se termine par `wp_send_json_error()` ou
	 * `wp_send_json_success()`, donc par `wp_die()`, donc par `die()`. Le
	 * gestionnaire installé ici le traduit en exception : sans lui, le
	 * processus s'arrête en silence avec le code 0 et les tests suivants ne
	 * sont pas exécutés (JE-007).
	 *
	 * @return string Charge utile émise.
	 */
	private function process_registration(): string {
		Request_Halted::intercept_wp_die();

		ob_start();

		try {
			rcp_process_registration();
		} catch ( Request_Halted $halted ) {
			unset( $halted );
		}

		return (string) ob_get_clean();
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
	 * L'accroche est atteinte pendant le traitement d'une inscription.
	 *
	 * @return void
	 */
	public function test_the_form_errors_hook_is_reached(): void {
		$this->prepare_registration();

		$witness = Hook_Witness::watch( 'rcp_form_errors', 2 );

		$this->process_registration();

		$this->assertTrue(
			$witness->fired(),
			'L\'amont a changé : rcp_process_registration() n\'atteint plus rcp_form_errors '
			. '(constaté en core/includes/registration-functions.php:164). FNC-07 n\'a plus '
			. 'de point d\'accroche.'
		);
	}

	/**
	 * L'accroche est atteinte **après** la validation du niveau.
	 *
	 * C'est la raison pour laquelle `rcp_before_form_errors` n'est pas retenue
	 * (cahier des charges §5.1) : s'accrocher avant la validation du niveau
	 * ferait porter au plugin un refus que la dépendance aurait de toute façon
	 * prononcé, et masquerait la vraie cause à l'utilisateur.
	 *
	 * @return void
	 */
	public function test_the_early_hook_fires_before_the_retained_one(): void {
		$this->prepare_registration();

		$order = array();

		add_action(
			'rcp_before_form_errors',
			static function () use ( &$order ): void {
				$order[] = 'before';
			},
			1
		);
		add_action(
			'rcp_form_errors',
			static function () use ( &$order ): void {
				$order[] = 'retained';
			},
			1
		);

		$this->process_registration();

		$this->assertSame( array( 'before', 'retained' ), $order );
	}

	/**
	 * Une erreur ajoutée depuis l'accroche annule l'inscription, et son
	 * message revient par le canal de la dépendance.
	 *
	 * @return void
	 */
	public function test_an_error_added_from_the_hook_cancels_the_registration(): void {
		$this->prepare_registration();

		add_action(
			'rcp_form_errors',
			static function (): void {
				rcp_errors()->add( 'rcipr_contract', self::BLOCKED_MESSAGE, 'register' );
			}
		);

		$payload = $this->process_registration();

		/*
		 * La charge utile est du JSON : le message y est échappé à la mode
		 * JSON (`refus\u00e9e`). L'affirmer sur la chaîne brute passerait à
		 * côté du contrat réel, qui porte sur le balisage renvoyé, pas sur son
		 * encodage de transport.
		 */
		$decoded = json_decode( $payload, true );

		$this->assertIsArray( $decoded, 'La réponse de la dépendance n\'est plus du JSON.' );
		$this->assertFalse( $decoded['success'], 'L\'inscription doit être annulée.' );
		$this->assertStringContainsString(
			self::BLOCKED_MESSAGE,
			(string) ( $decoded['data']['errors'] ?? '' ),
			'Le message ajouté depuis rcp_form_errors ne revient plus par la réponse de la '
			. 'dépendance : le canal établi en core/includes/registration-functions.php:167-175 '
			. 'a changé, et FNC-10 doit être réexaminé.'
		);
		$this->assertFalse(
			username_exists( 'contrat-inscription' ),
			'L\'inscription a été menée à son terme malgré l\'erreur ajoutée.'
		);
	}

	/**
	 * Le collecteur d'erreurs est bien un `WP_Error`, et il est relu après
	 * l'accroche.
	 *
	 * @return void
	 */
	public function test_the_error_collector_is_read_after_the_hook(): void {
		$this->prepare_registration();

		$this->assertInstanceOf( 'WP_Error', rcp_errors() );

		add_action(
			'rcp_form_errors',
			static function (): void {
				rcp_errors()->add( 'rcipr_contract', self::BLOCKED_MESSAGE, 'register' );
			}
		);

		$this->process_registration();

		$this->assertContains(
			self::BLOCKED_MESSAGE,
			rcp_errors()->get_error_messages(),
			'Le collecteur d\'erreurs de la dépendance ne conserve plus ce qui lui est ajouté.'
		);
	}
}
