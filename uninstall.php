<?php
/**
 * Désinstallation du plugin.
 *
 * À ce stade, le plugin ne persiste aucune donnée : il n'y a donc rien à
 * supprimer. Le fichier existe pour que le point d'entrée de désinstallation
 * soit en place avant que le premier réglage ne soit écrit — le jour où il
 * l'est, la suppression se décide ici, et nulle part ailleurs.
 *
 * Deux règles s'appliqueront alors, et la distinction est délibérée : la
 * révocation de tout accès conféré par le plugin sera inconditionnelle, tandis
 * que la suppression des réglages n'aura lieu que si l'administrateur l'a
 * explicitement demandée. Les comptes utilisateurs ne sont jamais supprimés,
 * dans aucun scénario.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
