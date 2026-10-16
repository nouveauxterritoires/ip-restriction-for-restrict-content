<?php
/**
 * Désinstallation du plugin — §6.6.
 *
 * Deux règles, et leur asymétrie est délibérée.
 *
 * La révocation de tout accès conféré par le plugin est **inconditionnelle** :
 * elle ne demande aucun code, puisque le plugin absent, aucune liste n'accorde
 * plus rien. C'est une conséquence de l'architecture, pas une action de
 * désinstallation.
 *
 * La suppression des réglages, elle, n'a lieu que si l'administrateur l'a
 * explicitement demandée par un réglage. Supprimer par défaut ferait perdre une
 * configuration de plusieurs dizaines de lignes à qui désinstalle pour
 * déboguer, ou le temps d'une migration — et une liste d'adresses ne se
 * retrouve pas de mémoire.
 *
 * « Les réglages » comprend les trois endroits où ils vivent (§6.6) : l'option
 * du site, les métadonnées de niveau et celles de contenu. En oublier un
 * laisserait un réglage orphelin — et les identifiants de niveau viennent d'une
 * table auto-incrémentée, donc une liste oubliée finirait par s'appliquer à un
 * autre niveau que celui pour lequel elle avait été écrite.
 *
 * Les comptes utilisateurs ne sont jamais touchés, dans aucun scénario.
 *
 * Les réglages sont propres à chaque site d'un réseau (O-08, ADR-0009) : une
 * désinstallation réseau les supprime donc site par site, sans quoi elle en
 * laisserait autant que le réseau compte de sites.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * Le fichier principal du plugin n'est pas chargé pendant une désinstallation :
 * l'autoloader est donc enregistré ici. L'archive distribuable embarque
 * `vendor/autoload.php` ; sur une copie de travail sans dépendances installées,
 * le repli charge les deux classes nécessaires.
 */
$rcipr_autoload = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $rcipr_autoload ) ) {
	require_once $rcipr_autoload;
} else {
	require_once __DIR__ . '/src/Settings/Settings.php';
	require_once __DIR__ . '/src/Settings/Settings_Repository.php';
}

/**
 * Supprime les réglages du site courant, si l'administrateur l'a demandé.
 *
 * La décision de supprimer est lue dans les réglages eux-mêmes : c'est le seul
 * endroit où elle est exprimée, et la lire ailleurs reviendrait à la deviner.
 *
 * @return void
 */
function ip_restriction_for_restrict_content_uninstall_site(): void {
	$stored = get_option( \Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository::OPTION, array() );

	$settings = \Ip_Restriction_For_Restrict_Content\Settings\Settings::from_array(
		is_array( $stored ) ? $stored : array()
	);

	if ( ! $settings->deletes_on_uninstall() ) {
		return;
	}

	delete_option( \Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository::OPTION );
	delete_option( \Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository::VERSION_OPTION );

	delete_post_meta_by_key( \Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository::POST_META_KEY );

	ip_restriction_for_restrict_content_uninstall_level_lists();
}

/**
 * Supprime les listes rangées en métadonnée de niveau d'adhésion.
 *
 * Les métadonnées de niveau vivent dans une table de la dépendance, et il n'y a
 * pas d'équivalent de `delete_post_meta_by_key()` pour elles. Deux voies, dans
 * cet ordre : son interface si elle est chargée — c'est le cas courant, on
 * désinstalle ce plugin-ci alors que la dépendance reste active —, puis le nom
 * de table qu'elle déclare. Si ni l'une ni l'autre n'est disponible, la
 * fonction ne fait rien : deviner le nom d'une table pour y lancer une
 * suppression serait pire que de laisser des métadonnées derrière soi.
 *
 * @return void
 */
function ip_restriction_for_restrict_content_uninstall_level_lists(): void {
	$key = \Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository::LEVEL_META_KEY;

	if ( function_exists( 'rcp_get_membership_levels' ) && function_exists( 'rcp_delete_membership_level_meta' ) ) {
		foreach ( (array) rcp_get_membership_levels( array( 'number' => 0 ) ) as $level ) {
			$level_id = is_object( $level ) ? ( $level->get_id() ?? 0 ) : 0;

			if ( 0 === (int) $level_id ) {
				continue;
			}

			rcp_delete_membership_level_meta( (int) $level_id, $key );
		}

		return;
	}

	global $wpdb;

	$table = '';

	if ( function_exists( 'rcp_get_level_meta_db_name' ) ) {
		$table = (string) rcp_get_level_meta_db_name();
	} elseif ( isset( $wpdb->levelmeta ) ) {
		$table = (string) $wpdb->levelmeta;
	}

	/*
	 * Un nom de table ne se place pas en paramètre préparé. Il est donc
	 * vérifié : seul un identifiant simple est accepté, et tout le reste fait
	 * renoncer. La valeur vient de la dépendance et non d'une requête, mais
	 * c'est la vérification qui le garantit, pas la confiance.
	 */
	if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
		return;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Nom de table vérifié juste au-dessus ; la dépendance n'offre aucune API sur ce chemin, et il n'y a rien à mettre en cache dans une désinstallation.
	$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE meta_key = %s", $key ) );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $rcipr_site_id ) {
		switch_to_blog( (int) $rcipr_site_id );
		ip_restriction_for_restrict_content_uninstall_site();
		restore_current_blog();
	}
} else {
	ip_restriction_for_restrict_content_uninstall_site();
}
