<?php
/**
 * L'adresse retenue pour la requête, et d'où elle vient.
 *
 * Une adresse indéterminable est une **valeur**, pas une absence. La
 * représenter par `null` obligerait chaque appelant à se souvenir de tester, et
 * le premier oubli deviendrait une erreur fatale sur un chemin rare —
 * exactement le chemin d'un visiteur derrière un proxy mal configuré. Un objet
 * indéterminé ne correspond à aucune règle, ce qui est la fermeture exigée par
 * SEC-10, et il porte la raison de son indétermination, que FNC-29 affiche.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Address;

defined( 'ABSPATH' ) || exit;

/**
 * Adresse du visiteur, immuable, avec sa provenance.
 */
final class Client_Address {

	/**
	 * L'adresse de connexion est absente ou illisible.
	 *
	 * @var string
	 */
	public const NO_CONNECTION_ADDRESS = 'no_connection_address';

	/**
	 * Derrière un proxy déclaré, l'en-tête de transfert est absent.
	 *
	 * @var string
	 */
	public const MISSING_HEADER = 'missing_header';

	/**
	 * La valeur de l'en-tête n'est pas analysable.
	 *
	 * @var string
	 */
	public const HEADER_UNPARSABLE = 'header_unparsable';

	/**
	 * La chaîne ne contient que des proxys déclarés.
	 *
	 * @var string
	 */
	public const PROXY_CHAIN_EXHAUSTED = 'proxy_chain_exhausted';

	/**
	 * Adresse retenue, ou null si indéterminable.
	 *
	 * @var Ip_Number|null
	 */
	private ?Ip_Number $number;

	/**
	 * Provenance de l'adresse.
	 *
	 * @var Address_Source
	 */
	private Address_Source $source;

	/**
	 * Rang dans la chaîne de transfert, compté depuis la gauche, ou null.
	 *
	 * @var int|null
	 */
	private ?int $position;

	/**
	 * Raison de l'indétermination, chaîne vide si l'adresse est connue.
	 *
	 * @var string
	 */
	private string $reason;

	/**
	 * Construit une adresse déjà qualifiée.
	 *
	 * @param Ip_Number|null $number   Adresse retenue.
	 * @param Address_Source $source   Provenance.
	 * @param int|null       $position Rang dans la chaîne de transfert.
	 * @param string         $reason   Raison de l'indétermination.
	 */
	private function __construct( ?Ip_Number $number, Address_Source $source, ?int $position, string $reason ) {
		$this->number   = $number;
		$this->source   = $source;
		$this->position = $position;
		$this->reason   = $reason;
	}

	/**
	 * Adresse issue de la connexion (R-01).
	 *
	 * @param Ip_Number $number Adresse de connexion.
	 * @return self
	 */
	public static function from_connection( Ip_Number $number ): self {
		return new self( $number, Address_Source::CONNECTION, null, '' );
	}

	/**
	 * Adresse issue d'un en-tête de transfert (R-04, R-07).
	 *
	 * @param Ip_Number $number   Adresse retenue.
	 * @param int       $position Rang dans la chaîne, compté depuis la gauche.
	 * @return self
	 */
	public static function from_header( Ip_Number $number, int $position ): self {
		return new self( $number, Address_Source::FORWARDED_HEADER, $position, '' );
	}

	/**
	 * Adresse indéterminable (R-05).
	 *
	 * @param string $reason Raison, parmi les constantes de cette classe.
	 * @return self
	 */
	public static function undetermined( string $reason ): self {
		return new self( null, Address_Source::UNDETERMINED, null, $reason );
	}

	/**
	 * Adresse retenue.
	 *
	 * @return Ip_Number|null
	 */
	public function number(): ?Ip_Number {
		return $this->number;
	}

	/**
	 * Indique si une adresse a pu être déterminée.
	 *
	 * @return bool
	 */
	public function is_known(): bool {
		return null !== $this->number;
	}

	/**
	 * Provenance de l'adresse.
	 *
	 * @return Address_Source
	 */
	public function source(): Address_Source {
		return $this->source;
	}

	/**
	 * Rang dans la chaîne de transfert, compté depuis la gauche.
	 *
	 * @return int|null
	 */
	public function position(): ?int {
		return $this->position;
	}

	/**
	 * Raison de l'indétermination.
	 *
	 * @return string Chaîne vide si l'adresse est connue.
	 */
	public function reason(): string {
		return $this->reason;
	}

	/**
	 * Forme textuelle de l'adresse.
	 *
	 * @return string Chaîne vide si l'adresse est indéterminable.
	 */
	public function to_string(): string {
		return null === $this->number ? '' : $this->number->to_string();
	}
}
