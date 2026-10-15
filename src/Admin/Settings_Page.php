<?php
/**
 * Écran de réglages — FNC-24, FNC-27, FNC-29, SEC-01, SEC-03.
 *
 * L'écran est rangé sous le menu de la dépendance et gardé par **sa** capacité,
 * `rcp_manage_settings` : réutiliser la capacité amont plutôt que d'en inventer
 * une évite qu'un site ait deux notions distinctes de « qui administre les
 * adhésions » (FNC-24).
 *
 * L'ordre des contrôles à l'enregistrement est l'exigence SEC-01, et il est
 * dans cet ordre-là : **nonce d'abord, capacité ensuite**. Une requête sans
 * droit et sans nonce doit être refusée pour le nonce, parce que c'est le
 * contrôle qui vaut pour tout le monde ; vérifier la capacité d'abord
 * apprendrait à un attaquant qu'il n'a pas le droit, là où il doit seulement
 * apprendre que sa requête n'est pas valide.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Admin;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Address\Address_Source;
use Ip_Restriction_For_Restrict_Content\Address\Client_Address;
use Ip_Restriction_For_Restrict_Content\Address\Client_Address_Resolver;
use Ip_Restriction_For_Restrict_Content\Http\Server_Environment;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Set;
use Ip_Restriction_For_Restrict_Content\Settings\Settings;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Validator;

/**
 * Rendu et enregistrement de l'écran de réglages.
 */
final class Settings_Page {

	/**
	 * Identifiant de l'écran.
	 *
	 * @var string
	 */
	public const SLUG = 'rcipr-settings';

	/**
	 * Capacité exigée, celle de la dépendance (FNC-24).
	 *
	 * @var string
	 */
	public const CAPABILITY = 'rcp_manage_settings';

	/**
	 * Menu parent : celui de la dépendance.
	 *
	 * @var string
	 */
	public const PARENT_SLUG = 'rcp-members';

	/**
	 * Action du nonce d'enregistrement.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'rcipr_save_settings';

	/**
	 * Champ qui porte le nonce.
	 *
	 * @var string
	 */
	public const NONCE_FIELD = 'rcipr_settings_nonce';

	/**
	 * Refus faute de nonce valide.
	 *
	 * @var string
	 */
	public const REFUSED_NONCE = 'nonce';

	/**
	 * Refus faute de capacité.
	 *
	 * @var string
	 */
	public const REFUSED_CAPABILITY = 'capability';

	/**
	 * Dépôt des réglages.
	 *
	 * @var Settings_Repository
	 */
	private Settings_Repository $repository;

	/**
	 * Motif du dernier refus d'enregistrement, chaîne vide s'il n'y en a pas.
	 *
	 * @var string
	 */
	private string $refusal = '';

	/**
	 * Indique si le dernier enregistrement a eu lieu.
	 *
	 * @var bool
	 */
	private bool $saved = false;

	/**
	 * Construit l'écran.
	 *
	 * @param Settings_Repository $repository Dépôt des réglages.
	 */
	public function __construct( Settings_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Accroche l'écran à l'administration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
	}

	/**
	 * Déclare l'entrée de menu sous celui de la dépendance.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Restriction par adresse IP', 'ip-restriction-for-restrict-content' ),
			__( 'Restriction par adresse IP', 'ip-restriction-for-restrict-content' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enregistre les réglages soumis, si la soumission est recevable.
	 *
	 * @return void
	 */
	public function maybe_save(): void {
		$this->refusal = '';
		$this->saved   = false;

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		/*
		 * Nonce d'abord, capacité ensuite : c'est l'ordre qu'exige SEC-01, et
		 * un test l'établit en soumettant une requête à la fois sans droit et
		 * sans nonce — le motif du refus doit être le nonce.
		 */
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$this->refusal = self::REFUSED_NONCE;

			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			$this->refusal = self::REFUSED_CAPABILITY;

			return;
		}

		/*
		 * La lecture de la soumission est volontairement ici, après les deux
		 * contrôles, et non dans une méthode à part : le lecteur — comme
		 * l'analyseur de style — doit voir au même endroit que rien n'est lu
		 * avant d'avoir vérifié le nonce. L'assainissement est fait à la
		 * lecture (SEC-03) ; la normalisation grammaticale est faite par la
		 * couche de décision, qui ne dépend pas de WordPress.
		 */
		$submitted = array(
			'allow_list'           => isset( $_POST['rcipr_allow_list'] )
				? sanitize_textarea_field( wp_unslash( $_POST['rcipr_allow_list'] ) )
				: '',
			'block_list'           => isset( $_POST['rcipr_block_list'] )
				? sanitize_textarea_field( wp_unslash( $_POST['rcipr_block_list'] ) )
				: '',
			'trusted_proxies'      => isset( $_POST['rcipr_trusted_proxies'] )
				? sanitize_textarea_field( wp_unslash( $_POST['rcipr_trusted_proxies'] ) )
				: '',
			'blocked_message'      => isset( $_POST['rcipr_blocked_message'] )
				? sanitize_text_field( wp_unslash( $_POST['rcipr_blocked_message'] ) )
				: '',
			'forwarded_header'     => isset( $_POST['rcipr_forwarded_header'] )
				? sanitize_text_field( wp_unslash( $_POST['rcipr_forwarded_header'] ) )
				: '',
			'deletes_on_uninstall' => isset( $_POST['rcipr_deletes_on_uninstall'] ),
		);

		$this->repository->save( Settings_Validator::validate( $submitted )->settings() );

		$this->saved = true;
	}

	/**
	 * Motif du dernier refus d'enregistrement.
	 *
	 * @return string Chaîne vide s'il n'y en a pas eu.
	 */
	public function refusal(): string {
		return $this->refusal;
	}

	/**
	 * Indique si le dernier enregistrement a eu lieu.
	 *
	 * @return bool
	 */
	public function has_saved(): bool {
		return $this->saved;
	}

	/**
	 * Affiche l'écran.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings = $this->repository->load();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Restriction par adresse IP', 'ip-restriction-for-restrict-content' ) . '</h1>';

		if ( self::REFUSED_NONCE === $this->refusal ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Votre page avait expiré : rien n\'a été enregistré. Rechargez l\'écran et recommencez.', 'ip-restriction-for-restrict-content' ) . '</p></div>';
		}

		if ( $this->saved ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Réglages enregistrés.', 'ip-restriction-for-restrict-content' ) . '</p></div>';
		}

		echo '<form method="post" action="">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$this->render_list_fields( $settings );
		$this->render_proxy_fields( $settings );
		$this->render_uninstall_field( $settings );

		submit_button();

		echo '</form>';

		$this->render_diagnostics( $settings );

		echo '</div>';
	}

	/**
	 * Affiche les deux listes et le message de blocage.
	 *
	 * Les deux listes sont rendues par deux appels distincts et des champs
	 * nommés séparément : aucun code commun paramétré par un nom de liste
	 * (A-01).
	 *
	 * @param Settings $settings Réglages courants.
	 * @return void
	 */
	private function render_list_fields( Settings $settings ): void {
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="rcipr_allow_list">'
			. esc_html__( 'Liste d\'autorisation globale', 'ip-restriction-for-restrict-content' )
			. '</label></th><td>';
		echo '<textarea id="rcipr_allow_list" name="rcipr_allow_list" rows="8" cols="50" class="large-text code">'
			. esc_textarea( $settings->allow_list() ) . '</textarea>';
		echo '<p class="description">'
			. esc_html__( 'Une entrée par ligne. Ces adresses accèdent à tout contenu restreint du site.', 'ip-restriction-for-restrict-content' )
			. '</p></td></tr>';

		echo '<tr><th scope="row"><label for="rcipr_block_list">'
			. esc_html__( 'Liste de blocage d\'inscription', 'ip-restriction-for-restrict-content' )
			. '</label></th><td>';
		echo '<textarea id="rcipr_block_list" name="rcipr_block_list" rows="8" cols="50" class="large-text code">'
			. esc_textarea( $settings->block_list() ) . '</textarea>';
		echo '<p class="description">'
			. esc_html__( 'Une entrée par ligne. Ces adresses ne peuvent pas s\'inscrire ; leur accès au contenu n\'est pas modifié.', 'ip-restriction-for-restrict-content' )
			. '</p></td></tr>';

		echo '<tr><th scope="row"><label for="rcipr_blocked_message">'
			. esc_html__( 'Message d\'erreur de blocage', 'ip-restriction-for-restrict-content' )
			. '</label></th><td>';
		echo '<input type="text" id="rcipr_blocked_message" name="rcipr_blocked_message" class="large-text" value="'
			. esc_attr( $settings->blocked_message() ) . '" />';
		echo '</td></tr>';

		echo '</tbody></table>';
	}

	/**
	 * Affiche la déclaration de proxys et le choix d'en-tête.
	 *
	 * @param Settings $settings Réglages courants.
	 * @return void
	 */
	private function render_proxy_fields( Settings $settings ): void {
		echo '<h2>' . esc_html__( 'Derrière un proxy ou un CDN', 'ip-restriction-for-restrict-content' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="rcipr_trusted_proxies">'
			. esc_html__( 'Proxys de confiance', 'ip-restriction-for-restrict-content' )
			. '</label></th><td>';
		echo '<textarea id="rcipr_trusted_proxies" name="rcipr_trusted_proxies" rows="4" cols="50" class="large-text code">'
			. esc_textarea( $settings->trusted_proxies() ) . '</textarea>';
		echo '<p class="description">'
			. esc_html__( 'Tant que ce champ est vide, aucun en-tête HTTP n\'est lu : seule l\'adresse de la connexion est évaluée.', 'ip-restriction-for-restrict-content' )
			. '</p></td></tr>';

		echo '<tr><th scope="row"><label for="rcipr_forwarded_header">'
			. esc_html__( 'En-tête de transfert à lire', 'ip-restriction-for-restrict-content' )
			. '</label></th><td>';
		echo '<select id="rcipr_forwarded_header" name="rcipr_forwarded_header">';
		echo '<option value="' . esc_attr( Settings::NO_HEADER ) . '"'
			. selected( Settings::NO_HEADER, $settings->forwarded_header(), false ) . '>'
			. esc_html__( 'aucun', 'ip-restriction-for-restrict-content' ) . '</option>';

		foreach ( Settings::READABLE_HEADERS as $header ) {
			echo '<option value="' . esc_attr( $header ) . '"'
				. selected( $header, $settings->forwarded_header(), false ) . '>'
				. esc_html( $header ) . '</option>';
		}

		echo '</select></td></tr>';
		echo '</tbody></table>';
	}

	/**
	 * Affiche le choix de suppression à la désinstallation.
	 *
	 * @param Settings $settings Réglages courants.
	 * @return void
	 */
	private function render_uninstall_field( Settings $settings ): void {
		echo '<table class="form-table"><tbody><tr><th scope="row">'
			. esc_html__( 'Désinstallation', 'ip-restriction-for-restrict-content' )
			. '</th><td><label><input type="checkbox" name="rcipr_deletes_on_uninstall" value="1"'
			. checked( true, $settings->deletes_on_uninstall(), false ) . ' /> '
			. esc_html__( 'Supprimer ces réglages si le plugin est désinstallé', 'ip-restriction-for-restrict-content' )
			. '</label></td></tr></tbody></table>';
	}

	/**
	 * Affiche le diagnostic de la requête en cours — FNC-29.
	 *
	 * L'adresse y est **complète**, et c'est délibéré. FNC-29 existe parce que
	 * la résolution derrière un proxy est la partie la plus facile à mal
	 * configurer et la plus difficile à observer : sans cet écran, un
	 * administrateur ne peut pas distinguer « ma règle est fausse » de
	 * « l'adresse évaluée n'est pas celle que je crois ». Une adresse expurgée
	 * ne permettrait pas cette distinction.
	 *
	 * SEC-06 n'en est pas contredite : elle porte sur ce que le plugin
	 * **écrit** — avis, arguments d'action, journaux —, et c'est là que
	 * `Support\Redactor` s'applique. Ici, rien n'est écrit : l'adresse du
	 * lecteur lui est montrée, à lui seul, le temps d'une requête, et il
	 * détient la capacité qui garde l'écran.
	 *
	 * @param Settings $settings Réglages courants.
	 * @return void
	 */
	private function render_diagnostics( Settings $settings ): void {
		$environment = Server_Environment::from_globals();
		$address     = Client_Address_Resolver::resolve(
			$environment->connection_address(),
			Rule_Set::from_text( $settings->trusted_proxies() ),
			$environment->forwarded_header( $settings->forwarded_header() )
		);

		echo '<h2>' . esc_html__( 'Diagnostic de la requête en cours', 'ip-restriction-for-restrict-content' ) . '</h2>';
		echo '<table class="widefat striped"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Adresse retenue', 'ip-restriction-for-restrict-content' ) . '</th><td><code>'
			. esc_html( $address->is_known() ? $address->to_string() : __( 'indéterminable', 'ip-restriction-for-restrict-content' ) )
			. '</code></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Source', 'ip-restriction-for-restrict-content' ) . '</th><td>'
			. esc_html( $this->source_label( $address ) ) . '</td></tr>';
		echo '</tbody></table>';
	}

	/**
	 * Libellé de la provenance de l'adresse retenue.
	 *
	 * @param Client_Address $address Adresse retenue.
	 * @return string
	 */
	private function source_label( Client_Address $address ): string {
		if ( Address_Source::CONNECTION === $address->source() ) {
			return __( 'adresse de la connexion ; aucun en-tête n\'est lu', 'ip-restriction-for-restrict-content' );
		}

		if ( Address_Source::FORWARDED_HEADER === $address->source() ) {
			return sprintf(
				/* translators: 1: nom de l'en-tête lu, 2: rang de la valeur retenue dans la chaîne. */
				__( 'en-tête %1$s, valeur de rang %2$d', 'ip-restriction-for-restrict-content' ),
				$this->repository->load()->forwarded_header(),
				(int) $address->position()
			);
		}

		return $this->undetermined_label( $address->reason() );
	}

	/**
	 * Libellé de la raison d'une adresse indéterminable.
	 *
	 * @param string $reason Raison issue de `Client_Address`.
	 * @return string
	 */
	private function undetermined_label( string $reason ): string {
		switch ( $reason ) {
			case Client_Address::NO_CONNECTION_ADDRESS:
				return __( 'le serveur web n\'a transmis aucune adresse de connexion', 'ip-restriction-for-restrict-content' );
			case Client_Address::MISSING_HEADER:
				return __( 'la connexion vient d\'un proxy déclaré, mais l\'en-tête choisi est absent de la requête', 'ip-restriction-for-restrict-content' );
			case Client_Address::HEADER_UNPARSABLE:
				return __( 'la valeur de l\'en-tête n\'est pas analysable', 'ip-restriction-for-restrict-content' );
			default:
				return __( 'la chaîne de transfert ne contient que des proxys déclarés', 'ip-restriction-for-restrict-content' );
		}
	}
}
