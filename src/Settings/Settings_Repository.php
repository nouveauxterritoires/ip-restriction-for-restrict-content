<?php
/**
 * Lecture et écriture des réglages globaux.
 *
 * Une seule option porte les cinq réglages (§6.2) : elle est lue à chaque
 * requête, et une option unique est chargée avec les options automatiques
 * plutôt que de coûter cinq lectures. Les deux listes y sont rangées sous des
 * clés distinctes, et aucun code commun paramétré par un nom de clé ne les
 * manipule (A-01).
 *
 * Les réglages sont **propres à chaque site** d'un réseau : `get_option()` et
 * `update_option()` le sont par construction. C'est la décision O-08, rendue au
 * lot 4 et motivée par ADR-0009.
 *
 * Les listes d'autorisation **par niveau d'adhésion** sont rangées en
 * métadonnée de niveau, par l'interface de la dépendance (§6.3, ADR-0004), et
 * celles **par contenu** en métadonnée de publication (§6.4). Le préfixe de
 * soulignement de cette dernière la retire de l'interface des champs
 * personnalisés : un réglage d'accès n'a pas à être modifiable par un champ
 * libre, où ni nonce ni capacité propre ne le garderaient.
 *
 * Aucune requête SQL directe : tout passe par les API d'options et de
 * métadonnées (SEC-04), et un test de la suite `unit` échoue si `$wpdb`
 * apparaît dans `src/`.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Dépôt des réglages globaux.
 */
final class Settings_Repository {

	/**
	 * Option qui porte les réglages.
	 *
	 * @var string
	 */
	public const OPTION = 'ip_restriction_for_restrict_content_settings';

	/**
	 * Option qui porte la version de la forme des réglages.
	 *
	 * Elle permettra une migration de forme sans deviner l'état antérieur
	 * (§6.2) : sans elle, une future migration devrait inférer la version
	 * depuis la présence des clés, ce qui est une heuristique.
	 *
	 * @var string
	 */
	public const VERSION_OPTION = 'ip_restriction_for_restrict_content_version';

	/**
	 * Clé de la métadonnée de niveau qui porte sa liste d'autorisation.
	 *
	 * Forme courte du préfixe, comme toute métadonnée (ADR-0001).
	 *
	 * @var string
	 */
	public const LEVEL_META_KEY = 'rcipr_ip_allow_list';

	/**
	 * Lit les réglages.
	 *
	 * @return Settings Les réglages stockés, ou les valeurs par défaut.
	 */
	public function load(): Settings {
		$stored = get_option( self::OPTION, array() );

		return Settings::from_array( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Écrit les réglages.
	 *
	 * @param Settings $settings Réglages à écrire.
	 * @return void
	 */
	public function save( Settings $settings ): void {
		update_option( self::OPTION, $settings->to_array() );
		update_option( self::VERSION_OPTION, RCIPR_VERSION );
	}

	/**
	 * Clé de la métadonnée de publication qui porte sa liste d'autorisation.
	 *
	 * Le préfixe de soulignement la retire de l'interface des champs
	 * personnalisés (§6.4) : un réglage d'accès n'a pas à être modifiable par
	 * un champ libre. Elle suit le contenu dans les exports, les révisions et
	 * la corbeille.
	 *
	 * @var string
	 */
	public const POST_META_KEY = '_rcipr_ip_allow_list';

	/**
	 * Lit la liste d'autorisation d'un niveau d'adhésion.
	 *
	 * @param int $level_id Identifiant du niveau.
	 * @return string Liste, une entrée par ligne ; chaîne vide s'il n'y en a pas.
	 */
	public function load_level_list( int $level_id ): string {
		$stored = rcp_get_membership_level_meta( $level_id, self::LEVEL_META_KEY, true );

		return is_string( $stored ) ? $stored : '';
	}

	/**
	 * Écrit la liste d'autorisation d'un niveau d'adhésion.
	 *
	 * Une liste vide **supprime** la métadonnée plutôt que d'écrire une chaîne
	 * vide : un réglage absent et un réglage vide doivent se lire pareil, et
	 * l'absence est la forme qui ne laisse rien derrière elle.
	 *
	 * @param int    $level_id Identifiant du niveau.
	 * @param string $text     Liste, une entrée par ligne.
	 * @return void
	 */
	public function save_level_list( int $level_id, string $text ): void {
		if ( '' === $text ) {
			rcp_delete_membership_level_meta( $level_id, self::LEVEL_META_KEY );

			return;
		}

		rcp_update_membership_level_meta( $level_id, self::LEVEL_META_KEY, $text );
	}

	/**
	 * Lit la liste d'autorisation d'un contenu.
	 *
	 * @param int $post_id Identifiant du contenu.
	 * @return string Liste, une entrée par ligne ; chaîne vide s'il n'y en a pas.
	 */
	public function load_post_list( int $post_id ): string {
		$stored = get_post_meta( $post_id, self::POST_META_KEY, true );

		return is_string( $stored ) ? $stored : '';
	}

	/**
	 * Écrit la liste d'autorisation d'un contenu.
	 *
	 * Une liste vide supprime la métadonnée, pour la même raison qu'au niveau :
	 * un réglage absent et un réglage vide doivent se lire pareil.
	 *
	 * @param int    $post_id Identifiant du contenu.
	 * @param string $text    Liste, une entrée par ligne.
	 * @return void
	 */
	public function save_post_list( int $post_id, string $text ): void {
		if ( '' === $text ) {
			delete_post_meta( $post_id, self::POST_META_KEY );

			return;
		}

		update_post_meta( $post_id, self::POST_META_KEY, $text );
	}

	/**
	 * Listes d'autorisation de tous les niveaux qui en portent une.
	 *
	 * Sert au signalement des entrées refusées (FNC-22) : un avis qui ne
	 * couvrirait que la liste globale laisserait une ligne inerte sans aucun
	 * signe sur un écran de niveau.
	 *
	 * @return array<int, string> Listes indexées par identifiant de niveau.
	 */
	public function level_lists(): array {
		$lists = array();

		foreach ( rcp_get_membership_levels( array( 'number' => 0 ) ) as $level ) {
			$text = $this->load_level_list( (int) $level->get_id() );

			if ( '' !== trim( $text ) ) {
				$lists[ (int) $level->get_id() ] = $text;
			}
		}

		return $lists;
	}
}
