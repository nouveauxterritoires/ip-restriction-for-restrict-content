<?php
/**
 * Réglages validés, et ce qui a été refusé en chemin.
 *
 * Les rejets sont exposés par trois accesseurs **nommés**, et non par une table
 * indexée par un nom de champ. C'est A-01 : les deux listes ne partagent aucun
 * chemin, et un appel paramétré par un nom de liste est précisément la faute
 * qui ferait lire la liste de blocage comme une liste d'autorisation.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Settings;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Rule\Rule_Rejection;

/**
 * Résultat immuable d'une validation de réglages.
 */
final class Validated_Settings {

	/**
	 * Réglages normalisés, prêts à être stockés.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Entrées refusées de la liste d'autorisation globale.
	 *
	 * @var list<Rule_Rejection>
	 */
	private array $allow_list_rejections;

	/**
	 * Entrées refusées de la liste de blocage.
	 *
	 * @var list<Rule_Rejection>
	 */
	private array $block_list_rejections;

	/**
	 * Entrées refusées de la liste de proxys de confiance.
	 *
	 * @var list<Rule_Rejection>
	 */
	private array $trusted_proxies_rejections;

	/**
	 * Construit un résultat de validation.
	 *
	 * @param Settings         $settings                   Réglages normalisés.
	 * @param Rule_Rejection[] $allow_list_rejections      Refus de la liste d'autorisation.
	 * @param Rule_Rejection[] $block_list_rejections      Refus de la liste de blocage.
	 * @param Rule_Rejection[] $trusted_proxies_rejections Refus de la liste de proxys.
	 */
	public function __construct(
		Settings $settings,
		array $allow_list_rejections,
		array $block_list_rejections,
		array $trusted_proxies_rejections
	) {
		$this->settings                   = $settings;
		$this->allow_list_rejections      = array_values( $allow_list_rejections );
		$this->block_list_rejections      = array_values( $block_list_rejections );
		$this->trusted_proxies_rejections = array_values( $trusted_proxies_rejections );
	}

	/**
	 * Réglages normalisés.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		return $this->settings;
	}

	/**
	 * Entrées refusées de la liste d'autorisation globale.
	 *
	 * @return list<Rule_Rejection>
	 */
	public function allow_list_rejections(): array {
		return array_values( $this->allow_list_rejections );
	}

	/**
	 * Entrées refusées de la liste de blocage.
	 *
	 * @return list<Rule_Rejection>
	 */
	public function block_list_rejections(): array {
		return array_values( $this->block_list_rejections );
	}

	/**
	 * Entrées refusées de la liste de proxys de confiance.
	 *
	 * @return list<Rule_Rejection>
	 */
	public function trusted_proxies_rejections(): array {
		return array_values( $this->trusted_proxies_rejections );
	}

	/**
	 * Indique si au moins une entrée a été refusée.
	 *
	 * @return bool
	 */
	public function has_rejections(): bool {
		return array() !== $this->allow_list_rejections
			|| array() !== $this->block_list_rejections
			|| array() !== $this->trusted_proxies_rejections;
	}
}
