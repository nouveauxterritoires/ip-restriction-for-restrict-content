<?php
/**
 * Liste d'autorisation d'un contenu — FNC-03, FNC-26, SEC-02.
 *
 * Le champ est ajouté dans la boîte de restriction de la dépendance, par
 * l'accroche qu'elle expose après ses propres champs (§7.3) : la liste
 * d'autorisation se lit à côté de la restriction qu'elle contourne, et non sur
 * un autre écran.
 *
 * SEC-02 exige nonce **et** capacité à l'enregistrement, avec un test de
 * contournement par identifiant. Le second est le vrai sujet : la capacité doit
 * être vérifiée **sur le contenu enregistré**, et non en général. Un
 * contributeur qui peut modifier ses propres articles pourrait sans cela poser
 * une autorisation sur n'importe quel contenu du site en changeant un
 * identifiant dans la requête.
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
 * Rendu et enregistrement du champ de contenu.
 */
final class Post_Metabox {

	/**
	 * Nom du champ soumis.
	 *
	 * @var string
	 */
	public const FIELD = 'rcipr_post_allow_list';

	/**
	 * Champ qui porte le nonce de la boîte de la dépendance.
	 *
	 * Notre champ vit dans son formulaire : exiger un nonce à nous
	 * reviendrait à en ajouter un second au même envoi.
	 *
	 * @var string
	 */
	private const DEPENDENCY_NONCE_FIELD = 'rcp_meta_box';

	/**
	 * Action du nonce de la boîte de la dépendance.
	 *
	 * La dépendance le crée avec `basename( __FILE__ )`, soit le nom de son
	 * fichier de boîte de métadonnées (`core/includes/admin/metabox.php`).
	 *
	 * @var string
	 */
	private const DEPENDENCY_NONCE_ACTION = 'metabox.php';

	/**
	 * Dépôt des réglages.
	 *
	 * @var Settings_Repository
	 */
	private Settings_Repository $repository;

	/**
	 * Construit le champ.
	 *
	 * @param Settings_Repository $repository Dépôt des réglages.
	 */
	public function __construct( Settings_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Accroche le champ à la boîte de restriction.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rcp_metabox_fields_after', array( $this, 'render' ) );
		add_action( 'rcp_save_post_meta', array( $this, 'maybe_save' ) );
	}

	/**
	 * Affiche le champ et les entrées refusées de ce contenu.
	 *
	 * Le signalement est **ici** et non dans l'avis général : FNC-22 demande
	 * que l'entrée refusée soit signalée sur l'écran où elle a été saisie, et
	 * c'est aussi le seul endroit qui ne coûte rien — énumérer les contenus
	 * porteurs d'une liste demanderait une requête non bornée, là où les
	 * niveaux se comptent sur une main.
	 *
	 * @return void
	 */
	public function render(): void {
		$post_id = (int) get_the_ID();

		if ( 0 === $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$list = $this->repository->load_post_list( $post_id );

		echo '<p><label for="' . esc_attr( self::FIELD ) . '"><strong>'
			. esc_html__( 'Liste d\'autorisation par adresse IP', 'ip-restriction-for-restrict-content' )
			. '</strong></label><br />';
		echo '<textarea id="' . esc_attr( self::FIELD ) . '" name="' . esc_attr( self::FIELD ) . '" rows="4" cols="40" class="widefat code">'
			. esc_textarea( $list ) . '</textarea>';
		echo '<span class="description">'
			. esc_html__( 'Une entrée par ligne. Ces adresses accèdent à ce seul contenu, quelles que soient les restrictions ci-dessus.', 'ip-restriction-for-restrict-content' )
			. '</span></p>';

		$this->render_rejections( $list );
	}

	/**
	 * Affiche les entrées refusées de ce contenu (FNC-22).
	 *
	 * @param string $stored Liste stockée.
	 * @return void
	 */
	private function render_rejections( string $stored ): void {
		$rejections = Settings_Validator::validate( array( 'allow_list' => $stored ) )->allow_list_rejections();

		if ( array() === $rejections ) {
			return;
		}

		echo '<ul class="rcipr_rejections">';

		foreach ( $rejections as $rejection ) {
			echo '<li><strong>' . esc_html(
				sprintf(
					/* translators: 1: numéro de ligne, 2: entrée refusée, 3: motif du refus. */
					__( 'Ligne %1$d — « %2$s » ignorée : %3$s', 'ip-restriction-for-restrict-content' ),
					$rejection->number(),
					$rejection->line(),
					Rejection_Labels::reason( $rejection->reason() )
				)
			) . '</strong></li>';
		}

		echo '</ul>';
	}

	/**
	 * Enregistre la liste du contenu, si la soumission est recevable.
	 *
	 * Trois conditions dans cet ordre : nonce de la boîte de la dépendance,
	 * capacité **sur ce contenu**, puis présence du champ. La deuxième est le
	 * cœur de SEC-02 : vérifier `edit_post` en général laisserait un
	 * contributeur poser une autorisation sur un contenu qu'il ne peut pas
	 * modifier, en changeant un identifiant dans la requête.
	 *
	 * @param mixed $post_id Identifiant du contenu enregistré.
	 * @return void
	 */
	public function maybe_save( $post_id = 0 ): void {
		$post_id = (int) $post_id;

		if ( 0 === $post_id ) {
			return;
		}

		if ( ! isset( $_POST[ self::DEPENDENCY_NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::DEPENDENCY_NONCE_FIELD ] ) );

		if ( ! wp_verify_nonce( $nonce, self::DEPENDENCY_NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::FIELD ] ) ) {
			return;
		}

		$submitted = sanitize_textarea_field( wp_unslash( $_POST[ self::FIELD ] ) );
		$validated = Settings_Validator::validate( array( 'allow_list' => $submitted ) );

		$this->repository->save_post_list( $post_id, $validated->settings()->allow_list() );
	}
}
