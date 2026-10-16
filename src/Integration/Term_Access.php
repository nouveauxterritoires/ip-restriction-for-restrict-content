<?php
/**
 * Couverture des archives de terme — FNC-03 étendu, §5.1.1, V-06.
 *
 * **C'est le branchement le plus grossier du plugin, et il est optionnel pour
 * cette raison.** `rcp_user_can_access_term()` n'applique aucun filtre : il est
 * impossible d'accorder l'accès à une archive de terme restreinte en filtrant
 * une décision. La seule accroche disponible est `rcp_get_term_restrictions`,
 * qui filtre la **donnée** « ce terme est-il restreint » — donc pour tout code
 * qui la relit pendant la même requête, et pas seulement pour le visiteur.
 *
 * Les **contenus** d'une catégorie restreinte, eux, n'ont pas besoin de ce
 * branchement : leur décision passe bien par `rcp_member_can_access`, ce que le
 * lot 9 a établi par l'exécution (JE-027). Ce branchement-ci ne sert que la
 * page d'archive du terme lui-même.
 *
 * Quatre garde-fous, dont les trois du §5.1.1 :
 *
 * 1. Il n'est pas branché si l'administrateur ne l'a pas demandé — V-06 a
 *    constaté l'effet indirect, et le cahier des charges avait prévu que ce
 *    constat rende la fonctionnalité optionnelle et désactivée par défaut.
 * 2. Il n'est pas branché s'il n'existe aucune liste d'autorisation.
 * 3. L'adresse n'est résolue qu'une fois le filtre atteint, jamais à
 *    l'enregistrement.
 * 4. Il ne neutralise que les restrictions du terme **demandé par la requête**,
 *    et seulement si une portée qui concerne ce terme accorde l'accès. Les
 *    restrictions d'un autre terme, et celles du même terme sur une requête qui
 *    ne l'interroge pas — un écran d'administration, par exemple —, sortent
 *    intactes.
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
 * Neutralisation conditionnelle de la restriction du terme demandé.
 */
final class Term_Access {

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
	 * Accroche le filtre, si et seulement s'il a lieu d'être.
	 *
	 * Les deux premiers garde-fous sont ici : ne pas brancher du tout est plus
	 * sûr que brancher un filtre qui se retiendra.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->repository->load()->covers_taxonomy_terms() ) {
			return;
		}

		if ( ! $this->has_any_allow_list() ) {
			return;
		}

		add_filter( 'rcp_get_term_restrictions', array( $this, 'filter_restrictions' ), 10, 2 );
	}

	/**
	 * Indique qu'au moins une liste d'autorisation existe.
	 *
	 * @return bool
	 */
	private function has_any_allow_list(): bool {
		if ( '' !== $this->repository->load()->allow_list() ) {
			return true;
		}

		foreach ( $this->repository->level_lists() as $list ) {
			if ( '' !== $list ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Neutralise la restriction du terme demandé si une liste l'accorde.
	 *
	 * @param mixed $restrictions Restrictions du terme.
	 * @param mixed $term_id      Identifiant du terme interrogé.
	 * @return mixed Restrictions, vidées ou telles quelles.
	 */
	public function filter_restrictions( $restrictions, $term_id = 0 ) {
		if ( ! is_array( $restrictions ) || array() === $restrictions ) {
			return $restrictions;
		}

		$term_id = (int) $term_id;

		if ( 0 === $term_id || $term_id !== $this->queried_term_id() ) {
			return $restrictions;
		}

		$decision = $this->decide( $restrictions );

		if ( $decision->is_not_handled() ) {
			return $restrictions;
		}

		$this->cache->mark();

		/**
		 * Signale qu'une restriction de terme vient d'être neutralisée.
		 *
		 * @param string   $address  Adresse expurgée du visiteur.
		 * @param int      $term_id  Terme concerné.
		 * @param string   $scope    Portée ayant accordé l'accès.
		 * @param int|null $scope_id Identifiant du niveau, null pour la portée globale.
		 */
		do_action(
			Extension_Points::TERM_RESTRICTION_LIFTED,
			Redactor::redact_address( $this->visitor->current() ),
			$term_id,
			$decision->scope()->value,
			$decision->scope_id()
		);

		return array();
	}

	/**
	 * Terme demandé par la requête en cours, s'il y en a un.
	 *
	 * C'est le quatrième garde-fou. Une archive de terme est la seule requête
	 * où la décision de la dépendance est hors d'atteinte ; partout ailleurs —
	 * un contenu, un écran d'administration, une requête d'API — il n'y a rien
	 * à neutraliser, et la donnée doit sortir intacte.
	 *
	 * @return int Zéro si la requête n'interroge aucun terme.
	 */
	private function queried_term_id(): int {
		if ( is_admin() ) {
			return 0;
		}

		if ( ! is_category() && ! is_tag() && ! is_tax() ) {
			return 0;
		}

		return (int) get_queried_object_id();
	}

	/**
	 * Décide de l'accès au terme demandé.
	 *
	 * Les portées consultées sont la liste globale et les listes des niveaux
	 * que **ce terme** exige : une autorisation posée sur un autre niveau n'est
	 * pas même consultée (FNC-04). Aucune liste de contenu n'entre ici — un
	 * terme n'est pas un contenu, et la liste d'un article n'a pas à ouvrir
	 * l'archive de sa catégorie.
	 *
	 * @param array<string, mixed> $restrictions Restrictions du terme.
	 * @return Access_Decision
	 */
	private function decide( array $restrictions ): Access_Decision {
		/** This filter is documented in src/Integration/Content_Access.php */
		$global = (string) apply_filters(
			Extension_Points::RULES,
			$this->repository->load()->allow_list(),
			Scope::GLOBAL_LIST->value,
			null
		);

		return Access_Policy::decide(
			$this->visitor->current()->number(),
			Rule_Set::from_text( $global ),
			$this->level_lists( $restrictions ),
			Rule_Set::from_text( '' )
		);
	}

	/**
	 * Listes des niveaux exigés par le terme interrogé, et d'eux seuls.
	 *
	 * @param array<string, mixed> $restrictions Restrictions du terme.
	 * @return array<int, Rule_Set>
	 */
	private function level_lists( array $restrictions ): array {
		$levels = $restrictions['subscriptions'] ?? array();

		if ( ! is_array( $levels ) ) {
			return array();
		}

		$lists = array();

		foreach ( $levels as $level_id ) {
			/** This filter is documented in src/Integration/Content_Access.php */
			$text = (string) apply_filters(
				Extension_Points::RULES,
				$this->repository->load_level_list( (int) $level_id ),
				Scope::MEMBERSHIP_LEVEL->value,
				(int) $level_id
			);

			$lists[ (int) $level_id ] = Rule_Set::from_text( $text );
		}

		return $lists;
	}
}
