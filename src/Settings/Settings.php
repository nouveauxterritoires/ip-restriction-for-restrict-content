<?php
/**
 * L'ensemble des réglages globaux, sans dépendance à WordPress.
 *
 * Deux propriétés gouvernent cette classe, et la seconde est une exigence de
 * sécurité.
 *
 * FNC-27 : tous les champs de liste sont vides par défaut. Ce n'est pas une
 * facilité, c'est la seule forme sous laquelle un plugin qui accorde des accès
 * peut être installé sans risque — fraîchement activé, il ne change aucun
 * comportement observable.
 *
 * A-01 : les deux listes ne partagent ni stockage, ni cache, ni chemin de
 * décision. Elles se ressemblent — deux champs de texte, la même grammaire, le
 * même moteur — et n'ont pas du tout les mêmes conséquences en cas d'erreur :
 * une erreur par excès sur la liste d'autorisation est une fuite d'accès
 * silencieuse, la même sur la liste de blocage est bruyante. Aucun accesseur
 * n'est donc paramétré par un nom de liste : une confusion de champ ne doit pas
 * pouvoir transformer un blocage en autorisation.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Réglages immuables.
 */
final class Settings {

	/**
	 * Valeur du réglage d'en-tête lorsqu'aucun ne doit être lu.
	 *
	 * @var string
	 */
	public const NO_HEADER = 'none';

	/**
	 * En-têtes de transfert que le réglage accepte (§7.1, O-07).
	 *
	 * La liste est fermée : un nom d'en-tête arbitraire est une surface
	 * d'erreur sans bénéfice, et tout en-tête est écrit par le client.
	 *
	 * @var list<string>
	 */
	public const READABLE_HEADERS = array(
		'X-Forwarded-For',
		'CF-Connecting-IP',
		'True-Client-IP',
		'X-Real-IP',
	);

	/**
	 * Message de blocage par défaut.
	 *
	 * Délibérément avare : il ne nomme ni l'adresse, ni la règle, ni
	 * l'existence d'une liste (FNC-11, SEC-17). La traduction est faite par la
	 * couche d'application ; cette classe ne dépend pas de WordPress.
	 *
	 * @var string
	 */
	public const DEFAULT_BLOCKED_MESSAGE = 'L\'inscription n\'est pas possible depuis cette connexion.';

	/**
	 * Liste d'autorisation globale, telle que saisie.
	 *
	 * @var string
	 */
	private string $allow_list;

	/**
	 * Liste de blocage d'inscription, telle que saisie.
	 *
	 * @var string
	 */
	private string $block_list;

	/**
	 * Message affiché lorsqu'une inscription est bloquée.
	 *
	 * @var string
	 */
	private string $blocked_message;

	/**
	 * Proxys de confiance déclarés, telle que saisie.
	 *
	 * @var string
	 */
	private string $trusted_proxies;

	/**
	 * Nom de l'en-tête de transfert à lire, ou `none`.
	 *
	 * @var string
	 */
	private string $forwarded_header;

	/**
	 * L'administrateur a demandé la suppression des réglages à la
	 * désinstallation.
	 *
	 * @var bool
	 */
	private bool $deletes_on_uninstall;

	/**
	 * Construit des réglages déjà normalisés.
	 *
	 * @param string $allow_list           Liste d'autorisation globale.
	 * @param string $block_list           Liste de blocage d'inscription.
	 * @param string $blocked_message      Message de blocage.
	 * @param string $trusted_proxies      Proxys de confiance.
	 * @param string $forwarded_header     En-tête de transfert à lire.
	 * @param bool   $deletes_on_uninstall Suppression à la désinstallation.
	 */
	private function __construct(
		string $allow_list,
		string $block_list,
		string $blocked_message,
		string $trusted_proxies,
		string $forwarded_header,
		bool $deletes_on_uninstall
	) {
		$this->allow_list           = $allow_list;
		$this->block_list           = $block_list;
		$this->blocked_message      = $blocked_message;
		$this->trusted_proxies      = $trusted_proxies;
		$this->forwarded_header     = $forwarded_header;
		$this->deletes_on_uninstall = $deletes_on_uninstall;
	}

	/**
	 * Réglages d'un plugin fraîchement activé (FNC-27).
	 *
	 * @return self
	 */
	public static function defaults(): self {
		return new self( '', '', self::DEFAULT_BLOCKED_MESSAGE, '', self::NO_HEADER, false );
	}

	/**
	 * Compose des réglages depuis un tableau stocké.
	 *
	 * Chaque clé absente ou corrompue prend sa valeur par défaut : une option
	 * écrite par une version antérieure du plugin n'aura pas toujours toutes
	 * les clés, et un réglage lu `null` serait une erreur fatale au premier
	 * appel.
	 *
	 * @param array<string, mixed> $stored Tableau stocké.
	 * @return self
	 */
	public static function from_array( array $stored ): self {
		$header = self::text( $stored, 'forwarded_header', self::NO_HEADER );

		return new self(
			self::text( $stored, 'allow_list', '' ),
			self::text( $stored, 'block_list', '' ),
			self::text( $stored, 'blocked_message', self::DEFAULT_BLOCKED_MESSAGE ),
			self::text( $stored, 'trusted_proxies', '' ),
			in_array( $header, self::READABLE_HEADERS, true ) ? $header : self::NO_HEADER,
			! empty( $stored['deletes_on_uninstall'] )
		);
	}

	/**
	 * Lit une valeur textuelle du tableau stocké.
	 *
	 * @param array<string, mixed> $stored   Tableau stocké.
	 * @param string               $key      Clé lue.
	 * @param string               $fallback Valeur de remplacement.
	 * @return string
	 */
	private static function text( array $stored, string $key, string $fallback ): string {
		$value = $stored[ $key ] ?? null;

		return is_string( $value ) ? $value : $fallback;
	}

	/**
	 * Liste d'autorisation globale.
	 *
	 * @return string
	 */
	public function allow_list(): string {
		return $this->allow_list;
	}

	/**
	 * Liste de blocage d'inscription.
	 *
	 * @return string
	 */
	public function block_list(): string {
		return $this->block_list;
	}

	/**
	 * Message affiché lorsqu'une inscription est bloquée.
	 *
	 * @return string
	 */
	public function blocked_message(): string {
		return $this->blocked_message;
	}

	/**
	 * Proxys de confiance déclarés.
	 *
	 * @return string
	 */
	public function trusted_proxies(): string {
		return $this->trusted_proxies;
	}

	/**
	 * Nom de l'en-tête de transfert à lire.
	 *
	 * @return string `none` si aucun en-tête ne doit être lu.
	 */
	public function forwarded_header(): string {
		return $this->forwarded_header;
	}

	/**
	 * Indique si les réglages doivent être supprimés à la désinstallation.
	 *
	 * @return bool
	 */
	public function deletes_on_uninstall(): bool {
		return $this->deletes_on_uninstall;
	}

	/**
	 * Forme stockée des réglages.
	 *
	 * @return array<string, bool|string>
	 */
	public function to_array(): array {
		return array(
			'allow_list'           => $this->allow_list,
			'block_list'           => $this->block_list,
			'blocked_message'      => $this->blocked_message,
			'trusted_proxies'      => $this->trusted_proxies,
			'forwarded_header'     => $this->forwarded_header,
			'deletes_on_uninstall' => $this->deletes_on_uninstall,
		);
	}
}
