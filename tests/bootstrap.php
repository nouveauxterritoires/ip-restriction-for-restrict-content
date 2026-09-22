<?php
/**
 * Amorçage PHPUnit.
 *
 * Deux modes selon la suite exécutée :
 *  - suite « unit » : WordPress est mocké par Brain Monkey, aucune base de données ;
 *  - autres suites  : la bibliothèque de tests de WordPress est chargée, avec
 *    Restrict Content ou Restrict Content Pro actif.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

$rcipr_autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! file_exists( $rcipr_autoloader ) ) {
	fwrite( STDERR, "Dépendances absentes. Exécutez : composer install\n" );
	exit( 1 );
}

require_once $rcipr_autoloader;

/**
 * Suites déclarées dans phpunit.xml.dist.
 *
 * @var list<string>
 */
const RCIPR_TEST_SUITES = array( 'unit', 'integration', 'contract', 'matching', 'access' );

/**
 * Lit la suite demandée en ligne de commande.
 *
 * @return string|null Nom de la suite, ou null si l'option est absente.
 */
function ip_restriction_for_restrict_content_tests_requested_suite(): ?string {
	$argv = $_SERVER['argv'] ?? array();

	foreach ( $argv as $index => $arg ) {
		if ( '--testsuite' === $arg ) {
			return (string) ( $argv[ $index + 1 ] ?? '' );
		}

		if ( 0 === strpos( (string) $arg, '--testsuite=' ) ) {
			return substr( (string) $arg, strlen( '--testsuite=' ) );
		}
	}

	return null;
}

$rcipr_suite = ip_restriction_for_restrict_content_tests_requested_suite();

/*
 * Les suites ne peuvent pas partager un même processus.
 *
 * La suite « unit » remplace les fonctions de WordPress via Brain Monkey
 * (Patchwork), ce qui exige qu'aucune d'elles ne soit déjà définie. Les autres
 * suites chargent au contraire un WordPress complet. Exécuter les deux dans la
 * même invocation fait échouer Patchwork avec « DefinedTooEarly ».
 *
 * Chaque suite doit donc être lancée séparément — ce que font `make test` et
 * l'intégration continue. La couverture se fusionne a posteriori (bin/coverage.sh).
 */
if ( null === $rcipr_suite || '' === $rcipr_suite ) {
	fwrite(
		STDERR,
		"Les suites de tests ne peuvent pas être exécutées dans une même invocation.\n"
		. "La suite « unit » remplace les fonctions de WordPress ; les autres les chargent.\n\n"
		. "Utilisez : make test\n"
		. 'Ou une suite à la fois : vendor/bin/phpunit --testsuite '
		. implode( '|', RCIPR_TEST_SUITES ) . "\n"
	);
	exit( 1 );
}

if ( ! in_array( $rcipr_suite, RCIPR_TEST_SUITES, true ) ) {
	fwrite(
		STDERR,
		sprintf(
			"Suite inconnue : %s\nSuites disponibles : %s\n",
			$rcipr_suite,
			implode( ', ', RCIPR_TEST_SUITES )
		)
	);
	exit( 1 );
}

if ( 'unit' === $rcipr_suite ) {
	/*
	 * Les fichiers du plugin refusent l'accès direct par
	 * `defined( 'ABSPATH' ) || exit;` (SEC-15). Sans cette constante, charger
	 * une classe dans la suite unit appelle `exit` : le processus s'arrête en
	 * silence avec le code 0, et la suite paraît réussie (JE-001).
	 *
	 * La valeur ne désigne rien d'exécutable : la suite unit ne charge jamais
	 * WordPress, elle le remplace par Brain Monkey.
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/tests/' );
	}

	return;
}

$rcipr_tests_dir = (string) getenv( 'WP_TESTS_DIR' );

if ( '' === $rcipr_tests_dir ) {
	$rcipr_tests_dir = '/wp-tests/lib';
}

if ( ! file_exists( $rcipr_tests_dir . '/includes/functions.php' ) ) {
	fwrite(
		STDERR,
		"Bibliothèque de tests WordPress introuvable dans {$rcipr_tests_dir}.\n"
		. "Exécutez : make prepare-tests\n"
	);
	exit( 1 );
}

require_once $rcipr_tests_dir . '/includes/functions.php';

/**
 * Localise le fichier principal de la dépendance d'adhésions.
 *
 * L'ordre reflète la priorité : si les deux variantes sont présentes, la
 * variante commerciale est testée.
 *
 * @return string|null Chemin absolu, ou null si aucune variante n'est présente.
 */
function ip_restriction_for_restrict_content_tests_locate_dependency(): ?string {
	$candidates = array(
		'restrict-content-pro/restrict-content-pro.php',
		'restrict-content/restrictcontent.php',
	);

	foreach ( $candidates as $candidate ) {
		$path = WP_PLUGIN_DIR . '/' . $candidate;

		if ( file_exists( $path ) ) {
			return $path;
		}
	}

	return null;
}

/*
 * La dépendance est chargée avant le plugin : le plugin sonde des capacités
 * qui n'existent que si elle est en mémoire.
 *
 * Le scénario « aucune dépendance active » n'est pas traité ici mais dans un
 * test dédié, qui réamorce sans elle.
 */
tests_add_filter(
	'muplugins_loaded',
	static function () {
		$dependency = ip_restriction_for_restrict_content_tests_locate_dependency();

		if ( null !== $dependency ) {
			require_once $dependency;
		}

		require_once dirname( __DIR__ ) . '/ip-restriction-for-restrict-content.php';
	}
);

require $rcipr_tests_dir . '/includes/bootstrap.php';

/*
 * La dépendance crée ses tables à l'activation du plugin et sur `admin_init`.
 * La bibliothèque de tests ne fait ni l'un ni l'autre : sans ce bloc, toute
 * création de niveau échoue avec « level_not_added », et la table de métas de
 * niveau reste absente.
 *
 * Les interfaces de table sont sollicitées directement plutôt que par
 * `do_action( 'admin_init' )`, qui entraînerait tout le reste de
 * l'initialisation de l'administration. Le DDL n'étant pas transactionnel, les
 * tables survivent au rollback opéré entre deux tests.
 *
 * Le registre des composants est parcouru plutôt qu'une liste écrite à la
 * main : une liste oubliée laisse une table absente, et l'échec se manifeste
 * alors loin de sa cause — « level_not_added » plutôt que « table manquante ».
 *
 * @see docs/journal-empirique.md JE-002
 */
if ( function_exists( 'rcp_setup_components' ) && function_exists( 'restrict_content_pro' ) ) {
	rcp_setup_components();

	$rcipr_components = restrict_content_pro()->components;

	foreach ( $rcipr_components as $rcipr_component ) {
		foreach ( array( 'table', 'meta' ) as $rcipr_interface ) {
			$rcipr_table = $rcipr_component->get_interface( $rcipr_interface );

			if ( $rcipr_table && method_exists( $rcipr_table, 'maybe_upgrade' ) ) {
				$rcipr_table->maybe_upgrade();
			}
		}
	}
}

if ( function_exists( 'rcp_options_install' ) ) {
	rcp_options_install();
}

if ( function_exists( 'rcp_create_tables' ) ) {
	rcp_create_tables();
}
