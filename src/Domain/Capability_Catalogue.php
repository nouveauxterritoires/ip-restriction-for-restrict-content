<?php
/**
 * Catalogue des capacités attendues de la dépendance.
 *
 * Une ligne par symbole, et chaque ligne porte la preuve qui l'a établie : le
 * fichier et la ligne amont où le symbole applique l'accroche dont ce plugin
 * dépend. Ces positions sont relevées par lecture de la source installée, et
 * elles datent une preuve — elles ne constituent pas un contrat. Le contrat,
 * c'est la suite `contract`, qui provoque chaque chemin et constate l'appel
 * (cahier des charges §5.1, §11.2).
 *
 * Le catalogue est volontairement une donnée et non du code : l'ajouter d'une
 * ligne ne demande ni condition ni branche, et l'écran d'état du §7 comme
 * l'avis de E-04 s'en déduisent tous les deux.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Déclaration du catalogue.
 */
final class Capability_Catalogue {

	/**
	 * Fonctionnalité sans laquelle le plugin ne peut pas s'amorcer.
	 *
	 * @var string
	 */
	public const FEATURE_CORE = 'core';

	/**
	 * Contournement des restrictions de contenu (FNC-01 à FNC-06).
	 *
	 * @var string
	 */
	public const FEATURE_CONTENT_ACCESS = 'content_access';

	/**
	 * Couverture des restrictions de taxonomie (§5.1.1).
	 *
	 * @var string
	 */
	public const FEATURE_TERM_ACCESS = 'term_access';

	/**
	 * Blocage d'inscription (FNC-07 à FNC-12).
	 *
	 * @var string
	 */
	public const FEATURE_REGISTRATION_BLOCK = 'registration_block';

	/**
	 * Portée par niveau d'adhésion (FNC-02, FNC-25).
	 *
	 * @var string
	 */
	public const FEATURE_LEVEL_SCOPE = 'level_scope';

	/**
	 * Portée par contenu (FNC-03, FNC-26).
	 *
	 * @var string
	 */
	public const FEATURE_POST_SCOPE = 'post_scope';

	/**
	 * Écran de réglages, gardé par la capacité de la dépendance (FNC-24).
	 *
	 * @var string
	 */
	public const FEATURE_SETTINGS = 'settings';

	/**
	 * Capacités attendues, dans l'ordre d'exposition à l'écran d'état.
	 *
	 * Chaque entrée est un tuple positionnel : symbole, nature, fonctionnalité,
	 * contexte de chargement amont, accroche fournie, preuve.
	 *
	 * @var list<array{string, string, string, string, string, string}>
	 */
	private const DECLARED = array(
		array(
			'rcp_get_membership_levels',
			Capability::FUNCTION_SYMBOL,
			self::FEATURE_CORE,
			Capability::CONTEXT_ANY,
			'',
			'core/includes/membership-levels/membership-level-functions.php:100',
		),
		array(
			'rcp_user_can_access',
			Capability::FUNCTION_SYMBOL,
			self::FEATURE_CONTENT_ACCESS,
			Capability::CONTEXT_ANY,
			'rcp_member_can_access',
			'core/includes/member-functions.php:101',
		),
		array(
			'RCP_Customer',
			Capability::CLASS_SYMBOL,
			self::FEATURE_CONTENT_ACCESS,
			Capability::CONTEXT_ANY,
			'rcp_member_can_access',
			'core/includes/customers/class-rcp-customer.php:772',
		),
		array(
			'RCP_Member',
			Capability::CLASS_SYMBOL,
			self::FEATURE_CONTENT_ACCESS,
			Capability::CONTEXT_ANY,
			'rcp_member_can_access',
			'core/includes/class-rcp-member.php:970',
		),
		array(
			'rcp_get_post_restrictions',
			Capability::FUNCTION_SYMBOL,
			self::FEATURE_CONTENT_ACCESS,
			Capability::CONTEXT_ANY,
			'',
			'core/includes/misc-functions.php:831',
		),
		array(
			'rcp_get_term_restrictions',
			Capability::FUNCTION_SYMBOL,
			self::FEATURE_TERM_ACCESS,
			Capability::CONTEXT_ANY,
			'rcp_get_term_restrictions',
			'core/includes/misc-functions.php:1002',
		),
		array(
			'rcp_process_registration',
			Capability::FUNCTION_SYMBOL,
			self::FEATURE_REGISTRATION_BLOCK,
			Capability::CONTEXT_ANY,
			'rcp_form_errors',
			'core/includes/registration-functions.php:164',
		),
		array(
			'rcp_errors',
			Capability::FUNCTION_SYMBOL,
			self::FEATURE_REGISTRATION_BLOCK,
			Capability::CONTEXT_ANY,
			'',
			'core/includes/error-tracking.php:19',
		),
		array(
			'rcp_add_membership_level_meta',
			Capability::FUNCTION_SYMBOL,
			self::FEATURE_LEVEL_SCOPE,
			Capability::CONTEXT_ANY,
			'',
			'core/includes/levels/meta.php:26',
		),
		array(
			'rcp_get_membership_level_meta',
			Capability::FUNCTION_SYMBOL,
			self::FEATURE_LEVEL_SCOPE,
			Capability::CONTEXT_ANY,
			'',
			'core/includes/levels/meta.php:69',
		),
		array(
			'rcp_update_membership_level_meta',
			Capability::FUNCTION_SYMBOL,
			self::FEATURE_LEVEL_SCOPE,
			Capability::CONTEXT_ANY,
			'',
			'core/includes/levels/meta.php:94',
		),
		array(
			'rcp_render_meta_box',
			Capability::FUNCTION_SYMBOL,
			self::FEATURE_POST_SCOPE,
			Capability::CONTEXT_ADMIN,
			'rcp_metabox_fields_after',
			'core/includes/admin/metabox.php:81',
		),
		array(
			'rcp_save_meta_data',
			Capability::FUNCTION_SYMBOL,
			self::FEATURE_POST_SCOPE,
			Capability::CONTEXT_ADMIN,
			'rcp_save_post_meta',
			'core/includes/admin/metabox.php:233',
		),
		array(
			'RCP_Capabilities',
			Capability::CLASS_SYMBOL,
			self::FEATURE_SETTINGS,
			Capability::CONTEXT_ANY,
			'rcp_manage_settings',
			'core/includes/class-rcp-capabilities.php:53',
		),
	);

	/**
	 * Symbole dont la présence atteste que la branche moderne est chargée.
	 *
	 * C'est la même capacité que celle du lot 0, et ce n'est pas un hasard :
	 * la sonde de branche et la sonde de dépendance sont la même chose
	 * (B-02, JE-003).
	 *
	 * @var string
	 */
	public const API_MARKER = 'rcp_get_membership_levels';

	/**
	 * Symbole dont la présence atteste que la variante gratuite est active,
	 * quelle que soit la branche qu'elle a ensuite chargée.
	 *
	 * `rc_process_legacy_switch()` est déclarée dans le fichier principal de la
	 * variante gratuite (`restrictcontent.php:676`), donc avant tout
	 * aiguillage : elle répond présente sur les deux branches. C'est ce qui
	 * permet de distinguer « dépendance absente » de « dépendance active sur sa
	 * branche historique » sans lire l'option amont (E-05, B-02).
	 *
	 * @var string
	 */
	public const BOOTSTRAP_MARKER = 'rc_process_legacy_switch';

	/**
	 * Capacités attendues.
	 *
	 * @return list<Capability>
	 */
	public static function declared(): array {
		$capabilities = array();

		foreach ( self::DECLARED as $entry ) {
			$capabilities[] = new Capability( $entry[0], $entry[1], $entry[2], $entry[3], $entry[4], $entry[5] );
		}

		return $capabilities;
	}

	/**
	 * Clés des fonctionnalités déclarées, sans doublon, dans l'ordre du catalogue.
	 *
	 * @return list<string>
	 */
	public static function features(): array {
		$features = array();

		foreach ( self::DECLARED as $entry ) {
			if ( ! in_array( $entry[2], $features, true ) ) {
				$features[] = $entry[2];
			}
		}

		return $features;
	}
}
