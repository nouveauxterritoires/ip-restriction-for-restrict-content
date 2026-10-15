<?php
/**
 * Tests de la validation à l'écriture — A-02, FNC-22, FNC-28.
 *
 * A-02 exige que toute règle de la liste d'autorisation soit validée **à
 * l'écriture avec la même sévérité qu'à la lecture**. C'est obtenu de la seule
 * façon qui ne puisse pas dériver : le même analyseur, appelé aux deux moments.
 * Deux analyseurs, même écrits le même jour, finissent par ne plus dire la
 * même chose.
 *
 * FNC-22 impose une asymétrie qu'il faut tenir : l'entrée rejetée est
 * **conservée telle quelle** dans le réglage, et signalée. Refuser
 * l'enregistrement entier ferait perdre à l'administrateur les quarante lignes
 * valides qu'il venait de coller à cause d'une faute de frappe sur la onzième.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Settings;

use Ip_Restriction_For_Restrict_Content\Rule\Rule_Rejection;
use Ip_Restriction_For_Restrict_Content\Settings\Settings;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Validator;
use Ip_Restriction_For_Restrict_Content\Tests\Unit\UnitTestCase;

/**
 * Normalisation et signalement à l'enregistrement.
 */
final class SettingsValidatorTest extends UnitTestCase {

	/**
	 * Les lignes sont ajustées et les doublons fusionnés, l'ordre d'origine
	 * conservé (FNC-28).
	 *
	 * @return void
	 */
	public function test_lines_are_trimmed_duplicates_merged_and_order_kept(): void {
		$validated = Settings_Validator::validate(
			array( 'allow_list' => "  10.0.0.3  \n\n10.0.0.1\n10.0.0.3\n\t10.0.0.2\t\n" )
		);

		$this->assertSame(
			"10.0.0.3\n10.0.0.1\n10.0.0.2",
			$validated->settings()->allow_list()
		);
	}

	/**
	 * Une entrée rejetée est conservée telle quelle dans le réglage (FNC-22).
	 *
	 * @return void
	 */
	public function test_a_rejected_entry_is_kept_in_the_setting(): void {
		$validated = Settings_Validator::validate(
			array( 'allow_list' => "10.0.0.1\nexemple.test\n10.0.0.2\n" )
		);

		$this->assertStringContainsString( 'exemple.test', $validated->settings()->allow_list() );
	}

	/**
	 * Une entrée rejetée est signalée avec son numéro de ligne et son motif.
	 *
	 * Le numéro porte sur le texte **tel qu'il sera stocké** : c'est celui que
	 * l'administrateur verra dans le champ, et un décalage renverrait vers la
	 * mauvaise ligne.
	 *
	 * @return void
	 */
	public function test_a_rejected_entry_is_reported_with_its_line_and_reason(): void {
		$validated = Settings_Validator::validate(
			array( 'allow_list' => "10.0.0.1\n\nexemple.test\n" )
		);

		$rejections = $validated->allow_list_rejections();

		$this->assertCount( 1, $rejections );
		$this->assertSame( 2, $rejections[0]->number(), 'La ligne vide a été retirée par la normalisation.' );
		$this->assertSame( Rule_Rejection::HOSTNAME, $rejections[0]->reason() );
		$this->assertSame( 'exemple.test', $rejections[0]->line() );
	}

	/**
	 * Une règle qui désigne toutes les adresses est refusée à l'écriture
	 * (A-02, SEC-12).
	 *
	 * @return void
	 */
	public function test_a_rule_covering_every_address_is_refused_on_write(): void {
		$validated = Settings_Validator::validate( array( 'allow_list' => "10.0.0.1\n0.0.0.0/0\n" ) );

		$this->assertCount( 1, $validated->allow_list_rejections() );
		$this->assertSame(
			Rule_Rejection::COVERS_EVERY_ADDRESS,
			$validated->allow_list_rejections()[0]->reason()
		);
	}

	/**
	 * Les trois listes sont validées, et chacune rapporte ses propres rejets.
	 *
	 * A-01 interdit un chemin commun paramétré par un nom de liste : le
	 * résultat expose donc trois accesseurs nommés, et non une table indexée
	 * par un nom de champ.
	 *
	 * @return void
	 */
	public function test_each_list_reports_its_own_rejections(): void {
		$validated = Settings_Validator::validate(
			array(
				'allow_list'      => "exemple.test\n",
				'block_list'      => "10.0.0.9-10.0.0.1\n",
				'trusted_proxies' => "*\n",
			)
		);

		$this->assertSame( Rule_Rejection::HOSTNAME, $validated->allow_list_rejections()[0]->reason() );
		$this->assertSame( Rule_Rejection::INVERTED_RANGE, $validated->block_list_rejections()[0]->reason() );
		$this->assertSame( Rule_Rejection::COVERS_EVERY_ADDRESS, $validated->trusted_proxies_rejections()[0]->reason() );
	}

	/**
	 * Une liste valide ne produit aucun signalement.
	 *
	 * @return void
	 */
	public function test_a_valid_list_reports_nothing(): void {
		$validated = Settings_Validator::validate(
			array(
				'allow_list'      => "192.0.2.1\n198.51.100.0/24\n",
				'block_list'      => "203.0.113.*\n",
				'trusted_proxies' => "2001:db8::1\n",
			)
		);

		$this->assertSame( array(), $validated->allow_list_rejections() );
		$this->assertSame( array(), $validated->block_list_rejections() );
		$this->assertSame( array(), $validated->trusted_proxies_rejections() );
		$this->assertFalse( $validated->has_rejections() );
	}

	/**
	 * Un signalement sur une seule liste suffit à ce qu'il y en ait un.
	 *
	 * @return void
	 */
	public function test_one_rejected_entry_is_enough_to_report(): void {
		$this->assertTrue(
			Settings_Validator::validate( array( 'block_list' => "exemple.test\n" ) )->has_rejections()
		);
	}

	/**
	 * Les autres réglages traversent la validation sans être altérés.
	 *
	 * Le message et l'en-tête ne relèvent pas de la grammaire : leur
	 * assainissement est l'affaire de la couche qui lit la requête, et c'est
	 * là que les fonctions de WordPress sont disponibles.
	 *
	 * @return void
	 */
	public function test_the_other_settings_pass_through(): void {
		$validated = Settings_Validator::validate(
			array(
				'blocked_message'      => 'Non, pas depuis ici.',
				'forwarded_header'     => 'X-Real-IP',
				'deletes_on_uninstall' => true,
			)
		);

		$this->assertSame( 'Non, pas depuis ici.', $validated->settings()->blocked_message() );
		$this->assertSame( 'X-Real-IP', $validated->settings()->forwarded_header() );
		$this->assertTrue( $validated->settings()->deletes_on_uninstall() );
	}

	/**
	 * Une entrée absente produit les réglages par défaut (FNC-27).
	 *
	 * @return void
	 */
	public function test_an_empty_submission_produces_the_defaults(): void {
		$validated = Settings_Validator::validate( array() );

		$this->assertSame( Settings::defaults()->to_array(), $validated->settings()->to_array() );
		$this->assertFalse( $validated->has_rejections() );
	}

	/**
	 * Les commentaires survivent à la normalisation.
	 *
	 * Une liste d'adresses se relit mal sans pouvoir y noter à quoi correspond
	 * un bloc : les retirer à l'enregistrement reviendrait à punir qui
	 * documente sa configuration.
	 *
	 * @return void
	 */
	public function test_comments_survive_normalisation(): void {
		$validated = Settings_Validator::validate(
			array( 'allow_list' => "# bureau de Lyon\n10.0.0.1\n" )
		);

		$this->assertStringContainsString( '# bureau de Lyon', $validated->settings()->allow_list() );
		$this->assertSame( array(), $validated->allow_list_rejections() );
	}

	/**
	 * Une liste soumise qui n'est pas du texte est traitée comme vide.
	 *
	 * Une soumission forgée peut porter un tableau là où le formulaire envoie
	 * une zone de texte. Le traiter comme vide est la fermeture sur l'inconnu
	 * appliquée à l'écriture : rien n'est accordé, et rien ne casse.
	 *
	 * @return void
	 */
	public function test_a_non_textual_list_is_treated_as_empty(): void {
		$validated = Settings_Validator::validate(
			array( 'allow_list' => array( 'pas', 'du', 'texte' ) )
		);

		$this->assertSame( '', $validated->settings()->allow_list() );
		$this->assertFalse( $validated->has_rejections() );
	}
}
