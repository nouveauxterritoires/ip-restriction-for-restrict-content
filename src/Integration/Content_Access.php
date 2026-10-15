<?php
/**
 * Branchement de la décision d'accès sur la dépendance — FNC-01, FNC-05, FNC-31.
 *
 * Le filtre `rcp_member_can_access` est appliqué par la dépendance sur trois
 * chemins selon qu'un client existe ou non (§5.1), et la suite `contract`
 * établit les trois par l'exécution. Ce branchement-ci n'a donc pas à choisir :
 * il est accroché au filtre, et il répond de la même façon d'où qu'il vienne.
 *
 * **Le plugin n'accorde que.** Si la dépendance accorde déjà l'accès, il n'y a
 * rien à ajouter ; si elle le refuse, le plugin accorde ou ne se prononce pas,
 * jamais ne retire (FNC-31). C'est ce qui garantit qu'un visiteur qui ne
 * correspond à aucune liste obtient exactement le comportement de la
 * dépendance seule.
 *
 * Ce qui est accordé est la lecture du contenu, et rien d'autre : aucune
 * capacité WordPress, aucune adhésion, aucune écriture (FNC-30, SEC-13). La
 * forme du branchement l'assure — le filtre ne renvoie qu'un booléen à la
 * question « cette personne peut-elle accéder à ce contenu ».
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Integration;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Address\Client_Address;
use Ip_Restriction_For_Restrict_Content\Address\Client_Address_Resolver;
use Ip_Restriction_For_Restrict_Content\Http\Server_Environment;
use Ip_Restriction_For_Restrict_Content\Policy\Access_Decision;
use Ip_Restriction_For_Restrict_Content\Policy\Access_Policy;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Set;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use Ip_Restriction_For_Restrict_Content\Support\Extension_Points;
use Ip_Restriction_For_Restrict_Content\Support\Redactor;

/**
 * Accroche de l'autorisation globale.
 */
final class Content_Access {

	/**
	 * Dépôt des réglages.
	 *
	 * @var Settings_Repository
	 */
	private Settings_Repository $repository;

	/**
	 * Marquage de non-cachabilité.
	 *
	 * @var Cache_Guard
	 */
	private Cache_Guard $cache;

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
	 * Construit le branchement.
	 *
	 * @param Settings_Repository $repository Dépôt des réglages.
	 * @param Cache_Guard         $cache      Marquage de non-cachabilité.
	 */
	public function __construct( Settings_Repository $repository, Cache_Guard $cache ) {
		$this->repository = $repository;
		$this->cache      = $cache;
	}

	/**
	 * Accroche la décision au filtre de la dépendance.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'rcp_member_can_access', array( $this, 'filter_access' ), 10, 3 );
	}

	/**
	 * Accorde l'accès si une liste le prévoit.
	 *
	 * @param mixed $can_access Décision de la dépendance.
	 * @param mixed $user_id    Identifiant de l'utilisateur, non utilisé (FNC-05).
	 * @param mixed $post_id    Identifiant du contenu demandé.
	 * @return bool
	 */
	public function filter_access( $can_access, $user_id = 0, $post_id = 0 ): bool {
		unset( $user_id );

		if ( true === $can_access ) {
			return true;
		}

		$decision = $this->decide();

		if ( $decision->is_not_handled() ) {
			return (bool) $can_access;
		}

		$this->cache->mark();

		/**
		 * Signale qu'un accès vient d'être accordé.
		 *
		 * L'adresse est expurgée : ce que l'exploitant journalise ensuite
		 * relève de sa responsabilité, ce que le plugin lui remet relève de la
		 * nôtre (SEC-06).
		 *
		 * @param string $address Adresse expurgée du visiteur.
		 * @param string $scope   Portée ayant accordé l'accès.
		 * @param int    $post_id Contenu demandé.
		 */
		do_action(
			Extension_Points::ACCESS_GRANTED,
			Redactor::redact_address( $this->resolved_address() ),
			$decision->scope()->value,
			(int) $post_id
		);

		return true;
	}

	/**
	 * Décide de l'accès pour la requête en cours.
	 *
	 * @return Access_Decision
	 */
	private function decide(): Access_Decision {
		$settings = $this->repository->load();

		/**
		 * Complète la liste d'autorisation globale depuis une source externe.
		 *
		 * @param string $text  Texte de la liste, une entrée par ligne.
		 * @param string $scope Portée concernée.
		 */
		$text = (string) apply_filters( Extension_Points::RULES, $settings->allow_list(), 'global' );

		$decision = Access_Policy::decide(
			$this->resolved_address()->number(),
			Rule_Set::from_text( $text )
		);

		return $this->filtered( $decision );
	}

	/**
	 * Laisse un tiers retirer un octroi, jamais en ajouter.
	 *
	 * Un filtre capable d'accorder transformerait tout plugin tiers en
	 * fournisseur d'accès au contenu payant, et mettrait le contrôle hors de
	 * portée de l'administrateur qui a rempli les listes. Le retour est donc
	 * **intersecté** avec la décision d'origine : il peut la ramener à « non
	 * traité », pas la faire passer à « accordé ».
	 *
	 * @param Access_Decision $decision Décision d'origine.
	 * @return Access_Decision
	 */
	private function filtered( Access_Decision $decision ): Access_Decision {
		if ( $decision->is_not_handled() ) {
			return $decision;
		}

		/**
		 * Dernier mot sur la décision d'accès.
		 *
		 * Ne peut que retirer un octroi : un retour qui n'accorde pas ramène
		 * la décision à « non traité », et un retour qui accorde ne peut pas
		 * en créer un.
		 *
		 * @param Access_Decision $decision Décision du plugin.
		 * @param string          $address  Adresse expurgée du visiteur.
		 */
		$filtered = apply_filters(
			Extension_Points::ACCESS_DECISION,
			$decision,
			Redactor::redact_address( $this->resolved_address() )
		);

		if ( ! $filtered instanceof Access_Decision || ! $filtered->is_granted() ) {
			return Access_Decision::not_handled();
		}

		return $decision;
	}

	/**
	 * Résout l'adresse du visiteur, une fois pour la requête.
	 *
	 * @return Client_Address
	 */
	private function resolved_address(): Client_Address {
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
