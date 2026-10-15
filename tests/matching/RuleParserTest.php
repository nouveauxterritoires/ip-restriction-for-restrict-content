<?php
/**
 * Tests de l'analyseur et de la grammaire close du §4.2.
 *
 * Deux obligations, et la seconde est celle qu'on oublie. La première : chaque
 * forme acceptée produit la règle attendue. La seconde : chaque contrainte de
 * G-01 à G-07 produit un rejet **et le motif attendu**. Un analyseur qui
 * rejetterait tout avec le même motif « syntaxe » satisferait FNC-20 et
 * rendrait FNC-22 inutilisable — l'administrateur lirait « ligne 11 ignorée :
 * syntaxe » sans savoir quoi corriger.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Matching;

use Ip_Restriction_For_Restrict_Content\Rule\Rule_Parser;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Rejection;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Set;
use WP_UnitTestCase;

/**
 * Analyse d'une liste, entrée par entrée.
 */
final class RuleParserTest extends WP_UnitTestCase {

	/**
	 * Analyse une entrée unique et renvoie l'ensemble obtenu.
	 *
	 * @param string $entry Une entrée.
	 * @return Rule_Set
	 */
	private function parse( string $entry ): Rule_Set {
		return Rule_Parser::parse( $entry );
	}

	/**
	 * Affirme qu'une entrée est acceptée, et renvoie ses bornes.
	 *
	 * @param string $entry Une entrée.
	 * @return array{0: string, 1: string}
	 */
	private function bounds_of( string $entry ): array {
		$set = $this->parse( $entry );

		$this->assertSame(
			array(),
			array_map(
				static fn( Rule_Rejection $rejection ): string => $rejection->reason(),
				$set->rejections()
			),
			sprintf( '« %s » devait être acceptée.', $entry )
		);
		$this->assertCount( 1, $set->rules() );

		$rule = $set->rules()[0];

		return array( $rule->low()->to_string(), $rule->high()->to_string() );
	}

	/**
	 * Entrées acceptées, avec les bornes attendues.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public function accepted_entries(): array {
		return array(
			'littéral IPv4 (FNC-13)'            => array( '226.216.136.78', '226.216.136.78', '226.216.136.78' ),
			'joker final (FNC-14)'              => array( '200.122.216.*', '200.122.216.0', '200.122.216.255' ),
			'joker sur trois segments (FNC-14)' => array( '186.*.*.*', '186.0.0.0', '186.255.255.255' ),
			'plage inclusive (FNC-15)'          => array( '159.7.89.231-159.7.89.240', '159.7.89.231', '159.7.89.240' ),
			'CIDR (FNC-16)'                     => array( '10.0.0.0/24', '10.0.0.0', '10.0.0.255' ),
			'plage à bornes jokers (FNC-17)'    => array( '149.58.*-149.60.*', '149.58.0.0', '149.60.255.255' ),
			'littéral IPv6 (FNC-18)'            => array( '2001:db8::1', '2001:db8::1', '2001:db8::1' ),
			'littéral IPv6 complet (FNC-18)'    => array( '2001:0db8:0000:0000:0000:0000:0000:0001', '2001:db8::1', '2001:db8::1' ),
			'CIDR IPv6 (FNC-18)'                => array( '2001:db8::/32', '2001:db8::', '2001:db8:ffff:ffff:ffff:ffff:ffff:ffff' ),
			'plage IPv6 (ADR-0005)'             => array( '2001:db8::1-2001:db8::ff', '2001:db8::1', '2001:db8::ff' ),
			'forme mixte (ADR-0005)'            => array( '::ffff:192.0.2.1', '::ffff:192.0.2.1', '::ffff:192.0.2.1' ),
			'espaces de bordure (FNC-19)'       => array( '   10.0.0.1   ', '10.0.0.1', '10.0.0.1' ),
			'espaces autour du tiret'           => array( '10.0.0.1 - 10.0.0.5', '10.0.0.1', '10.0.0.5' ),
		);
	}

	/**
	 * Chaque forme documentée est acceptée et normalisée comme le §4.3 le dit.
	 *
	 * @dataProvider accepted_entries
	 *
	 * @param string $entry Entrée analysée.
	 * @param string $low   Borne basse attendue.
	 * @param string $high  Borne haute attendue.
	 * @return void
	 */
	public function test_an_accepted_entry_normalises_as_specified( string $entry, string $low, string $high ): void {
		$this->assertSame( array( $low, $high ), $this->bounds_of( $entry ) );
	}

	/**
	 * Entrées refusées, avec le motif attendu.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function refused_entries(): array {
		return array(
			'G-01 familles mêlées'            => array( '10.0.0.1-::1', Rule_Rejection::MIXED_FAMILIES ),
			'G-02 plage inversée'             => array( '10.0.0.9-10.0.0.1', Rule_Rejection::INVERTED_RANGE ),
			'G-03 préfixe IPv4 trop grand'    => array( '10.0.0.0/33', Rule_Rejection::PREFIX_OUT_OF_RANGE ),
			'G-03 préfixe IPv6 trop grand'    => array( '2001:db8::/129', Rule_Rejection::PREFIX_OUT_OF_RANGE ),
			'G-04 joker avant un littéral'    => array( '10.*.0.1', Rule_Rejection::WILDCARD_BEFORE_LITERAL ),
			'G-05 joker seul'                 => array( '*', Rule_Rejection::COVERS_EVERY_ADDRESS ),
			'G-05 tous segments jokers'       => array( '*.*.*.*', Rule_Rejection::COVERS_EVERY_ADDRESS ),
			'G-05 CIDR universel IPv4'        => array( '0.0.0.0/0', Rule_Rejection::COVERS_EVERY_ADDRESS ),
			'G-05 CIDR universel IPv6'        => array( '::/0', Rule_Rejection::COVERS_EVERY_ADDRESS ),
			'G-05 plage universelle'          => array( '0.0.0.0-255.255.255.255', Rule_Rejection::COVERS_EVERY_ADDRESS ),
			'G-06 joker dans un CIDR'         => array( '10.0.*.0/24', Rule_Rejection::WILDCARD_IN_CIDR ),
			'G-07 nom d\'hôte'                => array( 'exemple.test', Rule_Rejection::HOSTNAME ),
			'G-07 nom d\'hôte dans une plage' => array( 'exemple.test-10.0.0.1', Rule_Rejection::HOSTNAME ),
			'joker IPv6 (ADR-0005)'           => array( '2001:db8::*', Rule_Rejection::IPV6_WILDCARD ),
			'plage de jokers IPv6'            => array( '2001:db8:1::*-2001:db8:3::*', Rule_Rejection::IPV6_WILDCARD ),
			'octet à zéro de tête'            => array( '010.0.0.1', Rule_Rejection::AMBIGUOUS_OCTET ),
			'segment hors bornes'             => array( '10.0.0.256', Rule_Rejection::SYNTAX ),
			'segments en nombre insuffisant'  => array( '10.0.1', Rule_Rejection::SYNTAX ),
			'joker partiel dans un segment'   => array( '10.0.0.1*', Rule_Rejection::SYNTAX ),
			'deux tirets'                     => array( '10.0.0.1-10.0.0.2-10.0.0.3', Rule_Rejection::SYNTAX ),
			'préfixe non numérique'           => array( '10.0.0.0/abc', Rule_Rejection::SYNTAX ),
			'préfixe vide'                    => array( '10.0.0.0/', Rule_Rejection::SYNTAX ),
			'préfixe négatif'                 => array( '10.0.0.0/-1', Rule_Rejection::SYNTAX ),
			'deux barres obliques'            => array( '10.0.0.0/24/24', Rule_Rejection::SYNTAX ),
			'plage sans borne droite'         => array( '10.0.0.1-', Rule_Rejection::SYNTAX ),
			'texte quelconque'                => array( 'bonjour', Rule_Rejection::HOSTNAME ),
			'nom d\'hôte avec préfixe'        => array( 'exemple.test/24', Rule_Rejection::HOSTNAME ),
			'zéro de tête dans un CIDR'       => array( '010.0.0.0/24', Rule_Rejection::AMBIGUOUS_OCTET ),
			'zéro de tête dans un joker'      => array( '010.0.0.*', Rule_Rejection::AMBIGUOUS_OCTET ),
			'segment de joker hors bornes'    => array( '300.0.0.*', Rule_Rejection::SYNTAX ),
			'trop de segments'                => array( '1.2.3.4.5.*', Rule_Rejection::SYNTAX ),
			'ponctuation'                     => array( '!!', Rule_Rejection::SYNTAX ),
		);
	}

	/**
	 * Chaque entrée invalide est rejetée avec le motif attendu.
	 *
	 * @dataProvider refused_entries
	 *
	 * @param string $entry  Entrée analysée.
	 * @param string $reason Motif attendu.
	 * @return void
	 */
	public function test_a_refused_entry_names_its_reason( string $entry, string $reason ): void {
		$set = $this->parse( $entry );

		$this->assertSame( array(), $set->rules(), sprintf( '« %s » ne devait produire aucune règle.', $entry ) );
		$this->assertCount( 1, $set->rejections() );

		$rejection = $set->rejections()[0];

		$this->assertSame( $reason, $rejection->reason(), sprintf( 'Motif du rejet de « %s ».', $entry ) );
		$this->assertSame( $entry, $rejection->line(), 'Le rejet conserve la ligne telle quelle (FNC-22).' );
		$this->assertSame( 1, $rejection->number(), 'Le rejet porte son numéro de ligne (FNC-22).' );
	}

	/**
	 * Les lignes vides et les espaces ne produisent ni règle ni rejet (FNC-19).
	 *
	 * @return void
	 */
	public function test_blank_lines_produce_neither_rule_nor_rejection(): void {
		$set = Rule_Parser::parse( "\n   \n10.0.0.1\n\t\n" );

		$this->assertCount( 1, $set->rules() );
		$this->assertSame( array(), $set->rejections() );
	}

	/**
	 * Une ligne de commentaire est ignorée, et un commentaire en fin de ligne
	 * ne gêne pas l'analyse.
	 *
	 * La grammaire du §4.2 prévoit le commentaire : une liste d'adresses se
	 * relit mal sans pouvoir y noter à quoi correspond un bloc.
	 *
	 * @return void
	 */
	public function test_comments_are_ignored(): void {
		$set = Rule_Parser::parse( "# bureau de Lyon\n10.0.0.1 # poste de garde\n" );

		$this->assertCount( 1, $set->rules() );
		$this->assertSame( array(), $set->rejections() );
		$this->assertSame( '10.0.0.1', $set->rules()[0]->source() );
	}

	/**
	 * Le numéro de ligne d'un rejet est celui de la ligne d'origine, lignes
	 * vides et commentaires compris.
	 *
	 * Sans cela, le signalement de FNC-22 renverrait l'administrateur vers la
	 * mauvaise ligne de son champ — un défaut qui ne se voit qu'avec une liste
	 * longue, c'est-à-dire en production.
	 *
	 * @return void
	 */
	public function test_a_rejection_carries_the_original_line_number(): void {
		$set = Rule_Parser::parse( "10.0.0.1\n\n# un commentaire\nexemple.test\n10.0.0.2\n10.0.0.9-10.0.0.1\n" );

		$this->assertCount( 2, $set->rules() );
		$this->assertCount( 2, $set->rejections() );
		$this->assertSame( 4, $set->rejections()[0]->number() );
		$this->assertSame( Rule_Rejection::HOSTNAME, $set->rejections()[0]->reason() );
		$this->assertSame( 6, $set->rejections()[1]->number() );
		$this->assertSame( Rule_Rejection::INVERTED_RANGE, $set->rejections()[1]->reason() );
	}

	/**
	 * Les fins de ligne de Windows sont traitées comme les autres.
	 *
	 * Une liste collée depuis un tableur ou un courriel les porte, et une
	 * entrée rejetée pour un retour chariot invisible serait indéchiffrable.
	 *
	 * @return void
	 */
	public function test_windows_line_endings_are_handled(): void {
		$set = Rule_Parser::parse( "10.0.0.1\r\n10.0.0.2\r\n" );

		$this->assertCount( 2, $set->rules() );
		$this->assertSame( array(), $set->rejections() );
	}

	/**
	 * Une règle invalide ne rend pas la liste invalide (D-02).
	 *
	 * @return void
	 */
	public function test_an_invalid_rule_does_not_invalidate_the_list(): void {
		$set = Rule_Parser::parse( "10.0.0.1\nexemple.test\n10.0.0.2\n" );

		$this->assertCount( 2, $set->rules() );
		$this->assertCount( 1, $set->rejections() );
		$this->assertFalse( $set->is_empty() );
	}

	/**
	 * Une liste dont toute entrée est rejetée se comporte comme une liste vide
	 * (FNC-23).
	 *
	 * @return void
	 */
	public function test_a_fully_invalid_list_behaves_like_an_empty_one(): void {
		$set = Rule_Parser::parse( "exemple.test\n*\n10.0.0.0/33\n" );

		$this->assertSame( array(), $set->rules() );
		$this->assertCount( 3, $set->rejections() );
		$this->assertTrue( $set->is_empty() );
	}

	/**
	 * Une liste vide est vide, et une liste vide est disponible comme valeur.
	 *
	 * @return void
	 */
	public function test_an_empty_list_is_empty(): void {
		$this->assertTrue( Rule_Parser::parse( '' )->is_empty() );
		$this->assertTrue( Rule_Set::empty()->is_empty() );
		$this->assertSame( array(), Rule_Set::empty()->rules() );
		$this->assertSame( array(), Rule_Set::empty()->rejections() );
	}

	/**
	 * L'ensemble est composable depuis son texte, sans passer par l'analyseur.
	 *
	 * @return void
	 */
	public function test_a_set_is_built_from_its_text(): void {
		$set = Rule_Set::from_text( "10.0.0.1\n" );

		$this->assertCount( 1, $set->rules() );
	}

	/**
	 * L'ordre d'origine des règles est conservé (FNC-28).
	 *
	 * @return void
	 */
	public function test_the_original_order_is_preserved(): void {
		$set = Rule_Parser::parse( "10.0.0.3\n10.0.0.1\n10.0.0.2\n" );

		$this->assertSame(
			array( '10.0.0.3', '10.0.0.1', '10.0.0.2' ),
			array_map(
				static fn( $rule ): string => $rule->source(),
				$set->rules()
			)
		);
	}

	/**
	 * Les accesseurs ne laissent pas modifier l'ensemble après coup.
	 *
	 * @return void
	 */
	public function test_the_set_cannot_be_altered_through_its_accessors(): void {
		$set   = Rule_Parser::parse( "10.0.0.1\nexemple.test\n" );
		$rules = $set->rules();

		$rules[] = $rules[0];

		$this->assertCount( 1, $set->rules() );

		$rejections   = $set->rejections();
		$rejections[] = $rejections[0];

		$this->assertCount( 1, $set->rejections() );
	}
}
