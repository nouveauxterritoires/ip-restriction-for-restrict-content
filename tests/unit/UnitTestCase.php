<?php
/**
 * Socle des tests unitaires.
 *
 * La suite `unit` ne charge pas WordPress : elle le remplace par Brain Monkey.
 * Les fonctions de traduction et d'échappement sont bouchonnées à l'identité,
 * ce qui suffit au code testé — aucune décision du plugin ne dépend de la
 * traduction d'une chaîne.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Amorçage et démontage de Brain Monkey.
 */
abstract class UnitTestCase extends TestCase {

	/**
	 * Installe les bouchons de WordPress.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\stubs(
			array(
				'__'           => null,
				'_x'           => null,
				'esc_html__'   => null,
				'esc_attr__'   => null,
				'esc_html'     => null,
				'esc_attr'     => null,
				'esc_url'      => null,
				'sanitize_key' => static fn( string $key ): string => strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ),
				'absint'       => static fn( $value ): int => abs( (int) $value ),
			)
		);
	}

	/**
	 * Démonte les bouchons.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();

		parent::tearDown();
	}
}
