<?php
/**
 * Tests de l'objet de réglages.
 *
 * Deux propriétés comptent, et la seconde est une exigence de sécurité.
 *
 * La première est FNC-27 : tous les champs de liste sont vides par défaut. Ce
 * n'est pas une facilité, c'est la seule forme sous laquelle un plugin qui
 * accorde des accès peut être installé sans risque — fraîchement activé, il ne
 * change aucun comportement observable.
 *
 * La seconde est A-01 : les deux listes ne partagent ni stockage, ni cache, ni
 * chemin de décision. Elles se ressemblent — deux champs de texte, la même
 * grammaire, le même moteur — et elles n'ont pas du tout les mêmes
 * conséquences en cas d'erreur. Un accesseur commun paramétré par un nom de
 * clé serait le chemin par lequel une confusion de champ transforme un blocage
 * en autorisation.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Settings;

use Ip_Restriction_For_Restrict_Content\Settings\Settings;
use Ip_Restriction_For_Restrict_Content\Tests\Unit\UnitTestCase;

/**
 * Forme et valeurs par défaut des réglages.
 */
final class SettingsTest extends UnitTestCase {

	/**
	 * Un plugin fraîchement activé ne porte aucune liste (FNC-27).
	 *
	 * @return void
	 */
	public function test_every_list_is_empty_by_default(): void {
		$settings = Settings::defaults();

		$this->assertSame( '', $settings->allow_list() );
		$this->assertSame( '', $settings->block_list() );
		$this->assertSame( '', $settings->trusted_proxies() );
		$this->assertFalse( $settings->deletes_on_uninstall() );
	}

	/**
	 * Aucun en-tête de transfert n'est lu par défaut (R-01).
	 *
	 * @return void
	 */
	public function test_no_forwarded_header_is_read_by_default(): void {
		$this->assertSame( Settings::NO_HEADER, Settings::defaults()->forwarded_header() );
	}

	/**
	 * Le message de blocage a une valeur par défaut, avare (FNC-10, FNC-11).
	 *
	 * @return void
	 */
	public function test_the_blocked_message_has_a_frugal_default(): void {
		$message = Settings::defaults()->blocked_message();

		$this->assertNotSame( '', $message );
		$this->assertStringNotContainsString( 'liste', $message, 'Le message ne révèle pas l\'existence d\'une liste (SEC-17).' );
		$this->assertStringNotContainsString( 'IP', $message, 'Le message ne nomme pas l\'adresse (FNC-11).' );
	}

	/**
	 * Les réglages se composent depuis un tableau, et les clés absentes
	 * prennent leur valeur par défaut.
	 *
	 * Une option écrite par une version antérieure du plugin n'aura pas
	 * toujours toutes les clés : ne pas se replier sur le défaut produirait
	 * une liste `null` au premier appel.
	 *
	 * @return void
	 */
	public function test_missing_keys_fall_back_to_their_default(): void {
		$settings = Settings::from_array( array( 'allow_list' => "10.0.0.1\n" ) );

		$this->assertSame( "10.0.0.1\n", $settings->allow_list() );
		$this->assertSame( '', $settings->block_list() );
		$this->assertSame( Settings::NO_HEADER, $settings->forwarded_header() );
	}

	/**
	 * Les deux listes ne sont pas atteignables par une clé.
	 *
	 * C'est A-01 vérifié sur la forme du code : si l'objet exposait un
	 * accesseur paramétré par un nom de liste, une faute d'appel suffirait à
	 * lire la liste de blocage comme une liste d'autorisation. Le test
	 * inspecte l'interface publique, parce que c'est la seule chose qu'un test
	 * puisse vérifier mécaniquement ici.
	 *
	 * @return void
	 */
	public function test_no_accessor_takes_a_list_name(): void {
		$methods = get_class_methods( Settings::class );

		foreach ( $methods as $method ) {
			$reflection = new \ReflectionMethod( Settings::class, $method );

			foreach ( $reflection->getParameters() as $parameter ) {
				$this->assertNotSame(
					'list',
					$parameter->getName(),
					sprintf( 'Settings::%s() prend une liste en paramètre : A-01 l\'interdit.', $method )
				);
				$this->assertNotSame( 'key', $parameter->getName() );
				$this->assertNotSame( 'scope', $parameter->getName() );
			}
		}

		$this->assertContains( 'allow_list', $methods );
		$this->assertContains( 'block_list', $methods );
	}

	/**
	 * Les réglages se relisent sous la forme qui sera stockée.
	 *
	 * @return void
	 */
	public function test_settings_read_back_as_the_stored_shape(): void {
		$stored = Settings::from_array(
			array(
				'allow_list'           => "10.0.0.1\n",
				'block_list'           => "10.0.0.2\n",
				'blocked_message'      => 'Non.',
				'trusted_proxies'      => "203.0.113.7\n",
				'forwarded_header'     => 'X-Forwarded-For',
				'deletes_on_uninstall' => true,
			)
		)->to_array();

		$this->assertSame( "10.0.0.1\n", $stored['allow_list'] );
		$this->assertSame( "10.0.0.2\n", $stored['block_list'] );
		$this->assertSame( 'Non.', $stored['blocked_message'] );
		$this->assertSame( "203.0.113.7\n", $stored['trusted_proxies'] );
		$this->assertSame( 'X-Forwarded-For', $stored['forwarded_header'] );
		$this->assertTrue( $stored['deletes_on_uninstall'] );
	}

	/**
	 * Un aller-retour par le tableau ne change rien.
	 *
	 * @return void
	 */
	public function test_a_round_trip_through_the_array_changes_nothing(): void {
		$settings = Settings::from_array(
			array(
				'allow_list'       => "192.0.2.1\n",
				'forwarded_header' => 'X-Real-IP',
			)
		);

		$this->assertSame( $settings->to_array(), Settings::from_array( $settings->to_array() )->to_array() );
	}

	/**
	 * Un en-tête hors de la liste fermée est ramené à « aucun ».
	 *
	 * Une option écrite à la main, ou par une version antérieure, ne doit pas
	 * pouvoir faire lire un en-tête que le §7.1 n'autorise pas.
	 *
	 * @return void
	 */
	public function test_a_header_outside_the_closed_list_falls_back_to_none(): void {
		$this->assertSame(
			Settings::NO_HEADER,
			Settings::from_array( array( 'forwarded_header' => 'Client-Ip' ) )->forwarded_header()
		);
	}

	/**
	 * Les valeurs non textuelles d'une option corrompue ne font pas tomber le
	 * plugin.
	 *
	 * @return void
	 */
	public function test_a_corrupted_option_does_not_break_the_object(): void {
		$settings = Settings::from_array(
			array(
				'allow_list'           => array( 'pas', 'une', 'chaîne' ),
				'deletes_on_uninstall' => 'oui',
			)
		);

		$this->assertSame( '', $settings->allow_list() );
		$this->assertTrue( $settings->deletes_on_uninstall() );
	}
}
