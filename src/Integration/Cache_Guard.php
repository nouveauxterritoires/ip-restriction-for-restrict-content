<?php
/**
 * Marquage de non-cachabilité — SEC-14.
 *
 * Un site qui met ses pages en cache et qui sert à un visiteur autorisé la
 * version complète d'un contenu restreint expose cette version à **tous** les
 * visiteurs suivants servis par le cache. Le contournement se propage alors
 * sans qu'aucune liste ne le décide, et il survit à la correction de la liste
 * jusqu'à l'expiration du cache.
 *
 * L'exigence n'est pas de désactiver le cache : c'est de ne jamais laisser une
 * réponse **élargie par une autorisation** entrer dans un cache partagé. Le
 * marquage n'a donc lieu que lorsqu'une autorisation s'applique, et pas
 * autrement.
 *
 * Que les caches réellement déployés honorent ce marquage est le point ouvert
 * V-08 : il se constate en recette, pas en test unitaire.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Marque la requête en cours comme non cachable.
 */
final class Cache_Guard {

	/**
	 * Le marquage a eu lieu pour cette requête.
	 *
	 * @var bool
	 */
	private bool $marked = false;

	/**
	 * Marque la requête comme non cachable.
	 *
	 * Idempotent : plusieurs contenus élargis dans la même requête ne
	 * produisent qu'un marquage.
	 *
	 * @return void
	 */
	public function mark(): void {
		if ( $this->marked ) {
			return;
		}

		$this->marked = true;

		/*
		 * `DONOTCACHEPAGE` est la convention que respectent les caches de
		 * pages répandus. Les en-têtes de non-cachabilité visent les caches
		 * intermédiaires, qui ne connaissent pas cette constante : les deux
		 * sont nécessaires, et aucun des deux ne suffit.
		 */
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	/**
	 * Indique si la requête a été marquée.
	 *
	 * @return bool
	 */
	public function has_marked(): bool {
		return $this->marked;
	}
}
