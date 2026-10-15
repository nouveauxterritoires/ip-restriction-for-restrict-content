<?php
/**
 * Rejeu de l'attribution des capacités de la dépendance.
 *
 * La dépendance accorde ses capacités (`rcp_manage_settings`,
 * `rcp_manage_levels`, …) à l'administrateur **à l'activation** de son plugin,
 * par `rcp_options_install()` puis `RCP_Capabilities::add_caps()`. La
 * bibliothèque de tests de WordPress n'active aucun plugin : sans rejeu
 * explicite, l'administrateur d'un test ne détient aucune de ces capacités
 * (JE-002).
 *
 * Rejouer `add_caps()` ne suffit pourtant pas, et c'est le constat JE-017.
 * `WP_Roles::add_cap()` écrit la capacité dans son tableau `roles` et dans
 * l'option qui le mémorise — mais pas dans les objets `WP_Role` déjà
 * construits. Or c'est d'eux que `WP_User::get_role_caps()` dérive les
 * capacités effectives d'un utilisateur. L'attribution reste donc invisible
 * dans le processus courant tant que les rôles ne sont pas réinitialisés
 * depuis l'option.
 *
 * Ce que cela donnait, et qui a motivé cet auxiliaire : la suite `contract`
 * échouait au premier passage sur une base vierge — donc systématiquement en
 * intégration continue — et réussissait à tous les suivants, parce que
 * l'option portait alors les capacités écrites par le passage précédent et que
 * les objets `WP_Role` en héritaient dès leur construction. Un vert qui ne
 * dépendait que de l'historique de la base n'est pas un vert.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Support;

use RCP_Capabilities;

/**
 * Attribution des capacités de la dépendance, rendue effective en mémoire.
 */
final class Dependency_Capabilities {

	/**
	 * Rejoue l'attribution faite par la dépendance à son activation.
	 *
	 * À appeler avant de créer l'utilisateur qui doit les détenir : un
	 * `WP_User` déjà instancié a figé ses capacités.
	 *
	 * @return void
	 */
	public static function grant(): void {
		( new RCP_Capabilities() )->add_caps();

		// Reconstruit les objets WP_Role depuis l'option que add_caps() vient
		// d'écrire : sans cela, l'attribution n'est lisible nulle part.
		wp_roles()->for_site();
	}
}
