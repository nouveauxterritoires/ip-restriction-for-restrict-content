<?php
/**
 * Tests de l'avis de prérequis.
 *
 * L'avis est le seul retour que reçoit un administrateur dont l'environnement
 * ne permet pas au plugin de démarrer. Trois propriétés comptent : il nomme
 * précisément ce qui manque, il n'affiche rien à qui ne peut pas y remédier, et
 * il fait passer chaque libellé par une fonction d'échappement.
 *
 * Cette suite ne charge pas WordPress : `esc_html()` y est un bouchon. Elle
 * établit donc que l'échappement est **appelé**, ce qui est ce que le code doit
 * faire ; que WordPress échappe correctement est son affaire, et le résultat
 * réel est vérifié par la suite `integration`.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Ip_Restriction_For_Restrict_Content\Admin\Requirements_Notice;
use Ip_Restriction_For_Restrict_Content\Tests\Unit\UnitTestCase;

/**
 * Rendu de l'avis dans ses différents états.
 */
final class RequirementsNoticeTest extends UnitTestCase {

	/**
	 * Rend l'avis et renvoie le balisage remis à `wp_admin_notice()`.
	 *
	 * @param string[] $missing      Prérequis absents.
	 * @param bool     $can_activate Droit `activate_plugins` de l'utilisateur courant.
	 * @param string[] $hints        Indications d'action.
	 * @return string Balisage capturé, chaîne vide si l'avis ne s'affiche pas.
	 */
	private function render( array $missing, bool $can_activate = true, array $hints = array() ): string {
		$captured = '';

		Functions\when( 'current_user_can' )->justReturn( $can_activate );
		Functions\when( 'wp_admin_notice' )->alias(
			static function ( string $message ) use ( &$captured ): void {
				$captured = $message;
			}
		);

		( new Requirements_Notice( $missing, $hints ) )->render();

		return $captured;
	}

	/**
	 * Chaque prérequis absent est nommé dans l'avis.
	 *
	 * @return void
	 */
	public function test_every_missing_requirement_is_named(): void {
		$output = $this->render( array( 'PHP 8.1 ou plus récent', 'Restrict Content, actif' ) );

		$this->assertStringContainsString( 'PHP 8.1 ou plus récent', $output );
		$this->assertStringContainsString( 'Restrict Content, actif', $output );
	}

	/**
	 * Sans prérequis absent, l'avis ne s'affiche pas.
	 *
	 * @return void
	 */
	public function test_nothing_is_rendered_when_requirements_are_met(): void {
		$this->assertSame( '', $this->render( array() ) );
	}

	/**
	 * Un utilisateur qui ne peut pas activer de plugin ne voit pas l'avis :
	 * il n'a aucun moyen d'y remédier, et l'avis lui apprendrait l'état de
	 * l'installation sans contrepartie.
	 *
	 * @return void
	 */
	public function test_nothing_is_rendered_for_users_who_cannot_act(): void {
		$this->assertSame( '', $this->render( array( 'Restrict Content, actif' ), false ) );
	}

	/**
	 * Chaque libellé passe par `esc_html()`.
	 *
	 * Le bouchon marque ce qu'il reçoit : la présence du marqueur autour du
	 * libellé prouve que le code l'a fait traverser la fonction d'échappement,
	 * et son absence prouverait le contraire même si la sortie paraissait sûre.
	 *
	 * @return void
	 */
	public function test_every_label_goes_through_the_escaping_function(): void {
		Functions\when( 'esc_html' )->alias(
			static fn( string $text ): string => '[echappe]' . $text . '[/echappe]'
		);

		$output = $this->render( array( 'Restrict Content, actif' ) );

		$this->assertStringContainsString( '[echappe]Restrict Content, actif[/echappe]', $output );
	}

	/**
	 * Le constructeur ne conserve pas la référence du tableau reçu.
	 *
	 * Les conventions interdisent la mutation d'un argument : l'appelant doit
	 * pouvoir modifier son tableau après coup sans que l'avis change.
	 *
	 * @return void
	 */
	public function test_constructor_does_not_alias_its_argument(): void {
		$missing = array( 'Restrict Content, actif' );
		$notice  = new Requirements_Notice( $missing );

		$missing[] = 'Ajouté après coup';

		Functions\when( 'current_user_can' )->justReturn( true );

		$captured = '';
		Functions\when( 'wp_admin_notice' )->alias(
			static function ( string $message ) use ( &$captured ): void {
				$captured = $message;
			}
		);

		$notice->render();

		$this->assertStringNotContainsString( 'Ajouté après coup', $captured );
	}

	/**
	 * Une indication d'action est rendue après la liste des prérequis.
	 *
	 * E-05 exige que l'avis nomme l'action de retour, et non seulement la
	 * cause : celui qui lit l'avis est exactement celui qui peut revenir en
	 * arrière.
	 *
	 * @return void
	 */
	public function test_a_hint_is_rendered_after_the_requirements(): void {
		$output = $this->render(
			array( 'Restrict Content charge sa base de code historique' ),
			true,
			array( 'Repassez sur la base de code moderne.' )
		);

		$this->assertStringContainsString( 'Repassez sur la base de code moderne.', $output );
		$this->assertLessThan(
			strpos( $output, 'Repassez sur la base de code moderne.' ),
			(int) strpos( $output, '</ul>' ),
			'L\'indication doit suivre la liste des prérequis.'
		);
	}

	/**
	 * Une indication passe elle aussi par l'échappement.
	 *
	 * @return void
	 */
	public function test_every_hint_goes_through_the_escaping_function(): void {
		Functions\when( 'esc_html' )->alias(
			static fn( string $text ): string => '[echappe]' . $text . '[/echappe]'
		);

		$output = $this->render( array( 'un prérequis' ), true, array( 'Repassez sur la base moderne.' ) );

		$this->assertStringContainsString( '[echappe]Repassez sur la base moderne.[/echappe]', $output );
	}

	/**
	 * Sans indication, l'avis ne rend aucun paragraphe supplémentaire.
	 *
	 * @return void
	 */
	public function test_no_hint_renders_no_extra_paragraph(): void {
		$output = $this->render( array( 'un prérequis' ) );

		$this->assertStringEndsWith( '</ul>', $output );
	}
}
