<?php
/**
 * Blocage d'inscription — FNC-07 à FNC-12, SEC-03, SEC-17.
 *
 * L'accroche est `rcp_form_errors`, et non `rcp_before_form_errors` : la
 * première est atteinte **après** la validation du niveau d'adhésion (§5.1), et
 * s'accrocher avant ferait porter au plugin un refus que la dépendance aurait de
 * toute façon prononcé — en masquant la vraie cause à l'utilisateur.
 *
 * Ajouter une erreur à `rcp_errors()` suffit à annuler l'inscription : la
 * dépendance relit son collecteur juste après l'accroche et interrompt le
 * traitement si elle y trouve quelque chose. La suite `contract` l'établit par
 * l'exécution depuis le lot 1.
 *
 * Le message est **échappé ici**, et c'est la seule barrière : la dépendance
 * interpole les messages dans son balisage sans les échapper
 * (`core/includes/error-tracking.php:47`, JE-023).
 *
 * Le blocage ne touche que l'inscription. Il n'a aucun effet sur l'accès au
 * contenu (FNC-08) ni sur la connexion d'un compte existant (FNC-09), et la
 * forme du branchement l'assure : rien d'autre que cette action n'est accroché.
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Integration;

defined( 'ABSPATH' ) || exit;

use Ip_Restriction_For_Restrict_Content\Address\Client_Address;
use Ip_Restriction_For_Restrict_Content\Address\Client_Address_Resolver;
use Ip_Restriction_For_Restrict_Content\Http\Server_Environment;
use Ip_Restriction_For_Restrict_Content\Policy\Registration_Policy;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Set;
use Ip_Restriction_For_Restrict_Content\Settings\Settings_Repository;
use Ip_Restriction_For_Restrict_Content\Support\Blocked_Message;
use Ip_Restriction_For_Restrict_Content\Support\Extension_Points;
use Ip_Restriction_For_Restrict_Content\Support\Redactor;

/**
 * Accroche du blocage d'inscription.
 */
final class Registration_Guard {

	/**
	 * Code d'erreur ajouté au collecteur de la dépendance.
	 *
	 * @var string
	 */
	public const ERROR_CODE = 'rcipr_registration_blocked';

	/**
	 * Dépôt des réglages.
	 *
	 * @var Settings_Repository
	 */
	private Settings_Repository $repository;

	/**
	 * Adresse retenue, conclue une fois pour la requête.
	 *
	 * @var Client_Address|null
	 */
	private ?Client_Address $address = null;

	/**
	 * Construit le branchement.
	 *
	 * @param Settings_Repository $repository Dépôt des réglages.
	 */
	public function __construct( Settings_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Accroche le blocage à la validation du formulaire.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rcp_form_errors', array( $this, 'guard' ), 10, 0 );
	}

	/**
	 * Annule l'inscription si l'adresse est bloquée.
	 *
	 * @return void
	 */
	public function guard(): void {
		$settings = $this->repository->load();
		$address  = $this->resolved_address();

		$decision = Registration_Policy::decide(
			$address->number(),
			Rule_Set::from_text( $settings->block_list() )
		);

		$redacted = Redactor::redact_address( $address );

		/**
		 * Dernier mot sur le blocage d'inscription.
		 *
		 * Ce filtre peut aussi **ajouter** un blocage, contrairement à celui
		 * de la décision d'accès : l'asymétrie est celle du §4.6 — une erreur
		 * par excès ici refuse des inscriptions légitimes, ce qui est bruyant,
		 * là où la même erreur sur l'accès serait une fuite silencieuse.
		 *
		 * @param bool   $blocked  L'inscription est bloquée.
		 * @param string $redacted Adresse expurgée du visiteur.
		 */
		$blocked = (bool) apply_filters(
			Extension_Points::REGISTRATION_BLOCKED,
			$decision->is_blocked(),
			$redacted
		);

		if ( ! $blocked ) {
			return;
		}

		/**
		 * Message affiché lorsqu'une inscription est bloquée.
		 *
		 * Le retour est échappé juste après : la dépendance interpole les
		 * messages sans les échapper (JE-023), et un filtre est du code tiers.
		 *
		 * @param string $message  Message résolu par le plugin.
		 * @param string $redacted Adresse expurgée du visiteur.
		 */
		$message = (string) apply_filters(
			Extension_Points::BLOCKED_MESSAGE,
			Blocked_Message::resolve( $settings ),
			$redacted
		);

		rcp_errors()->add( self::ERROR_CODE, esc_html( $message ), 'register' );

		/**
		 * Signale qu'une inscription vient d'être bloquée.
		 *
		 * @param string $redacted Adresse expurgée du visiteur.
		 */
		do_action( Extension_Points::REGISTRATION_BLOCK_APPLIED, $redacted );
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

		/** This filter is documented in src/Integration/Content_Access.php */
		$proxies = (string) apply_filters( Extension_Points::TRUSTED_PROXIES, $settings->trusted_proxies() );

		$address = Client_Address_Resolver::resolve(
			$environment->connection_address(),
			Rule_Set::from_text( $proxies ),
			$environment->forwarded_header( $settings->forwarded_header() )
		);

		/** This filter is documented in src/Integration/Content_Access.php */
		$filtered = apply_filters( Extension_Points::CLIENT_ADDRESS, $address );

		$this->address = $filtered instanceof Client_Address ? $filtered : $address;

		return $this->address;
	}
}
