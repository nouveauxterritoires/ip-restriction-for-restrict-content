<?php
/**
 * Contrat : la bascule de branche de la dépendance, dans les deux variantes.
 *
 * C'est le constat qui gouverne toute la §5.2 du cahier des charges, et il va
 * plus loin que la question qui l'avait fait ouvrir (V-12) :
 * `rc_process_legacy_switch()` (`restrictcontent.php:676`) est un gestionnaire
 * AJAX enregistré sur `wp_ajax_rc_process_legacy_switch` (ligne 731), protégé
 * par la capacité `manage_options` (ligne 680) et par un nonce (ligne 681), qui
 * retourne l'option de branche **dans les deux sens** (lignes 695 et 706).
 *
 * La branche chargée n'est donc pas une propriété de l'installation : c'est un
 * état mutable, qui peut changer entre deux requêtes sans qu'aucun plugin soit
 * désactivé ni mis à jour (B-04).
 *
 * **Ces tests n'ignorent rien selon la variante : ils affirment, pour chacune,
 * ce qui doit être vrai.** Le mécanisme de bascule appartient au fichier
 * principal de la variante gratuite ; la variante commerciale n'a ni ce
 * fichier ni ce mécanisme. Affirmer son absence là-bas n'est pas une
 * concession, c'est la réponse au point ouvert V-10 — et elle est établie par
 * exécution plutôt que déduite d'un répertoire.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Contract;

use Ip_Restriction_For_Restrict_Content\Domain\Capability_Catalogue;
use Ip_Restriction_For_Restrict_Content\Tests\Support\Request_Halted;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * Le mécanisme amont de bascule de branche.
 */
final class BranchSwitchContractTest extends WP_UnitTestCase {

	/**
	 * Option dans laquelle la dépendance range la branche retenue.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'restrict_content_chosen_version';

	/**
	 * Valeur de l'option pour la branche moderne.
	 *
	 * @var string
	 */
	private const MODERN = '3.0';

	/**
	 * Valeur de l'option pour la branche historique.
	 *
	 * @var string
	 */
	private const LEGACY = 'legacy';

	/**
	 * Symbole du gestionnaire de bascule.
	 *
	 * @var string
	 */
	private const SWITCH_HANDLER = 'rc_process_legacy_switch';

	/**
	 * Indique si la variante chargée est la variante gratuite.
	 *
	 * La détection se fait par capacité, comme partout ailleurs : le fichier
	 * principal de la variante gratuite déclare son mécanisme de bascule avant
	 * tout aiguillage, et la variante commerciale ne le déclare pas du tout.
	 * Aucun nom de plugin, aucun numéro de version, aucun chemin de fichier
	 * n'est interrogé (B-02).
	 *
	 * @return bool
	 */
	private function is_free_variant(): bool {
		return function_exists( Capability_Catalogue::BOOTSTRAP_MARKER );
	}

	/**
	 * Déclenche le gestionnaire amont et absorbe son interruption.
	 *
	 * @return void
	 */
	private function trigger_switch(): void {
		Request_Halted::intercept_wp_die();

		ob_start();

		try {
			call_user_func( self::SWITCH_HANDLER );
		} catch ( Request_Halted $halted ) {
			unset( $halted );
		}

		ob_end_clean();
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
	 * Le mécanisme de bascule appartient à la variante gratuite, et à elle
	 * seule.
	 *
	 * C'est la réponse au point ouvert V-10, et elle vaut dans les deux sens :
	 * exécutée contre la variante gratuite, elle établit la présence du
	 * mécanisme ; exécutée contre la variante commerciale, elle établit son
	 * absence. Les deux exécutions sont nécessaires, et c'est pourquoi la
	 * suite tourne deux fois.
	 *
	 * @return void
	 */
	public function test_the_switch_mechanism_belongs_to_the_free_variant_only(): void {
		$this->assertSame(
			$this->is_free_variant(),
			function_exists( self::SWITCH_HANDLER ),
			'Le marqueur d\'amorçage et le gestionnaire de bascule ne vont plus de pair : '
			. 'la distinction entre « dépendance absente » et « branche historique » (E-05) '
			. 'repose sur le premier pour conclure au second.'
		);

		if ( $this->is_free_variant() ) {
			$this->assertNotFalse(
				has_action( 'wp_ajax_' . self::SWITCH_HANDLER, self::SWITCH_HANDLER ),
				'L\'amont a changé : la bascule n\'est plus atteignable par AJAX '
				. '(constaté en restrictcontent.php:731). L\'avis de E-05 nommerait une '
				. 'action de retour qui n\'existe plus.'
			);

			return;
		}

		$this->assertFalse(
			has_action( 'wp_ajax_' . self::SWITCH_HANDLER ),
			'La variante commerciale expose désormais le mécanisme de bascule de la variante '
			. 'gratuite : le point ouvert V-10 doit être rouvert, et l\'avis de E-05 revu pour '
			. 'nommer la bonne action de retour.'
		);
	}

	/**
	 * L'interface attendue est présente quelle que soit la variante.
	 *
	 * La variante commerciale n'a pas de branche historique : son interface
	 * est là sans condition. C'est la seconde moitié de la réponse à V-10.
	 *
	 * @return void
	 */
	public function test_the_api_is_present_in_both_variants(): void {
		$this->assertTrue(
			function_exists( Capability_Catalogue::API_MARKER ),
			'L\'interface attendue est absente : aucune fonctionnalité du plugin ne peut '
			. 's\'exécuter dans cet environnement (B-01).'
		);
	}

	/**
	 * Un administrateur muni du nonce retourne la branche, dans les deux sens.
	 *
	 * Contre la variante commerciale, l'affirmation change de nature et non de
	 * rigueur : il n'y a aucune branche à retourner, et l'option de branche ne
	 * doit pas apparaître du fait de la dépendance.
	 *
	 * @return void
	 */
	public function test_an_administrator_can_switch_the_branch_both_ways(): void {
		if ( ! $this->is_free_variant() ) {
			$this->assertFalse(
				get_option( self::VERSION_OPTION ),
				'La variante commerciale écrit désormais l\'option de branche de la variante '
				. 'gratuite : le routage entre les deux bases de code a changé (V-10).'
			);

			return;
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		update_option( self::VERSION_OPTION, self::MODERN );

		$_POST = array( 'rc_process_legacy_nonce' => wp_create_nonce( 'rc_process_legacy_nonce' ) );

		$this->trigger_switch();

		$this->assertSame(
			self::LEGACY,
			get_option( self::VERSION_OPTION ),
			'La bascule vers la branche historique n\'a plus lieu : le constat JE-003 a changé.'
		);

		$this->trigger_switch();

		$this->assertSame(
			self::MODERN,
			get_option( self::VERSION_OPTION ),
			'Le retour vers la branche moderne n\'a plus lieu : l\'avis de E-05 nommerait une '
			. 'action de retour qui n\'existe plus.'
		);
	}

	/**
	 * La bascule exige un nonce et la capacité.
	 *
	 * Ce n'est pas une faille, c'est une fonctionnalité — et c'est ce qui
	 * permet d'affirmer, dans l'avis de E-05, que celui qui le lit est
	 * exactement celui qui peut revenir en arrière.
	 *
	 * @return void
	 */
	public function test_the_switch_requires_a_nonce_and_the_capability(): void {
		if ( ! $this->is_free_variant() ) {
			$this->assertFalse( function_exists( self::SWITCH_HANDLER ) );

			return;
		}

		update_option( self::VERSION_OPTION, self::MODERN );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array( 'rc_process_legacy_nonce' => 'nonce-invalide' );
		$this->trigger_switch();

		$this->assertSame(
			self::MODERN,
			get_option( self::VERSION_OPTION ),
			'La bascule ne vérifie plus son nonce (constaté en restrictcontent.php:681).'
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = array( 'rc_process_legacy_nonce' => wp_create_nonce( 'rc_process_legacy_nonce' ) );
		$this->trigger_switch();

		$this->assertSame(
			self::MODERN,
			get_option( self::VERSION_OPTION ),
			'La bascule ne vérifie plus la capacité (constaté en restrictcontent.php:680) : '
			. 'un abonné peut désactiver ce plugin.'
		);
	}

	/**
	 * Le défaut SQL amont qui fausse l'orientation initiale est toujours là.
	 *
	 * Ce test ne protège rien : il surveille. Le jour où il échoue, le défaut
	 * a été corrigé en amont et les sites nouvellement installés sont orientés
	 * autrement (B-03, JE-003, V-13). C'est une information, et elle doit
	 * arriver par un échec de suite plutôt que par un incident. Aucun code de
	 * ce plugin n'en dépend.
	 *
	 * @return void
	 */
	public function test_the_upstream_routing_defect_is_still_present(): void {
		if ( ! class_exists( 'RC_Requirements_Check' ) ) {
			$this->assertFalse(
				$this->is_free_variant(),
				'La variante gratuite est chargée sans sa classe d\'amorçage : '
				. 'l\'environnement de test n\'est pas celui qu\'on croit.'
			);

			return;
		}

		$reflection = new ReflectionClass( 'RC_Requirements_Check' );
		$source     = (string) file_get_contents( (string) $reflection->getFileName() );

		$this->assertStringContainsString(
			"CONTAINS '[restrict]'",
			$source,
			'La détection amont par code court n\'utilise plus l\'opérateur SQL inexistant '
			. '« CONTAINS » : le défaut consigné en JE-003 a été corrigé. Relire le nouveau '
			. 'code — le routage des sites nouvellement installés a changé (V-13).'
		);
	}
}
