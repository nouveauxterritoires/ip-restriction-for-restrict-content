<?php
/**
 * Tests des libellés destinés à l'administrateur.
 *
 * Ces classes n'ont l'air de rien, et elles portent une exigence : FNC-22
 * demande que l'administrateur sache **pourquoi** une entrée a été ignorée. Dix
 * motifs traduits par la même phrase satisferaient la lettre de l'exigence et
 * en rateraient l'objet — d'où un test qui vérifie que les libellés sont
 * distincts, et pas seulement présents.
 *
 * Le repli sur une clé inconnue compte aussi : un motif ou une fonctionnalité
 * ajouté plus tard sans son libellé doit produire un texte, jamais une page
 * blanche.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Admin;

use Ip_Restriction_For_Restrict_Content\Admin\Capability_Labels;
use Ip_Restriction_For_Restrict_Content\Admin\Rejection_Labels;
use Ip_Restriction_For_Restrict_Content\Domain\Capability_Catalogue;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Rejection;
use Ip_Restriction_For_Restrict_Content\Tests\Unit\UnitTestCase;

/**
 * Libellés des motifs de refus et des fonctionnalités.
 */
final class LabelsTest extends UnitTestCase {

	/**
	 * Tous les motifs de refus déclarés.
	 *
	 * @return list<string>
	 */
	private function reasons(): array {
		return array(
			Rule_Rejection::SYNTAX,
			Rule_Rejection::MIXED_FAMILIES,
			Rule_Rejection::INVERTED_RANGE,
			Rule_Rejection::PREFIX_OUT_OF_RANGE,
			Rule_Rejection::WILDCARD_BEFORE_LITERAL,
			Rule_Rejection::COVERS_EVERY_ADDRESS,
			Rule_Rejection::WILDCARD_IN_CIDR,
			Rule_Rejection::HOSTNAME,
			Rule_Rejection::IPV6_WILDCARD,
			Rule_Rejection::AMBIGUOUS_OCTET,
		);
	}

	/**
	 * Chaque motif de refus a un libellé, et ils sont tous distincts.
	 *
	 * @return void
	 */
	public function test_every_rejection_reason_has_its_own_label(): void {
		$labels = array();

		foreach ( $this->reasons() as $reason ) {
			$label = Rejection_Labels::reason( $reason );

			$this->assertNotSame( '', $label, $reason . ' n\'a pas de libellé.' );
			$this->assertNotContains(
				$label,
				$labels,
				$reason . ' partage son libellé avec un autre motif : FNC-22 ne serait plus actionnable.'
			);

			$labels[] = $label;
		}

		$this->assertCount( 10, $labels );
	}

	/**
	 * Un motif inconnu produit un texte, et non une page blanche.
	 *
	 * @return void
	 */
	public function test_an_unknown_reason_still_produces_a_label(): void {
		$this->assertNotSame( '', Rejection_Labels::reason( 'motif_ajoute_plus_tard' ) );
	}

	/**
	 * Les motifs qui recommandent une forme de remplacement le font.
	 *
	 * Pour trois motifs, l'administrateur ne peut pas deviner la correction :
	 * le joker IPv6 se remplace par un CIDR, le zéro de tête s'écrit sans
	 * zéro, et un nom d'hôte se remplace par une adresse. Le libellé le dit.
	 *
	 * @return void
	 */
	public function test_the_labels_that_can_suggest_a_fix_do_so(): void {
		$this->assertStringContainsString( 'CIDR', Rejection_Labels::reason( Rule_Rejection::IPV6_WILDCARD ) );
		$this->assertStringContainsString( '010', Rejection_Labels::reason( Rule_Rejection::AMBIGUOUS_OCTET ) );
		$this->assertStringContainsString( 'adresse IP', Rejection_Labels::reason( Rule_Rejection::HOSTNAME ) );
	}

	/**
	 * Chaque fonctionnalité du catalogue a son libellé, et une clé inconnue
	 * se replie sur elle-même.
	 *
	 * @return void
	 */
	public function test_every_feature_has_a_label(): void {
		foreach ( Capability_Catalogue::features() as $feature ) {
			$this->assertNotSame( $feature, Capability_Labels::feature( $feature ), $feature . ' n\'a pas de libellé.' );
		}

		$this->assertSame( 'fonctionnalite_future', Capability_Labels::feature( 'fonctionnalite_future' ) );
	}

	/**
	 * Un état de capacité inconnu se replie sur lui-même.
	 *
	 * @return void
	 */
	public function test_an_unknown_capability_state_falls_back_to_itself(): void {
		$this->assertSame( 'etat_futur', Capability_Labels::state( 'etat_futur' ) );
	}
}
