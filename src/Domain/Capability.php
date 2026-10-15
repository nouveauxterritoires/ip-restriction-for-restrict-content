<?php
/**
 * Une capacité attendue de la dépendance, décrite sans dépendance à WordPress.
 *
 * Une capacité n'est pas un nom de fonction : c'est un symbole, la
 * fonctionnalité de ce plugin qui en dépend, le contexte dans lequel la
 * dépendance le charge, et la ligne amont qui l'atteste. Les quatre
 * informations sont nécessaires, et la troisième est celle qu'on oublie :
 * plusieurs accroches du cahier des charges §5.1 vivent dans des fichiers que
 * la dépendance ne charge qu'en administration (`is_admin()` dans
 * `core/includes/class-restrict-content.php:247`). Sonder leur présence sur une
 * requête de façade conclurait à leur absence, et l'avis de E-04 annoncerait
 * une fonctionnalité perdue qui ne l'est pas.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Description immuable d'une capacité attendue.
 */
final class Capability {

	/**
	 * Le symbole est une fonction.
	 *
	 * @var string
	 */
	public const FUNCTION_SYMBOL = 'function';

	/**
	 * Le symbole est une classe.
	 *
	 * @var string
	 */
	public const CLASS_SYMBOL = 'class';

	/**
	 * La dépendance charge le symbole sur toute requête.
	 *
	 * @var string
	 */
	public const CONTEXT_ANY = 'any';

	/**
	 * La dépendance ne charge le symbole qu'en administration.
	 *
	 * @var string
	 */
	public const CONTEXT_ADMIN = 'admin';

	/**
	 * Symbole PHP attendu.
	 *
	 * @var string
	 */
	private string $symbol;

	/**
	 * Nature du symbole.
	 *
	 * @var string
	 */
	private string $kind;

	/**
	 * Clé de la fonctionnalité de ce plugin qui en dépend.
	 *
	 * @var string
	 */
	private string $feature;

	/**
	 * Contexte dans lequel la dépendance charge le symbole.
	 *
	 * @var string
	 */
	private string $context;

	/**
	 * Accroche ou capacité que le symbole met à disposition.
	 *
	 * @var string
	 */
	private string $provides;

	/**
	 * Fichier et ligne amont qui attestent le symbole.
	 *
	 * @var string
	 */
	private string $evidence;

	/**
	 * Construit une description de capacité.
	 *
	 * @param string $symbol   Symbole PHP attendu.
	 * @param string $kind     Nature du symbole : fonction ou classe.
	 * @param string $feature  Clé de la fonctionnalité qui en dépend.
	 * @param string $context  Contexte de chargement amont.
	 * @param string $provides Accroche ou capacité mise à disposition.
	 * @param string $evidence Fichier et ligne amont constatés.
	 */
	public function __construct(
		string $symbol,
		string $kind,
		string $feature,
		string $context,
		string $provides,
		string $evidence
	) {
		$this->symbol   = $symbol;
		$this->kind     = $kind;
		$this->feature  = $feature;
		$this->context  = $context;
		$this->provides = $provides;
		$this->evidence = $evidence;
	}

	/**
	 * Symbole PHP attendu.
	 *
	 * @return string
	 */
	public function symbol(): string {
		return $this->symbol;
	}

	/**
	 * Nature du symbole.
	 *
	 * @return string
	 */
	public function kind(): string {
		return $this->kind;
	}

	/**
	 * Clé de la fonctionnalité qui en dépend.
	 *
	 * @return string
	 */
	public function feature(): string {
		return $this->feature;
	}

	/**
	 * Contexte de chargement amont.
	 *
	 * @return string
	 */
	public function context(): string {
		return $this->context;
	}

	/**
	 * Accroche ou capacité mise à disposition par le symbole.
	 *
	 * @return string
	 */
	public function provides(): string {
		return $this->provides;
	}

	/**
	 * Fichier et ligne amont constatés.
	 *
	 * @return string
	 */
	public function evidence(): string {
		return $this->evidence;
	}

	/**
	 * Indique si la capacité est observable dans le contexte de la requête.
	 *
	 * Une capacité que la dépendance ne charge qu'en administration n'est pas
	 * absente sur une requête de façade : elle n'y est pas observable. La
	 * nuance évite un avis faux.
	 *
	 * @param bool $is_admin La requête en cours est une requête d'administration.
	 * @return bool
	 */
	public function is_observable_in_admin_context( bool $is_admin ): bool {
		return self::CONTEXT_ANY === $this->context || $is_admin;
	}
}
