<?php
/**
 * Lecture de l'environnement de la requête — le seul point qui lise `$_SERVER`.
 *
 * Un seul point d'entrée pour la donnée la plus sensible du plugin, et c'est ce
 * qui rend les onze scénarios du §11.3 exhaustifs : si un autre fichier lisait
 * un en-tête, aucun test de résolution ne le couvrirait. Cette classe ne décide
 * rien — elle ne sait même pas ce qu'est un proxy de confiance.
 *
 * La liste des en-têtes lisibles est **fermée** (§7.1, O-07). Ce n'est pas une
 * commodité d'interface : tout en-tête est écrit par le client, et chaque nom
 * ajouté à cette liste est une voie de contournement de plus à défendre. Un nom
 * hors liste n'est jamais lu, même écrit dans le réglage — et `Client-Ip`, celui
 * auquel la dépendance fait confiance en premier (JE-005), n'y est pas.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Environnement de la requête en cours.
 */
final class Server_Environment {

	/**
	 * Valeur du réglage lorsqu'aucun en-tête ne doit être lu (défaut).
	 *
	 * @var string
	 */
	public const NO_HEADER = 'none';

	/**
	 * En-têtes lisibles, et la clé de `$_SERVER` qui les porte.
	 *
	 * @var array<string, string>
	 */
	private const READABLE_HEADERS = array(
		'X-Forwarded-For'  => 'HTTP_X_FORWARDED_FOR',
		'CF-Connecting-IP' => 'HTTP_CF_CONNECTING_IP',
		'True-Client-IP'   => 'HTTP_TRUE_CLIENT_IP',
		'X-Real-IP'        => 'HTTP_X_REAL_IP',
	);

	/**
	 * Adresse de la connexion.
	 *
	 * @var string
	 */
	private string $connection_address;

	/**
	 * Valeurs des en-têtes lisibles présentes dans la requête.
	 *
	 * @var array<string, string>
	 */
	private array $headers;

	/**
	 * Construit un environnement déjà lu.
	 *
	 * @param string                $connection_address Adresse de la connexion.
	 * @param array<string, string> $headers            En-têtes lisibles présents.
	 */
	private function __construct( string $connection_address, array $headers ) {
		$this->connection_address = $connection_address;
		$this->headers            = $headers;
	}

	/**
	 * Lit l'environnement de la requête en cours.
	 *
	 * Seuls `REMOTE_ADDR` et les en-têtes de la liste fermée sont lus : ce qui
	 * n'est pas lu ne peut pas être évalué par erreur.
	 *
	 * @return self
	 */
	public static function from_globals(): self {
		$headers = array();

		foreach ( self::READABLE_HEADERS as $name => $key ) {
			if ( ! isset( $_SERVER[ $key ] ) ) {
				continue;
			}

			$headers[ $name ] = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
		}

		$address = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		return new self( $address, $headers );
	}

	/**
	 * Compose un environnement explicitement.
	 *
	 * C'est ce qui permet à l'écran de diagnostic de FNC-29 de montrer une
	 * requête donnée, et aux tests de ne pas bricoler les superglobales.
	 *
	 * @param string                $connection_address Adresse de la connexion.
	 * @param array<string, string> $headers            En-têtes, indexés par leur nom.
	 * @return self
	 */
	public static function from_values( string $connection_address, array $headers = array() ): self {
		$readable = array();

		foreach ( $headers as $name => $value ) {
			if ( isset( self::READABLE_HEADERS[ $name ] ) ) {
				$readable[ $name ] = (string) $value;
			}
		}

		return new self( $connection_address, $readable );
	}

	/**
	 * Noms des en-têtes lisibles, dans l'ordre du réglage.
	 *
	 * @return list<string>
	 */
	public static function readable_headers(): array {
		return array_keys( self::READABLE_HEADERS );
	}

	/**
	 * Adresse de la connexion, telle que le serveur web l'a renseignée.
	 *
	 * @return string Chaîne vide si elle est absente.
	 */
	public function connection_address(): string {
		return $this->connection_address;
	}

	/**
	 * Valeur d'un en-tête de transfert déclaré.
	 *
	 * @param string $name Nom de l'en-tête déclaré dans le réglage.
	 * @return string|null La valeur, ou null si l'en-tête n'est pas lisible ou absent.
	 */
	public function forwarded_header( string $name ): ?string {
		return $this->headers[ $name ] ?? null;
	}
}
