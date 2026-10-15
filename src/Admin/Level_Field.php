<?php
/**
 * Champ de liste d'autorisation sur les écrans de niveau d'adhésion — FNC-25.
 *
 * Le champ est ajouté par les deux accroches que la dépendance expose sur ses
 * écrans de niveau, et son enregistrement par les deux actions qu'elle
 * déclenche après avoir vérifié son propre nonce (§5.1). La suite `contract`
 * établit les quatre par l'exécution depuis le lot 1.
 *
 * Le libellé énonce la **non-transitivité** (FNC-04) : l'autorisation ne vaut
 * que pour les contenus restreints à ce niveau. C'est la chose que
 * l'administrateur ne devinera pas, et celle dont l'erreur est la plus coûteuse
 * — croire qu'une autorisation de niveau ouvre tout le site conduit à la poser
 * là où une autorisation globale était voulue, ou l'inverse.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Admin;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Validator;

/**
 * Rendu et enregistrement du champ de niveau.
 */
final class Level_Field {

	/**
	 * Nom du champ soumis.
	 *
	 * @var string
	 */
	public const FIELD = 'rcipr_level_allow_list';

	/**
	 * Capacité exigée pour écrire la liste d'un niveau.
	 *
	 * C'est celle que la dépendance exige de ses propres écrans de niveau.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'rcp_manage_levels';

	/**
	 * Nonces de la dépendance, selon l'écran d'où vient la soumission.
	 *
	 * @var array<string, string>
	 */
	private const NONCES = array(
		'rcp_edit_level_nonce' => 'rcp_edit_level_nonce',
		'rcp_add_level_nonce'  => 'rcp_add_level_nonce',
	);

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
	 * Accroche le champ aux écrans de niveau.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rcp_edit_subscription_form', array( $this, 'render_for_level' ) );
		add_action( 'rcp_add_subscription_form', array( $this, 'render_empty' ) );
		add_action( 'rcp_edit_subscription_level', array( $this, 'maybe_save' ) );
		add_action( 'rcp_add_subscription', array( $this, 'maybe_save' ) );
		add_action( 'rcp_remove_level', array( $this, 'forget_level' ) );
	}

	/**
	 * Oublie la liste d'un niveau supprimé.
	 *
	 * ADR-0004 tenait pour acquis que la suppression d'un niveau emporterait sa
	 * métadonnée, et que le stockage choisi dispensait donc de tout code de
	 * nettoyage. **C'est faux sur cette version de la dépendance** : la
	 * métadonnée survit à la suppression du niveau (JE-025). Le nettoyage est
	 * donc fait ici, sur l'action que la dépendance déclenche après la
	 * suppression.
	 *
	 * Ce n'est pas qu'une question de propreté : les identifiants de niveau
	 * sont attribués par une table auto-incrémentée, et un réglage orphelin
	 * accorderait un accès au prochain niveau à porter le même identifiant.
	 *
	 * @param mixed $level_id Identifiant du niveau supprimé.
	 * @return void
	 */
	public function forget_level( $level_id = 0 ): void {
		$level_id = (int) $level_id;

		if ( 0 === $level_id ) {
			return;
		}

		$this->repository->save_level_list( $level_id, '' );
	}

	/**
	 * Affiche le champ sur l'écran d'édition d'un niveau.
	 *
	 * @param mixed $level Niveau édité, tel que la dépendance le transmet.
	 * @return void
	 */
	public function render_for_level( $level = null ): void {
		$level_id = is_object( $level ) && method_exists( $level, 'get_id' ) ? (int) $level->get_id() : 0;

		$this->render( 0 === $level_id ? '' : $this->repository->load_level_list( $level_id ) );
	}

	/**
	 * Affiche le champ sur l'écran de création d'un niveau.
	 *
	 * @return void
	 */
	public function render_empty(): void {
		$this->render( '' );
	}

	/**
	 * Affiche la ligne de formulaire.
	 *
	 * @param string $value Liste à afficher.
	 * @return void
	 */
	private function render( string $value ): void {
		echo '<tr class="form-field"><th scope="row" valign="top"><label for="' . esc_attr( self::FIELD ) . '">'
			. esc_html__( 'Liste d\'autorisation par adresse IP', 'ip-restriction-for-restrict-content' )
			. '</label></th><td>';
		echo '<textarea id="' . esc_attr( self::FIELD ) . '" name="' . esc_attr( self::FIELD ) . '" rows="5" cols="50" class="large-text code">'
			. esc_textarea( $value ) . '</textarea>';
		echo '<p class="description">'
			. esc_html__( 'Une entrée par ligne. Ces adresses accèdent aux contenus restreints à ce niveau, et à eux seuls : une autorisation posée ici ne donne aucun accès aux contenus d\'un autre niveau.', 'ip-restriction-for-restrict-content' )
			. '</p></td></tr>';
	}

	/**
	 * Enregistre la liste du niveau, si la soumission est recevable.
	 *
	 * Trois conditions, et leur ordre est le même que pour l'écran de réglages
	 * (SEC-01) : authenticité de la soumission d'abord, capacité ensuite,
	 * présence du champ enfin.
	 *
	 * @param mixed $level_id Identifiant du niveau enregistré.
	 * @return void
	 */
	public function maybe_save( $level_id = 0 ): void {
		$level_id = (int) $level_id;

		if ( 0 === $level_id ) {
			return;
		}

		/*
		 * Le nonce est vérifié ici, dans la même méthode que la lecture : le
		 * lecteur — comme l'analyseur de style — doit voir au même endroit que
		 * rien n'est lu avant de l'avoir vérifié.
		 *
		 * Celui qui est vérifié est le nonce de la **dépendance** : c'est son
		 * écran qui a produit la soumission, et exiger un nonce à nous
		 * reviendrait à ajouter un champ à un formulaire qu'elle rend.
		 */
		$authentic = false;

		foreach ( self::NONCES as $field => $action ) {
			if ( isset( $_POST[ $field ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ) ) {
				$authentic = true;

				break;
			}
		}

		if ( ! $authentic ) {
			return;
		}

		/*
		 * La capacité est vérifiée en plus du nonce, parce que les actions
		 * d'enregistrement se déclenchent aussi sur un appel programmatique de
		 * l'interface de la dépendance, où aucun formulaire n'est en jeu.
		 */
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::FIELD ] ) ) {
			return;
		}

		$submitted = sanitize_textarea_field( wp_unslash( $_POST[ self::FIELD ] ) );

		/*
		 * La validation passe par le même analyseur que la lecture et que les
		 * listes globales : A-02 l'exige, et un second analyseur finirait par
		 * être le plus permissif.
		 */
		$validated = Settings_Validator::validate( array( 'allow_list' => $submitted ) );

		$this->repository->save_level_list( $level_id, $validated->settings()->allow_list() );
	}
}
