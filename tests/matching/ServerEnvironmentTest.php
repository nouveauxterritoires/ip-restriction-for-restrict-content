<?php
/**
 * Tests du seul point du plugin qui lise `$_SERVER`.
 *
 * Un seul point d'entrée pour la donnée la plus sensible du plugin, c'est ce
 * qui rend les onze scénarios du §11.3 exhaustifs : si un autre fichier lisait
 * un en-tête, aucun test de résolution ne le couvrirait.
 *
 * La liste des en-têtes lisibles est **fermée** (§7.1, O-07). Un nom d'en-tête
 * arbitraire est une surface d'erreur sans bénéfice, et surtout : tout en-tête
 * est écrit par le client, donc chaque nom ajouté à cette liste est une voie
 * de contournement de plus à défendre.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Matching;

use Ip_Restriction_For_Restrict_Content\Http\Server_Environment;
use WP_UnitTestCase;

/**
 * Lecture de l'environnement de la requête.
 */
final class ServerEnvironmentTest extends WP_UnitTestCase {

	/**
	 * Valeurs de `$_SERVER` à restaurer après chaque test.
	 *
	 * @var array<string, mixed>
	 */
	private array $server = array();

	/**
	 * Mémorise l'environnement réel.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->server = $_SERVER;
	}

	/**
	 * Restaure l'environnement réel.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_SERVER = $this->server;

		parent::tear_down();
	}

	/**
	 * L'adresse de connexion est lue dans `REMOTE_ADDR`.
	 *
	 * @return void
	 */
	public function test_the_connection_address_comes_from_remote_addr(): void {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.9';

		$this->assertSame( '198.51.100.9', Server_Environment::from_globals()->connection_address() );
	}

	/**
	 * Une adresse de connexion absente est une chaîne vide, et non une erreur.
	 *
	 * @return void
	 */
	public function test_a_missing_connection_address_is_an_empty_string(): void {
		unset( $_SERVER['REMOTE_ADDR'] );

		$this->assertSame( '', Server_Environment::from_globals()->connection_address() );
	}

	/**
	 * Chacun des quatre en-têtes de la liste fermée est lisible, sous son nom.
	 *
	 * @return void
	 */
	public function test_each_closed_list_header_is_readable(): void {
		$_SERVER['HTTP_X_FORWARDED_FOR']  = '192.0.2.1';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '192.0.2.2';
		$_SERVER['HTTP_TRUE_CLIENT_IP']   = '192.0.2.3';
		$_SERVER['HTTP_X_REAL_IP']        = '192.0.2.4';

		$environment = Server_Environment::from_globals();

		$this->assertSame( '192.0.2.1', $environment->forwarded_header( 'X-Forwarded-For' ) );
		$this->assertSame( '192.0.2.2', $environment->forwarded_header( 'CF-Connecting-IP' ) );
		$this->assertSame( '192.0.2.3', $environment->forwarded_header( 'True-Client-IP' ) );
		$this->assertSame( '192.0.2.4', $environment->forwarded_header( 'X-Real-IP' ) );
	}

	/**
	 * Aucun en-tête n'est lu quand l'administrateur n'en a déclaré aucun.
	 *
	 * C'est la valeur par défaut du réglage, et c'est R-01.
	 *
	 * @return void
	 */
	public function test_no_header_is_read_when_none_is_declared(): void {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.1';

		$environment = Server_Environment::from_globals();

		$this->assertNull( $environment->forwarded_header( Server_Environment::NO_HEADER ) );
		$this->assertNull( $environment->forwarded_header( '' ) );
	}

	/**
	 * Un nom d'en-tête hors de la liste fermée n'est jamais lu.
	 *
	 * `Client-Ip` est précisément l'en-tête auquel la dépendance fait confiance
	 * en premier (JE-005) : il ne doit pas être atteignable, même en écrivant
	 * son nom dans le réglage.
	 *
	 * @return void
	 */
	public function test_a_header_outside_the_closed_list_is_never_read(): void {
		$_SERVER['HTTP_CLIENT_IP']   = '192.0.2.1';
		$_SERVER['HTTP_X_FORWARDED'] = '192.0.2.2';

		$environment = Server_Environment::from_globals();

		$this->assertNull( $environment->forwarded_header( 'Client-Ip' ) );
		$this->assertNull( $environment->forwarded_header( 'X-Forwarded' ) );
		$this->assertNull( $environment->forwarded_header( 'HTTP_CLIENT_IP' ) );
	}

	/**
	 * Un en-tête déclaré mais absent de la requête est null.
	 *
	 * @return void
	 */
	public function test_a_declared_but_absent_header_is_null(): void {
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$this->assertNull( Server_Environment::from_globals()->forwarded_header( 'X-Forwarded-For' ) );
	}

	/**
	 * La liste fermée est exactement celle du §7.1.
	 *
	 * Ce test fige la décision O-07 : toute entrée ajoutée à cette liste est
	 * une voie de contournement de plus, et doit être une décision, pas un
	 * ajout de passage.
	 *
	 * @return void
	 */
	public function test_the_closed_list_is_exactly_the_four_declared_headers(): void {
		$this->assertSame(
			array( 'X-Forwarded-For', 'CF-Connecting-IP', 'True-Client-IP', 'X-Real-IP' ),
			Server_Environment::readable_headers()
		);
	}

	/**
	 * L'environnement est composable sans toucher aux superglobales.
	 *
	 * C'est ce qui permet aux tests de résolution de rester purs, et à
	 * l'écran de diagnostic de FNC-29 de montrer une requête donnée.
	 *
	 * @return void
	 */
	public function test_an_environment_can_be_composed_explicitly(): void {
		$environment = Server_Environment::from_values( '203.0.113.7', array( 'X-Forwarded-For' => '198.51.100.9' ) );

		$this->assertSame( '203.0.113.7', $environment->connection_address() );
		$this->assertSame( '198.51.100.9', $environment->forwarded_header( 'X-Forwarded-For' ) );
		$this->assertNull( $environment->forwarded_header( 'X-Real-IP' ) );
	}
}
