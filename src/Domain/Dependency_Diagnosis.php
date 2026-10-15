<?php
/**
 * Diagnostic de l'état de la dépendance d'adhésions, sans dépendance à WordPress.
 *
 * Trois états, et non deux. « Absente » et « active mais sur sa base de code
 * historique » conduisent au même verdict — le plugin ne peut pas s'exécuter —
 * mais pas au même avis : c'est l'exigence E-05 du cahier des charges §5.3. Un
 * administrateur qui lit « dépendance absente » alors que l'extension figure,
 * active, dans sa liste, conclut que l'avis est faux et cherche ailleurs.
 *
 * La distinction se fait par capacité, jamais par nom de plugin, par numéro de
 * version ni par lecture de l'option amont `restrict_content_chosen_version`
 * (B-02, ADR-0001 mis à part, voir architecture §7). Cette classe ne constate
 * rien elle-même : elle reçoit deux observations et les qualifie.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * État constaté de la dépendance, qualifié.
 */
final class Dependency_Diagnosis {

	/**
	 * La dépendance n'est pas en mémoire.
	 *
	 * @var string
	 */
	public const ABSENT = 'absent';

	/**
	 * La dépendance est en mémoire, mais sur sa base de code historique.
	 *
	 * @var string
	 */
	public const LEGACY_BRANCH = 'legacy_branch';

	/**
	 * La dépendance est en mémoire et expose l'interface attendue.
	 *
	 * @var string
	 */
	public const USABLE = 'usable';

	/**
	 * État retenu.
	 *
	 * @var string
	 */
	private string $state;

	/**
	 * Construit un diagnostic.
	 *
	 * Le constructeur est privé : un diagnostic se constate, il ne se fabrique
	 * pas.
	 *
	 * @param string $state Un des trois états déclarés par cette classe.
	 */
	private function __construct( string $state ) {
		$this->state = $state;
	}

	/**
	 * Qualifie l'état de la dépendance à partir de deux observations.
	 *
	 * La seconde observation est ce qui permet de distinguer une dépendance
	 * absente d'une dépendance active sur sa branche historique : le fichier
	 * principal de la variante gratuite déclare son propre mécanisme de bascule
	 * quelle que soit la branche ensuite chargée. Sa présence atteste donc que
	 * l'extension est active ; l'absence de l'interface atteste que la branche
	 * chargée n'est pas celle qui l'expose (JE-003).
	 *
	 * @param bool $api_present      L'interface de programmation attendue est en mémoire.
	 * @param bool $bootstrap_present Le mécanisme d'amorçage de la dépendance est en mémoire.
	 * @return self
	 */
	public static function from_observation( bool $api_present, bool $bootstrap_present ): self {
		if ( $api_present ) {
			return new self( self::USABLE );
		}

		if ( $bootstrap_present ) {
			return new self( self::LEGACY_BRANCH );
		}

		return new self( self::ABSENT );
	}

	/**
	 * État retenu.
	 *
	 * @return string
	 */
	public function state(): string {
		return $this->state;
	}

	/**
	 * La dépendance expose l'interface attendue.
	 *
	 * @return bool
	 */
	public function is_usable(): bool {
		return self::USABLE === $this->state;
	}

	/**
	 * La dépendance est active mais charge sa base de code historique.
	 *
	 * @return bool
	 */
	public function is_legacy_branch(): bool {
		return self::LEGACY_BRANCH === $this->state;
	}

	/**
	 * La dépendance n'est pas en mémoire.
	 *
	 * @return bool
	 */
	public function is_absent(): bool {
		return self::ABSENT === $this->state;
	}
}
