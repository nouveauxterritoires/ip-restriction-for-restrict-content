<?php
/**
 * Témoin d'accroche, pour établir un contrat par l'exécution.
 *
 * Un filtre ne se sonde pas par introspection : il est impossible de savoir
 * qu'un `apply_filters` existe dans un corps de fonction. La seule preuve
 * disponible est d'y brancher un témoin, de provoquer le chemin, et de
 * constater l'appel — avec ses arguments.
 *
 * Le témoin ne modifie jamais ce qu'il observe : branché sur un filtre, il
 * renvoie la valeur reçue telle quelle. Un témoin qui altérerait la valeur
 * ferait de la suite `contract` une suite de comportement, et masquerait la
 * cause d'un échec ailleurs.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Support;

/**
 * Enregistre les appels reçus sur une accroche.
 */
final class Hook_Witness {

	/**
	 * Nom de l'accroche observée.
	 *
	 * @var string
	 */
	private string $hook;

	/**
	 * Arguments reçus, un tableau par appel.
	 *
	 * @var list<array<int, mixed>>
	 */
	private array $calls = array();

	/**
	 * Branche un témoin sur une accroche.
	 *
	 * La priorité est volontairement très basse : le témoin observe ce que
	 * l'accroche transporte avant qu'un autre greffon ne l'ait modifié.
	 *
	 * @param string $hook          Nom de l'accroche.
	 * @param int    $accepted_args Nombre d'arguments transportés.
	 * @return self
	 */
	public static function watch( string $hook, int $accepted_args = 4 ): self {
		$witness = new self( $hook );

		add_filter(
			$hook,
			array( $witness, 'record' ),
			1,
			$accepted_args
		);

		return $witness;
	}

	/**
	 * Construit un témoin.
	 *
	 * @param string $hook Nom de l'accroche.
	 */
	private function __construct( string $hook ) {
		$this->hook = $hook;
	}

	/**
	 * Enregistre un appel et renvoie la première valeur inchangée.
	 *
	 * @param mixed ...$args Arguments transportés par l'accroche.
	 * @return mixed La première valeur reçue, telle quelle.
	 */
	public function record( ...$args ) {
		$this->calls[] = $args;

		return $args[0] ?? null;
	}

	/**
	 * Nom de l'accroche observée.
	 *
	 * @return string
	 */
	public function hook(): string {
		return $this->hook;
	}

	/**
	 * Nombre d'appels reçus.
	 *
	 * @return int
	 */
	public function call_count(): int {
		return count( $this->calls );
	}

	/**
	 * Arguments du premier appel reçu.
	 *
	 * @return array<int, mixed>
	 */
	public function first_call(): array {
		return $this->calls[0] ?? array();
	}

	/**
	 * Indique si l'accroche a été atteinte.
	 *
	 * @return bool
	 */
	public function fired(): bool {
		return array() !== $this->calls;
	}
}
