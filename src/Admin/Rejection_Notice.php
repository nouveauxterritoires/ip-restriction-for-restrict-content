<?php
/**
 * Avis persistant sur les entrées refusées — FNC-22, A-03, SEC-11.
 *
 * L'avis est **recalculé** à chaque rendu depuis les réglages stockés, et non
 * mémorisé dans un transitoire à l'enregistrement. Deux conséquences, et les
 * deux sont voulues : il est persistant par construction — il reste tant que la
 * ligne fautive est dans le champ, et disparaît dès qu'elle est corrigée —, et
 * il reste exact même si la ligne a été écrite par un autre chemin que l'écran
 * de réglages.
 *
 * A-03 explique pourquoi la liste de **blocage** y figure autant que la liste
 * d'autorisation : son échec est silencieux. Une entrée de blocage refusée ne
 * bloque personne, et personne ne s'en aperçoit — alors qu'une entrée
 * d'autorisation refusée se manifeste par un accès refusé, donc par une
 * réclamation.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Admin;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Rule\Rule_Rejection;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Validator;

/**
 * Rendu de l'avis d'entrées refusées.
 */
final class Rejection_Notice {

	/**
	 * Dépôt des réglages.
	 *
	 * @var Settings_Repository
	 */
	private Settings_Repository $repository;

	/**
	 * Construit l'avis.
	 *
	 * @param Settings_Repository $repository Dépôt des réglages.
	 */
	public function __construct( Settings_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Accroche l'avis à l'administration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Affiche l'avis s'il y a lieu.
	 *
	 * Rien n'est affiché à qui ne peut pas corriger les réglages : l'avis
	 * apprendrait l'état de la configuration à quelqu'un qui n'y peut rien.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Settings_Page::CAPABILITY ) ) {
			return;
		}

		$settings  = $this->repository->load();
		$validated = Settings_Validator::validate( $settings->to_array() );

		$items = ''
			. $this->items( __( 'Liste d\'autorisation globale', 'ip-restriction-for-restrict-content' ), $validated->allow_list_rejections() )
			. $this->items( __( 'Liste de blocage d\'inscription', 'ip-restriction-for-restrict-content' ), $validated->block_list_rejections() )
			. $this->items( __( 'Proxys de confiance', 'ip-restriction-for-restrict-content' ), $validated->trusted_proxies_rejections() )
			. $this->level_items();

		if ( '' === $items ) {
			return;
		}

		wp_admin_notice(
			'<p><strong>'
			. esc_html__( 'Restriction par adresse IP : des entrées sont ignorées.', 'ip-restriction-for-restrict-content' )
			. '</strong></p><ul class="ul-disc">' . $items . '</ul><p>'
			. esc_html__( 'Une entrée ignorée ne correspond à aucune adresse : elle n\'accorde aucun accès et ne bloque aucune inscription.', 'ip-restriction-for-restrict-content' )
			. '</p>',
			array(
				'type'               => 'warning',
				'additional_classes' => array( 'rcipr_rejections' ),
				'paragraph_wrap'     => false,
			)
		);
	}

	/**
	 * Compose les éléments refusés des listes de niveau.
	 *
	 * Un avis qui ne couvrirait que les listes globales laisserait une ligne
	 * inerte sans aucun signe sur un écran de niveau — et une ligne inerte dans
	 * une liste d'autorisation de niveau est exactement aussi silencieuse que
	 * dans la liste globale.
	 *
	 * @return string Balisage des éléments.
	 */
	private function level_items(): string {
		$items = '';

		foreach ( $this->repository->level_lists() as $level_id => $text ) {
			$rejections = Settings_Validator::validate( array( 'allow_list' => $text ) )->allow_list_rejections();

			if ( array() === $rejections ) {
				continue;
			}

			$level = rcp_get_membership_level( $level_id );
			$name  = $level ? $level->get_name() : (string) $level_id;

			$items .= $this->items(
				sprintf(
					/* translators: nom du niveau d'adhésion. */
					__( 'Niveau « %s »', 'ip-restriction-for-restrict-content' ),
					$name
				),
				$rejections
			);
		}

		return $items;
	}

	/**
	 * Compose les éléments d'une liste refusée.
	 *
	 * Chaque valeur est échappée une à une : une ligne refusée vient d'une
	 * saisie, et une saisie est une donnée hostile jusqu'à preuve du
	 * contraire (SEC-03).
	 *
	 * @param string           $field      Nom du champ, traduit.
	 * @param Rule_Rejection[] $rejections Entrées refusées.
	 * @return string Balisage des éléments.
	 */
	private function items( string $field, array $rejections ): string {
		$items = '';

		foreach ( $rejections as $rejection ) {
			$items .= '<li>' . esc_html(
				sprintf(
					/* translators: 1: nom du champ, 2: numéro de ligne, 3: entrée refusée, 4: motif du refus. */
					__( '%1$s, ligne %2$d — « %3$s » ignorée : %4$s', 'ip-restriction-for-restrict-content' ),
					$field,
					$rejection->number(),
					$rejection->line(),
					Rejection_Labels::reason( $rejection->reason() )
				)
			) . '</li>';
		}

		return $items;
	}
}
