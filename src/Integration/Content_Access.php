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

use Ip_Restriction_For_Restrict_Content\Policy\Access_Decision;
use Ip_Restriction_For_Restrict_Content\Policy\Access_Policy;
use Ip_Restriction_For_Restrict_Content\Policy\Scope;
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
	 * Adresse du visiteur, résolue une fois pour la requête.
	 *
	 * @var Visitor_Address
	 */
	private Visitor_Address $visitor;

	/**
	 * Construit le branchement.
	 *
	 * @param Settings_Repository  $repository Dépôt des réglages.
	 * @param Cache_Guard          $cache      Marquage de non-cachabilité.
	 * @param Visitor_Address|null $visitor    Résolution partagée de l'adresse ; la sienne à défaut.
	 */
	public function __construct( Settings_Repository $repository, Cache_Guard $cache, ?Visitor_Address $visitor = null ) {
		$this->repository = $repository;
		$this->cache      = $cache;
		$this->visitor    = $visitor ?? new Visitor_Address( $repository );
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

		$decision = $this->decide( (int) $post_id );

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
		 * @param string   $address  Adresse expurgée du visiteur.
		 * @param string   $scope    Portée ayant accordé l'accès.
		 * @param int      $post_id  Contenu demandé.
		 * @param int|null $scope_id Identifiant du niveau, null pour la portée globale.
		 */
		do_action(
			Extension_Points::ACCESS_GRANTED,
			Redactor::redact_address( $this->visitor->current() ),
			$decision->scope()->value,
			(int) $post_id,
			$decision->scope_id()
		);

		return true;
	}

	/**
	 * Décide de l'accès pour la requête en cours.
	 *
	 * @param int $post_id Contenu demandé.
	 * @return Access_Decision
	 */
	private function decide( int $post_id ): Access_Decision {
		$settings = $this->repository->load();

		/**
		 * Complète une liste d'autorisation depuis une source externe.
		 *
		 * @param string   $text     Texte de la liste, une entrée par ligne.
		 * @param string   $scope    Portée concernée.
		 * @param int|null $scope_id Identifiant du niveau ou du contenu, null pour la portée globale.
		 */
		$text = (string) apply_filters(
			Extension_Points::RULES,
			$settings->allow_list(),
			Scope::GLOBAL_LIST->value,
			null
		);

		$decision = Access_Policy::decide(
			$this->visitor->current()->number(),
			Rule_Set::from_text( $text ),
			$this->applicable_level_lists( $post_id ),
			$this->post_list( $post_id )
		);

		return $this->filtered( $decision );
	}

	/**
	 * Liste du contenu demandé, et de lui seul.
	 *
	 * FNC-04 est obtenu ici par la même économie qu'aux niveaux : la politique
	 * ne reçoit que la liste de ce contenu-ci. Il n'y a donc rien à comparer,
	 * et aucune façon pour une autorisation posée sur un article d'en ouvrir un
	 * autre.
	 *
	 * @param int $post_id Contenu demandé.
	 * @return Rule_Set
	 */
	private function post_list( int $post_id ): Rule_Set {
		if ( 0 === $post_id ) {
			return Rule_Set::from_text( '' );
		}

		/** This filter is documented in src/Integration/Content_Access.php */
		$text = (string) apply_filters(
			Extension_Points::RULES,
			$this->repository->load_post_list( $post_id ),
			Scope::POST->value,
			$post_id
		);

		return Rule_Set::from_text( $text );
	}

	/**
	 * Listes des niveaux qui restreignent le contenu demandé, et d'eux seuls.
	 *
	 * **C'est ici que FNC-04 est obtenu.** La politique ne reçoit que ces
	 * listes : une autorisation posée sur un autre niveau n'est pas même
	 * consultée. L'implémentation naïve — passer toutes les listes de niveau et
	 * laisser la politique trancher — violerait la non-transitivité
	 * silencieusement, et transformerait chaque autorisation de niveau en
	 * autorisation globale.
	 *
	 * @param int $post_id Contenu demandé.
	 * @return array<int, Rule_Set>
	 */
	private function applicable_level_lists( int $post_id ): array {
		if ( 0 === $post_id ) {
			return array();
		}

		$levels = $this->restricting_level_ids( $post_id );

		if ( array() === $levels ) {
			return array();
		}

		$lists = array();

		foreach ( $levels as $level_id ) {
			$text = $this->repository->load_level_list( (int) $level_id );

			/** This filter is documented in src/Integration/Content_Access.php */
			$text = (string) apply_filters(
				Extension_Points::RULES,
				$text,
				Scope::MEMBERSHIP_LEVEL->value,
				(int) $level_id
			);

			$lists[ (int) $level_id ] = Rule_Set::from_text( $text );
		}

		return $lists;
	}

	/**
	 * Identifiants des niveaux qui restreignent le contenu demandé.
	 *
	 * Un contenu peut être restreint **par son terme** plutôt que par
	 * lui-même : la dépendance ne consulte alors les restrictions de terme que
	 * si le contenu n'en porte aucune (`RCP_Membership::can_access()`), et nous
	 * suivons cette précédence plutôt que d'en inventer une autre. Les listes
	 * ainsi consultées restent celles de niveaux qui restreignent **ce**
	 * contenu : la non-transitivité de FNC-04 n'est pas entamée.
	 *
	 * @param int $post_id Contenu demandé.
	 * @return list<int>
	 */
	private function restricting_level_ids( int $post_id ): array {
		$restrictions = rcp_get_post_restrictions( $post_id );
		$levels       = $restrictions['membership_levels'] ?? array();

		if ( is_array( $levels ) && array() !== $levels ) {
			return array_map( 'intval', array_values( $levels ) );
		}

		if ( rcp_has_post_restrictions( $post_id ) || ! rcp_has_term_restrictions( $post_id ) ) {
			return array();
		}

		$levels = array();

		foreach ( (array) rcp_get_connected_term_ids( $post_id ) as $term_id ) {
			$term = rcp_get_term_restrictions( (int) $term_id );

			if ( ! is_array( $term ) || ! is_array( $term['subscriptions'] ?? null ) ) {
				continue;
			}

			$levels = array_merge( $levels, array_map( 'intval', array_values( $term['subscriptions'] ) ) );
		}

		return array_values( array_unique( $levels ) );
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
			Redactor::redact_address( $this->visitor->current() )
		);

		if ( ! $filtered instanceof Access_Decision || ! $filtered->is_granted() ) {
			return Access_Decision::not_handled();
		}

		return $decision;
	}
}
