<?php
/**
 * Les onze scénarios du §11.3 — la partie qui relève de la résolution.
 *
 * C'est le principal risque de sécurité du plugin, et il ne se voit pas : une
 * liste d'autorisation évaluée sur une valeur que le visiteur contrôle n'est
 * pas un contrôle d'accès dégradé, c'est un contournement d'authentification à
 * une ligne d'en-tête. La dépendance elle-même fait le mauvais choix
 * (`rcp_get_ip()`, JE-005), et c'est pourquoi ce plugin résout l'adresse pour
 * lui-même (R-06).
 *
 * Chaque test de ce fichier est un scénario nommé de la table du §11.3. Ce
 * n'est pas une liste indicative : c'est la traduction de SEC-07 à SEC-09, et
 * l'absence d'un seul de ces tests laisse une voie de contournement non
 * couverte.
 *
 * Les adresses utilisées sont celles que la RFC 5737 réserve à la
 * documentation, et la RFC 3849 pour IPv6 : aucune fixture ne porte d'adresse
 * réellement attribuée (§11.4).
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Tests\Matching;

use Ip_Restriction_For_Restrict_Content\Address\Address_Source;
use Ip_Restriction_For_Restrict_Content\Address\Client_Address;
use Ip_Restriction_For_Restrict_Content\Address\Client_Address_Resolver;
use Ip_Restriction_For_Restrict_Content\Rule\Rule_Set;
use WP_UnitTestCase;

/**
 * Résolution de l'adresse du visiteur.
 */
final class ClientAddressResolverTest extends WP_UnitTestCase {

	/**
	 * Adresse du proxy de confiance déclaré dans les scénarios.
	 *
	 * @var string
	 */
	private const PROXY = '203.0.113.7';

	/**
	 * Adresse réelle du visiteur.
	 *
	 * @var string
	 */
	private const REAL = '198.51.100.9';

	/**
	 * Adresse qu'un attaquant tenterait de faire retenir.
	 *
	 * @var string
	 */
	private const INJECTED = '192.0.2.10';

	/**
	 * Liste de proxys déclarée.
	 *
	 * @param string $text Texte du réglage.
	 * @return Rule_Set
	 */
	private function proxies( string $text = '' ): Rule_Set {
		return Rule_Set::from_text( $text );
	}

	/**
	 * Scénario 1 : en-tête injecté sans proxy déclaré.
	 *
	 * C'est l'installation par défaut, et c'est le scénario qui compte le
	 * plus : aucun en-tête n'est lu, quoi que le visiteur envoie (R-01,
	 * SEC-07).
	 *
	 * @return void
	 */
	public function test_an_injected_header_is_ignored_without_declared_proxies(): void {
		$address = Client_Address_Resolver::resolve( self::REAL, $this->proxies(), self::INJECTED );

		$this->assertSame( self::REAL, $address->to_string() );
		$this->assertSame( Address_Source::CONNECTION, $address->source() );
	}

	/**
	 * Scénario 2 : second en-tête injecté, même absence de proxy déclaré.
	 *
	 * Le nom de l'en-tête ne change rien : le résolveur ne reçoit une valeur
	 * d'en-tête que si l'administrateur en a déclaré un, et il ne la lit que
	 * derrière un proxy déclaré.
	 *
	 * @return void
	 */
	public function test_any_header_is_ignored_without_declared_proxies(): void {
		$address = Client_Address_Resolver::resolve(
			self::REAL,
			$this->proxies(),
			self::INJECTED . ', ' . self::INJECTED
		);

		$this->assertSame( self::REAL, $address->to_string() );
		$this->assertSame( Address_Source::CONNECTION, $address->source() );
	}

	/**
	 * Scénario 3 : proxy déclaré, mais requête directe.
	 *
	 * L'en-tête est ignoré parce que l'adresse de connexion n'est pas celle
	 * d'un proxy déclaré (R-03, SEC-08).
	 *
	 * @return void
	 */
	public function test_a_direct_request_ignores_the_header_even_with_declared_proxies(): void {
		$address = Client_Address_Resolver::resolve(
			self::REAL,
			$this->proxies( self::PROXY ),
			self::INJECTED
		);

		$this->assertSame( self::REAL, $address->to_string() );
		$this->assertSame( Address_Source::CONNECTION, $address->source() );
	}

	/**
	 * Scénario 4 : chaîne préfixée par le client.
	 *
	 * `X-Forwarded-For` s'accroît par la gauche à chaque saut : un client qui
	 * envoie déjà une valeur fait arriver « <injectée>, <réelle> ». Lire le
	 * premier élément retiendrait l'injectée ; lire de droite à gauche retient
	 * la réelle (R-04, SEC-09).
	 *
	 * @return void
	 */
	public function test_a_chain_prefixed_by_the_client_resolves_to_the_real_address(): void {
		$address = Client_Address_Resolver::resolve(
			self::PROXY,
			$this->proxies( self::PROXY ),
			self::INJECTED . ', ' . self::REAL
		);

		$this->assertSame( self::REAL, $address->to_string() );
		$this->assertSame( Address_Source::FORWARDED_HEADER, $address->source() );
	}

	/**
	 * Scénario 5 : chaîne entièrement composée de proxys déclarés.
	 *
	 * Aucune valeur de la chaîne ne peut être celle du client : l'adresse est
	 * indéterminable, et surtout pas celle du proxy (R-05).
	 *
	 * @return void
	 */
	public function test_a_chain_of_only_proxies_leaves_the_address_undetermined(): void {
		$address = Client_Address_Resolver::resolve(
			self::PROXY,
			$this->proxies( self::PROXY . "\n203.0.113.8" ),
			'203.0.113.8, ' . self::PROXY
		);

		$this->assertFalse( $address->is_known() );
		$this->assertSame( Address_Source::UNDETERMINED, $address->source() );
	}

	/**
	 * Scénario 6 : valeur d'en-tête non analysable.
	 *
	 * Jamais de repli sur une valeur arbitraire — et surtout pas sur l'adresse
	 * du proxy, qui est celle du CDN (R-05).
	 *
	 * @return void
	 */
	public function test_an_unparsable_header_leaves_the_address_undetermined(): void {
		$address = Client_Address_Resolver::resolve(
			self::PROXY,
			$this->proxies( self::PROXY ),
			'bonjour'
		);

		$this->assertFalse( $address->is_known() );
		$this->assertSame( Client_Address::HEADER_UNPARSABLE, $address->reason() );
	}

	/**
	 * Un élément illisible **à gauche** de l'adresse retenue ne ferme rien.
	 *
	 * La lecture s'arrête au premier élément qui n'est pas un proxy déclaré,
	 * en remontant depuis la droite. Tout ce qui se trouve à gauche de cet
	 * élément est du texte que le client a pu écrire lui-même et qu'aucun
	 * proxy de confiance n'a cautionné : s'en servir pour fermer la résolution
	 * donnerait à n'importe quel visiteur le moyen de neutraliser la liste
	 * d'autorisation en envoyant un en-tête absurde.
	 *
	 * @return void
	 */
	public function test_an_unparsable_element_on_the_left_does_not_close_the_chain(): void {
		$address = Client_Address_Resolver::resolve(
			self::PROXY,
			$this->proxies( self::PROXY ),
			'bonjour, ' . self::REAL
		);

		$this->assertSame( self::REAL, $address->to_string() );
	}

	/**
	 * Un élément illisible **à droite** ferme, lui.
	 *
	 * L'élément le plus à droite est celui que notre propre proxy de confiance
	 * a ajouté : s'il est illisible, c'est la configuration du proxy qui est en
	 * cause, et aucune valeur de la chaîne ne peut plus être attribuée à un
	 * saut connu (R-05).
	 *
	 * @return void
	 */
	public function test_an_unparsable_element_on_the_right_closes_the_chain(): void {
		$address = Client_Address_Resolver::resolve(
			self::PROXY,
			$this->proxies( self::PROXY ),
			self::REAL . ', bonjour'
		);

		$this->assertFalse( $address->is_known() );
		$this->assertSame( Client_Address::HEADER_UNPARSABLE, $address->reason() );
	}

	/**
	 * Derrière un proxy déclaré, un en-tête absent laisse l'adresse
	 * indéterminable.
	 *
	 * Retenir l'adresse de connexion reviendrait à évaluer les règles contre
	 * l'adresse du CDN : c'est exactement le piège que l'option B d'ADR-0006
	 * décrit, et une règle posée sur cette adresse ouvrirait le site à tout
	 * l'internet.
	 *
	 * @return void
	 */
	public function test_a_missing_header_behind_a_proxy_leaves_the_address_undetermined(): void {
		$this->assertFalse(
			Client_Address_Resolver::resolve( self::PROXY, $this->proxies( self::PROXY ), '' )->is_known()
		);
		$this->assertFalse(
			Client_Address_Resolver::resolve( self::PROXY, $this->proxies( self::PROXY ), null )->is_known()
		);
	}

	/**
	 * Une adresse de connexion absente ou illisible laisse l'adresse
	 * indéterminable.
	 *
	 * @return void
	 */
	public function test_an_unreadable_connection_address_leaves_the_address_undetermined(): void {
		$address = Client_Address_Resolver::resolve( '', $this->proxies(), null );

		$this->assertFalse( $address->is_known() );
		$this->assertSame( Client_Address::NO_CONNECTION_ADDRESS, $address->reason() );
	}

	/**
	 * La chaîne est remontée en sautant tous les proxys déclarés, quel qu'en
	 * soit le nombre.
	 *
	 * @return void
	 */
	public function test_every_declared_proxy_is_skipped_from_the_right(): void {
		$address = Client_Address_Resolver::resolve(
			self::PROXY,
			$this->proxies( '203.0.113.0/24' ),
			self::INJECTED . ', ' . self::REAL . ', 203.0.113.8, ' . self::PROXY
		);

		$this->assertSame( self::REAL, $address->to_string() );
		$this->assertSame( 1, $address->position(), 'Le rang retenu est compté depuis la gauche (R-07).' );
	}

	/**
	 * Un proxy déclaré par un bloc CIDR est reconnu comme tel.
	 *
	 * La liste de proxys suit la même grammaire que les listes d'adresses
	 * (§7.1) : c'est le même analyseur et le même moteur, donc toutes les
	 * formes y sont disponibles.
	 *
	 * @return void
	 */
	public function test_proxies_may_be_declared_by_cidr(): void {
		$address = Client_Address_Resolver::resolve(
			'203.0.113.200',
			$this->proxies( '203.0.113.0/24' ),
			self::REAL
		);

		$this->assertSame( self::REAL, $address->to_string() );
		$this->assertSame( Address_Source::FORWARDED_HEADER, $address->source() );
	}

	/**
	 * Une entrée illisible de la liste de proxys ne déclare aucun proxy.
	 *
	 * La défaillance va vers le refus : sans proxy reconnu, l'adresse de
	 * connexion est retenue, et l'en-tête ignoré (§4.5).
	 *
	 * @return void
	 */
	public function test_an_unreadable_proxy_list_declares_no_proxy(): void {
		$address = Client_Address_Resolver::resolve(
			self::PROXY,
			$this->proxies( 'exemple.test' ),
			self::REAL
		);

		$this->assertSame( self::PROXY, $address->to_string() );
		$this->assertSame( Address_Source::CONNECTION, $address->source() );
	}

	/**
	 * Une adresse d'en-tête accompagnée de son port est lue.
	 *
	 * Les proxys réels en émettent — `192.0.2.10:52000` en IPv4,
	 * `[2001:db8::1]:443` en IPv6 (§11.4). Refuser ces formes rendrait le
	 * plugin inutilisable derrière les CDN qui les produisent ; les accepter
	 * n'ajoute aucune ambiguïté, puisque la séparation est sans équivoque.
	 *
	 * @return void
	 */
	public function test_an_address_with_its_port_is_read(): void {
		$ipv4 = Client_Address_Resolver::resolve(
			self::PROXY,
			$this->proxies( self::PROXY ),
			self::REAL . ':52000'
		);

		$this->assertSame( self::REAL, $ipv4->to_string() );

		$ipv6 = Client_Address_Resolver::resolve(
			self::PROXY,
			$this->proxies( self::PROXY ),
			'[2001:db8::1]:443'
		);

		$this->assertSame( '2001:db8::1', $ipv6->to_string() );
	}

	/**
	 * Les formes d'adresse et de port qui ne sont pas sans équivoque sont
	 * refusées.
	 *
	 * Accepter `[2001:db8::1` ou `198.51.100.9:abc` demanderait de deviner où
	 * s'arrête l'adresse. La grammaire reste close, ici comme pour les règles.
	 *
	 * @return void
	 */
	public function test_an_ambiguous_address_and_port_form_is_refused(): void {
		foreach ( array( '[2001:db8::1', '[2001:db8::1]bonjour', self::REAL . ':abc', '[]:443' ) as $element ) {
			$address = Client_Address_Resolver::resolve(
				self::PROXY,
				$this->proxies( self::PROXY ),
				$element
			);

			$this->assertFalse(
				$address->is_known(),
				sprintf( '« %s » n\'est pas une forme sans équivoque.', $element )
			);
		}
	}

	/**
	 * Une adresse IPv6 sans crochets est lue telle quelle.
	 *
	 * @return void
	 */
	public function test_a_bare_ipv6_address_is_read(): void {
		$address = Client_Address_Resolver::resolve(
			self::PROXY,
			$this->proxies( self::PROXY ),
			'2001:db8::1'
		);

		$this->assertSame( '2001:db8::1', $address->to_string() );
	}

	/**
	 * Un proxy IPv6 est reconnu, et la chaîne mêlant les deux familles est
	 * remontée correctement.
	 *
	 * Un site à double pile reçoit les deux, et le saut de proxy peut changer
	 * de famille en chemin.
	 *
	 * @return void
	 */
	public function test_a_dual_stack_chain_is_walked_correctly(): void {
		$address = Client_Address_Resolver::resolve(
			'2001:db8::ff',
			$this->proxies( "2001:db8::ff\n" . self::PROXY ),
			self::REAL . ', ' . self::PROXY
		);

		$this->assertSame( self::REAL, $address->to_string() );
	}

	/**
	 * Les espaces superflus de la chaîne sont tolérés.
	 *
	 * @return void
	 */
	public function test_extra_spaces_in_the_chain_are_tolerated(): void {
		$address = Client_Address_Resolver::resolve(
			self::PROXY,
			$this->proxies( self::PROXY ),
			'  ' . self::INJECTED . ' ,   ' . self::REAL . '  '
		);

		$this->assertSame( self::REAL, $address->to_string() );
	}

	/**
	 * Une adresse indéterminable ne correspond à aucune règle.
	 *
	 * Le lien avec le moteur est explicite : c'est D-05, et c'est ce qui fait
	 * que la fermeture sur l'inconnu n'accorde jamais d'accès.
	 *
	 * @return void
	 */
	public function test_an_undetermined_address_carries_no_number(): void {
		$address = Client_Address_Resolver::resolve( 'bonjour', $this->proxies(), null );

		$this->assertNull( $address->number() );
		$this->assertSame( '', $address->to_string() );
	}
}
