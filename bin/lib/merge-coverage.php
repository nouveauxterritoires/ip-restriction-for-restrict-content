<?php
/**
 * Fusionne les rapports de couverture des suites.
 *
 * Les suites ne peuvent pas partager un processus (voir `tests/bootstrap.php`) :
 * chacune est mesurée séparément, puis les mesures sont fusionnées ici.
 *
 * `phpcov merge` était l'outil prévu par le cahier des charges §11.6. Il sort
 * avec le code 0 sans écrire aucun rapport sur cette combinaison de versions —
 * constat consigné en JE-012. La fusion passe donc directement par les classes
 * de `php-code-coverage`, que phpcov ne fait qu'envelopper.
 *
 * Usage : php bin/lib/merge-coverage.php <parts> <clover.xml> <html>
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Report\Html\Facade;

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

/*
 * `processUncoveredFiles` demande à php-code-coverage d'**inclure** les
 * fichiers jamais exécutés, afin d'en compter les lignes exécutables — sans
 * quoi un fichier non testé n'apparaîtrait pas du tout, et le pourcentage
 * mesurerait la couverture de ce qui est couvert.
 *
 * Or chaque fichier du plugin commence par la garde d'accès direct de SEC-15.
 * Hors WordPress, `ABSPATH` n'existe pas : l'inclusion appelle `exit`, et le
 * processus meurt en silence avec le code de sortie 0. C'est ce qui faisait
 * échouer `phpcov merge` sans le moindre message. Constat en JE-012.
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

$rcipr_parts  = $argv[1] ?? 'tests/coverage/parts';
$rcipr_clover = $argv[2] ?? 'tests/coverage/clover.xml';
$rcipr_html   = $argv[3] ?? 'tests/coverage/html';

$rcipr_files = glob( rtrim( $rcipr_parts, '/' ) . '/*.cov' );

if ( ! $rcipr_files ) {
	fwrite( STDERR, "Aucune mesure à fusionner dans {$rcipr_parts}.\n" );
	exit( 1 );
}

$rcipr_merged = null;

foreach ( $rcipr_files as $rcipr_file ) {
	$rcipr_measure = include $rcipr_file;

	if ( ! $rcipr_measure instanceof CodeCoverage ) {
		fwrite( STDERR, 'Mesure illisible : ' . $rcipr_file . "\n" );
		exit( 1 );
	}

	if ( null === $rcipr_merged ) {
		$rcipr_merged = $rcipr_measure;

		continue;
	}

	$rcipr_merged->merge( $rcipr_measure );
}

( new Clover() )->process( $rcipr_merged, $rcipr_clover );
( new Facade() )->process( $rcipr_merged, $rcipr_html );

if ( ! file_exists( $rcipr_clover ) ) {
	fwrite( STDERR, "La fusion n'a produit aucun rapport : {$rcipr_clover} est absent.\n" );
	exit( 1 );
}

printf( "%d mesure(s) fusionnée(s) → %s\n", count( $rcipr_files ), $rcipr_clover );
