<?php
/**
 * La sonde ne mémorise rien — P-01 à P-04.
 *
 * C'est le test qui empêche quelqu'un d'« optimiser » la sonde. Sonder
 * l'environnement à chaque requête ressemble à du gaspillage, et la première
 * optimisation qui vienne à l'esprit est de retenir le résultat. Il ne faut
 * pas : la branche de code chargée par la dépendance est un état mutable, que
 * l'administrateur retourne depuis un écran (JE-003, et la suite `contract` le
 * surveille). Un résultat persisté serait faux dès la première bascule, et
 * faux silencieusement.
 *
 * Ce que cette suite établit, et ce qu'elle ne peut pas établir : PHP ne permet
 * pas de retirer une fonction déjà définie, donc un seul processus ne peut pas
 * reproduire la disparition de l'interface amont. Ce qui est établi ici est
 * tout ce qui en dépend : rien n'est écrit, l'option amont n'est jamais lue
 * pour décider, et l'avis de E-05 est bien produit à partir d'un diagnostic de
 * branche historique. La disparition elle-même est établie par la suite
 * `contract`, qui exécute le mécanisme de bascule amont, et en recette
 * (cahier des charges §11.7).
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Integration;

use Ip_Restriction_For_Restrict_Content\Admin\Dependency_Labels;
use Ip_Restriction_For_Restrict_Content\Admin\Requirements_Notice;
use Ip_Restriction_For_Restrict_Content\Domain\Dependency_Diagnosis;
use Ip_Restriction_For_Restrict_Content\Plugin;
use WP_UnitTestCase;

/**
 * Non-mémorisation de la sonde, et avis de E-05.
 */
final class ProbePersistenceTest extends WP_UnitTestCase {

	/**
	 * Option amont de branche, citée mais jamais lue pour décider.
	 *
	 * @var string
	 */
	private const UPSTREAM_VERSION_OPTION = 'restrict_content_chosen_version';

	/**
	 * Noms des options et transients portant une marque du plugin.
	 *
	 * La requête est directe : c'est la seule façon de constater qu'une
	 * option n'existe pas sans la nommer une par une, et la mise en cache y
	 * serait un contresens.
	 *
	 * @return list<string>
	 */
	private function plugin_option_names(): array {
		global $wpdb;

		$names = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options}
			 WHERE option_name LIKE '%rcipr%'
			    OR option_name LIKE '%ip_restriction_for_restrict_content%'
			    OR option_name LIKE '%ip-restriction-for-restrict-content%'"
		);

		return array_values( array_map( 'strval', (array) $names ) );
	}

	/**
	 * L'amorçage n'écrit aucune option ni aucun transient.
	 *
	 * @return void
	 */
	public function test_booting_writes_no_option_and_no_transient(): void {
		$before = $this->plugin_option_names();

		Plugin::from_environment()->register();

		$this->assertSame(
			$before,
			$this->plugin_option_names(),
			'Le plugin a écrit une option ou un transient à l\'amorçage. P-01 l\'interdit : '
			. 'un résultat de sonde persisté serait faux dès la première bascule de branche '
			. 'de la dépendance, et faux silencieusement.'
		);
	}

	/**
	 * Amorcer deux fois n'écrit toujours rien.
	 *
	 * Un cache introduit « pour la seconde requête » se verrait ici.
	 *
	 * @return void
	 */
	public function test_booting_twice_still_writes_nothing(): void {
		Plugin::from_environment()->register();

		$after_first = $this->plugin_option_names();

		Plugin::from_environment()->register();

		$this->assertSame( $after_first, $this->plugin_option_names() );
		$this->assertSame( array(), $after_first );
	}

	/**
	 * Le contrôle des prérequis a lieu à chaque requête, sur `plugins_loaded`.
	 *
	 * Ce n'est pas un détail d'implémentation : c'est P-03, et c'est une
	 * propriété à préserver.
	 *
	 * @return void
	 */
	public function test_the_probe_runs_on_every_request(): void {
		$this->assertSame(
			20,
			has_action( 'plugins_loaded', 'ip_restriction_for_restrict_content_boot' ),
			'L\'amorçage n\'est plus accroché à plugins_loaded en priorité 20 : la sonde ne '
			. 'conclut plus à chaque requête (P-03).'
		);
	}

	/**
	 * Chaque amorçage produit un constat neuf.
	 *
	 * La mémorisation autorisée est celle de l'instance, et elle s'arrête à la
	 * requête : deux amorçages ne doivent pas partager un rapport.
	 *
	 * @return void
	 */
	public function test_each_boot_produces_a_fresh_report(): void {
		$first  = Plugin::from_environment();
		$second = Plugin::from_environment();

		$this->assertNotSame( $first->capability_report(), $second->capability_report() );
		$this->assertNotSame( $first->dependency_diagnosis(), $second->dependency_diagnosis() );
	}

	/**
	 * L'option amont de branche n'entre pas dans la décision.
	 *
	 * Elle dit ce que la dépendance a décidé, pas ce qu'elle a effectivement
	 * chargé. Seule la présence des fonctions fait foi (B-02, architecture §7).
	 * Retourner l'option sans retirer l'interface ne doit donc rien changer.
	 *
	 * @return void
	 */
	public function test_the_upstream_branch_option_does_not_drive_the_verdict(): void {
		update_option( self::UPSTREAM_VERSION_OPTION, 'legacy' );

		$plugin = Plugin::from_environment();

		$this->assertTrue(
			$plugin->is_satisfied(),
			'Le plugin a conclu à l\'absence de la dépendance sur la seule foi de l\'option '
			. 'amont, alors que l\'interface est en mémoire. B-02 l\'interdit : l\'option est '
			. 'un détail d\'implémentation amont, susceptible de changer.'
		);
		$this->assertTrue( $plugin->dependency_diagnosis()->is_usable() );

		delete_option( self::UPSTREAM_VERSION_OPTION );
	}

	/**
	 * Un diagnostic de branche historique produit l'avis de E-05, avec sa
	 * cause et son action de retour.
	 *
	 * Le libellé est celui que verra l'administrateur, rendu par un WordPress
	 * réel : la suite `unit` établit que l'échappement est appelé, celle-ci
	 * établit ce qui sort.
	 *
	 * @return void
	 */
	public function test_a_legacy_branch_diagnosis_produces_the_expected_notice(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$legacy = Dependency_Diagnosis::from_observation( false, true );

		$notice = new Requirements_Notice(
			array( Dependency_Labels::missing_requirement( $legacy ) ),
			array( Dependency_Labels::remediation() )
		);

		ob_start();
		$notice->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'base de code historique', $output );
		$this->assertStringNotContainsString(
			'Restrict Content ou Restrict Content Pro, actif',
			$output,
			'L\'avis annonce la dépendance absente alors qu\'elle est active sur sa branche '
			. 'historique : c\'est exactement ce que E-05 proscrit.'
		);
		$this->assertStringContainsString( 'sans réactivation', $output );
	}

	/**
	 * Une dépendance absente produit, elle, l'avis générique.
	 *
	 * Sans ce second test, le précédent passerait aussi bien si l'avis était
	 * devenu invariant.
	 *
	 * @return void
	 */
	public function test_an_absent_dependency_produces_the_generic_notice(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$absent = Dependency_Diagnosis::from_observation( false, false );

		$notice = new Requirements_Notice( array( Dependency_Labels::missing_requirement( $absent ) ) );

		ob_start();
		$notice->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Restrict Content ou Restrict Content Pro, actif', $output );
		$this->assertStringNotContainsString( 'base de code historique', $output );
	}
}
