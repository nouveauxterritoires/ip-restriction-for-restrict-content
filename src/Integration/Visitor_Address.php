<?php
/**
 * Adresse du visiteur, résolue une fois pour la requête.
 *
 * La résolution était portée par le branchement d'accès au contenu jusqu'au
 * lot 8. Le lot 9 lui donne un second client — la couverture des archives de
 * terme — et deux copies de la même résolution auraient été deux copies des
 * mêmes garde-fous : l'en-tête n'est lu que derrière un proxy déclaré, la
 * chaîne est remontée de droite à gauche, et le point d'extension a le dernier
 * mot. Une seule instance par requête tient aussi la promesse du §7.1 : le
 * filtre `rcipr_client_address` est appliqué une fois, pas une fois par
 * branchement.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Integration;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Address\Client_Address;
use Ip_Restriction_For_Restrict_Content\Address\Client_Address_Resolver;
use Ip_Restriction_For_Restrict_Content\Http\Server_Environment;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Set;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use Ip_Restriction_For_Restrict_Content\Support\Extension_Points;

/**
 * Résolution mémorisée de l'adresse du visiteur.
 */
final class Visitor_Address {

	/**
	 * Dépôt des réglages.
	 *
	 * @var Settings_Repository
	 */
	private Settings_Repository $repository;

	/**
	 * Adresse retenue, conclue une fois pour la requête.
	 *
	 * La mémorisation est portée par l'instance et s'arrête à la requête,
	 * comme celle de la sonde de capacités : un contenu restreint peut être
	 * évalué des dizaines de fois dans une page, et résoudre l'adresse autant
	 * de fois n'apprendrait rien de plus.
	 *
	 * @var Client_Address|null
	 */
	private ?Client_Address $address = null;

	/**
	 * Construit la résolution.
	 *
	 * @param Settings_Repository $repository Dépôt des réglages.
	 */
	public function __construct( Settings_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Adresse retenue pour la requête en cours.
	 *
	 * @return Client_Address
	 */
	public function current(): Client_Address {
		if ( null !== $this->address ) {
			return $this->address;
		}

		$settings    = $this->repository->load();
		$environment = Server_Environment::from_globals();

		/**
		 * Déclare des proxys de confiance par code.
		 *
		 * @param string $text Texte de la liste, une entrée par ligne.
		 */
		$proxies = (string) apply_filters( Extension_Points::TRUSTED_PROXIES, $settings->trusted_proxies() );

		$address = Client_Address_Resolver::resolve(
			$environment->connection_address(),
			Rule_Set::from_text( $proxies ),
			$environment->forwarded_header( $settings->forwarded_header() )
		);

		/**
		 * Dernier mot sur l'adresse retenue.
		 *
		 * Pour un hébergement dont l'en-tête n'est pas dans la liste fermée du
		 * §7.1. Un retour qui n'est pas une adresse est ignoré : la fermeture
		 * sur l'inconnu vaut aussi pour ce que renvoie un tiers.
		 *
		 * @param Client_Address $address Adresse retenue par le plugin.
		 */
		$filtered = apply_filters( Extension_Points::CLIENT_ADDRESS, $address );

		$this->address = $filtered instanceof Client_Address ? $filtered : $address;

		return $this->address;
	}
}
