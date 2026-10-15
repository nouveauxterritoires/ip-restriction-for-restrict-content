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
