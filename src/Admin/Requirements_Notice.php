<?php
/**
 * Avis d'administration listant les prérequis absents.
 *
 * L'avis nomme les capacités et les versions manquantes, jamais un nom de
 * plugin ni un numéro de version de la dépendance : c'est ce qui permet à
 * l'administrateur d'agir sans avoir à deviner ce que le plugin attendait.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Rendu de l'avis de prérequis.
 */
final class Requirements_Notice {

	/**
	 * Libellés des prérequis absents.
	 *
	 * @var list<string>
	 */
	private array $missing;

	/**
	 * Construit l'avis.
	 *
	 * @param string[] $missing Libellés des prérequis absents.
	 */
	public function __construct( array $missing ) {
		$this->missing = array_values( array_map( 'strval', $missing ) );
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
	 * Affiche l'avis.
	 *
	 * Rien n'est affiché à qui ne peut pas y remédier : l'avis apprendrait
	 * l'état de l'installation à un visiteur sans lui donner les moyens d'agir.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( array() === $this->missing || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		wp_admin_notice(
			$this->message(),
			array(
				'type'               => 'error',
				'additional_classes' => array( 'rcipr_requirements' ),
				'paragraph_wrap'     => false,
			)
		);
	}

	/**
	 * Compose le corps de l'avis.
	 *
	 * Chaque libellé est échappé un à un : un libellé provient d'une
	 * traduction, et une traduction est une donnée comme une autre.
	 *
	 * @return string Balisage de l'avis.
	 */
	private function message(): string {
		$items = '';

		foreach ( $this->missing as $requirement ) {
			$items .= '<li>' . esc_html( $requirement ) . '</li>';
		}

		return '<p><strong>'
			. esc_html__( 'IP Restriction for Restrict Content ne peut pas démarrer : prérequis absents.', 'ip-restriction-for-restrict-content' )
			. '</strong></p><ul class="ul-disc">' . $items . '</ul>';
	}
}
