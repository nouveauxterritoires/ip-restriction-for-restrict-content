<?php
/**
 * Tests d'intégrité du catalogue de capacités.
 *
 * Le catalogue est une donnée, et une donnée fautive est silencieuse : un
 * symbole mal orthographié serait sondé, rapporté absent, et désactiverait une
 * fonctionnalité intacte. Un contexte mal déclaré produirait l'inverse — une
 * capacité jamais vérifiée. Ces tests ne valident pas l'amont : ils valident la
 * forme de ce que nous déclarons. L'amont, c'est la suite `contract`.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Unit\Domain;

use Ip_Restriction_For_Restrict_Content\Domain\Capability;
use Ip_Restriction_For_Restrict_Content\Domain\Capability_Catalogue;
use Ip_Restriction_For_Restrict_Content\Tests\Unit\UnitTestCase;

/**
 * Forme du catalogue déclaré.
 */
final class CapabilityCatalogueTest extends UnitTestCase {

	/**
	 * Le catalogue couvre les sept fonctionnalités du cahier des charges.
	 *
	 * @return void
	 */
	public function test_every_declared_feature_is_covered(): void {
		$this->assertSame(
			array(
				Capability_Catalogue::FEATURE_CORE,
				Capability_Catalogue::FEATURE_CONTENT_ACCESS,
				Capability_Catalogue::FEATURE_TERM_ACCESS,
				Capability_Catalogue::FEATURE_REGISTRATION_BLOCK,
				Capability_Catalogue::FEATURE_LEVEL_SCOPE,
				Capability_Catalogue::FEATURE_POST_SCOPE,
				Capability_Catalogue::FEATURE_SETTINGS,
			),
			Capability_Catalogue::features()
		);
	}

	/**
	 * Aucun symbole n'est déclaré deux fois.
	 *
	 * Un doublon n'est pas qu'une redondance : deux entrées de contextes
	 * différents pour le même symbole rendraient son état indéterminé.
	 *
	 * @return void
	 */
	public function test_no_symbol_is_declared_twice(): void {
		$symbols = array();

		foreach ( Capability_Catalogue::declared() as $capability ) {
			$symbols[] = $capability->symbol();
		}

		$this->assertSame( array_values( array_unique( $symbols ) ), $symbols );
	}

	/**
	 * Chaque entrée déclare une nature et un contexte connus, et une preuve
	 * amont de la forme « fichier:ligne ».
	 *
	 * @return void
	 */
	public function test_every_entry_is_well_formed(): void {
		foreach ( Capability_Catalogue::declared() as $capability ) {
			$symbol = $capability->symbol();

			$this->assertContains(
				$capability->kind(),
				array( Capability::FUNCTION_SYMBOL, Capability::CLASS_SYMBOL ),
				$symbol . ' : nature inconnue.'
			);
			$this->assertContains(
				$capability->context(),
				array( Capability::CONTEXT_ANY, Capability::CONTEXT_ADMIN ),
				$symbol . ' : contexte inconnu.'
			);
			$this->assertNotSame( '', $capability->feature(), $symbol . ' : aucune fonctionnalité rattachée.' );
			$this->assertMatchesRegularExpression(
				'#^core/includes/[a-z0-9/.\-]+\.php:[1-9][0-9]*$#',
				$capability->evidence(),
				$symbol . ' : la preuve amont doit nommer un fichier de core/ et une ligne.'
			);
		}
	}

	/**
	 * Le marqueur d'interface est lui-même une capacité du catalogue.
	 *
	 * La sonde du lot 0 et le catalogue du lot 1 doivent parler du même
	 * symbole : deux marqueurs distincts finiraient par diverger, et le plugin
	 * s'amorcerait sur une interface qu'il ne vérifie plus.
	 *
	 * @return void
	 */
	public function test_the_api_marker_belongs_to_the_catalogue(): void {
		$symbols = array();

		foreach ( Capability_Catalogue::declared() as $capability ) {
			$symbols[] = $capability->symbol();
		}

		$this->assertContains( Capability_Catalogue::API_MARKER, $symbols );
	}

	/**
	 * Les deux capacités d'administration sont celles de la portée par contenu,
	 * et elles seules.
	 *
	 * Ce test fige un constat sur l'amont : la dépendance ne charge
	 * `core/includes/admin/metabox.php` que si `is_admin()`
	 * (`core/includes/class-restrict-content.php:247`). Déclarer en contexte
	 * `admin` une capacité chargée partout la soustrairait à toute
	 * vérification sur les requêtes de façade.
	 *
	 * @return void
	 */
	public function test_only_the_metabox_capabilities_are_admin_only(): void {
		$admin_only = array();

		foreach ( Capability_Catalogue::declared() as $capability ) {
			if ( Capability::CONTEXT_ADMIN === $capability->context() ) {
				$admin_only[] = $capability->symbol();
			}
		}

		$this->assertSame( array( 'rcp_render_meta_box', 'rcp_save_meta_data' ), $admin_only );
	}
}
