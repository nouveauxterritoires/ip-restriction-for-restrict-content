<?php
/**
 * Contrôle de la couverture de **branches** sur les répertoires purs.
 *
 * Le cahier des charges §11.6 exige 100 % des lignes et des branches sur le
 * moteur. Une couverture de lignes n'y suffit pas : `if ( $a && $b )` tient sur
 * une ligne et porte plusieurs chemins, et une condition jamais prise dans un
 * sens se lit couverte alors qu'elle n'a jamais été éprouvée. Sur du code qui
 * décide d'un accès, c'est exactement la branche qu'on voudrait avoir
 * exercée.
 *
 * Le rapport Clover ne transporte pas cette information : la mesure est donc
 * relue depuis la mesure sérialisée de php-code-coverage. Celle d'**une seule
 * suite**, et non une fusion : les données de branches ne se fusionnent pas de
 * façon fiable d'un processus à l'autre — une même méthode se retrouve décrite
 * par deux modèles de branches dont les identifiants et les lignes ne
 * coïncident pas, et la moitié est alors rapportée non prise alors qu'elle est
 * exercée (JE-021).
 *
 * Ce n'est pas un assouplissement, c'est un resserrement : les répertoires
 * purs sont exercés par la suite qui leur est dédiée, et une branche qui ne
 * serait prise que par une autre suite est rapportée non prise.
 *
 * Usage : php bin/lib/check-branches.php <mesure d'une suite> <répertoire relatif>...
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

use SebastianBergmann\CodeCoverage\CodeCoverage;

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

/*
 * Même motif qu'en JE-012 : les mesures réinclusent les fichiers du plugin,
 * dont la garde `ABSPATH` arrêterait le processus en silence.
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

$rcipr_measure_file = $argv[1] ?? 'tests/coverage/parts/matching.cov';
$rcipr_directories  = array_slice( $argv, 2 );

if ( array() === $rcipr_directories ) {
	fwrite( STDERR, "Aucun répertoire à contrôler.\n" );
	exit( 1 );
}

if ( ! is_file( $rcipr_measure_file ) ) {
	fwrite( STDERR, "Mesure fusionnée introuvable : {$rcipr_measure_file}\n" );
	exit( 1 );
}

$rcipr_measure = include $rcipr_measure_file;

if ( ! $rcipr_measure instanceof CodeCoverage ) {
	fwrite( STDERR, 'Mesure illisible : ' . $rcipr_measure_file . "\n" );
	exit( 1 );
}

/**
 * Branches par répertoire surveillé : identifiant complet, et prise ou non.
 *
 * @var array<string, array<string, bool>>
 */
$rcipr_hits = array();

foreach ( $rcipr_measure->getData()->functionCoverage() as $rcipr_path => $rcipr_functions ) {
	foreach ( $rcipr_directories as $rcipr_directory ) {
		if ( false === strpos( $rcipr_path, '/' . trim( $rcipr_directory, '/' ) . '/' ) ) {
			continue;
		}

		foreach ( $rcipr_functions as $rcipr_name => $rcipr_data ) {
			/*
			 * La portée de fichier ne porte qu'une chose : la garde
			 * `defined( 'ABSPATH' ) || exit;` de SEC-15. Sa seconde branche
			 * arrête le processus, et aucun test ne peut la prendre sans se
			 * suicider. C'est la seule exclusion, elle est structurelle, et
			 * elle est nommée ici plutôt que semée en annotations dans le code.
			 */
			if ( '{main}' === $rcipr_name ) {
				continue;
			}

			foreach ( $rcipr_data['branches'] ?? array() as $rcipr_id => $rcipr_branch ) {
				$rcipr_key = $rcipr_path . '::' . $rcipr_name . '::' . $rcipr_id;

				$rcipr_hits[ $rcipr_directory ][ $rcipr_key ] = (int) ( $rcipr_branch['hit'] ?? 0 ) > 0;
			}
		}
	}
}

$rcipr_failed = false;

foreach ( $rcipr_directories as $rcipr_directory ) {
	$rcipr_branches = $rcipr_hits[ $rcipr_directory ] ?? array();
	$rcipr_total    = count( $rcipr_branches );
	$rcipr_covered  = count( array_filter( $rcipr_branches ) );

	if ( 0 === $rcipr_total ) {
		printf( "Branches   : %s — aucune branche mesurée\n", $rcipr_directory );

		continue;
	}

	if ( $rcipr_covered < $rcipr_total ) {
		fwrite(
			STDERR,
			sprintf(
				"Branches   : %s — %d/%d, le cahier des charges §11.6 en exige 100 %%.\n",
				$rcipr_directory,
				$rcipr_covered,
				$rcipr_total
			)
		);

		foreach ( $rcipr_branches as $rcipr_key => $rcipr_hit ) {
			if ( ! $rcipr_hit ) {
				fwrite( STDERR, '             non prise : ' . $rcipr_key . "\n" );
			}
		}

		$rcipr_failed = true;

		continue;
	}

	printf( "Branches   : %s — 100%% (%d/%d)\n", $rcipr_directory, $rcipr_covered, $rcipr_total );
}

exit( $rcipr_failed ? 1 : 0 );
