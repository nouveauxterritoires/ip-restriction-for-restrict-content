<?php
/**
 * Noms des points d'extension du §8, déclarés ici et nulle part ailleurs.
 *
 * Un nom de hook écrit en clair à deux endroits finit par diverger d'un
 * caractère, et le filtre cesse silencieusement d'être appelé — sans erreur,
 * sans avertissement, et sans que personne ne s'en aperçoive avant qu'un
 * exploitant ne demande pourquoi son filtre ne sert à rien. La constante rend
 * la divergence impossible et la renomination sûre.
 *
 * Tous sont préfixés par la forme longue (ADR-0001).
 *
 * @package Ip_Restriction_For_Restrict_Content
 */

declare( strict_types = 1 );

namespace Ip_Restriction_For_Restrict_Content\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Constantes des noms de hooks.
 */
final class Extension_Points {

	/**
	 * Filtre : dernier mot sur l'adresse retenue.
	 *
	 * Pour un hébergement dont l'en-tête n'est pas dans la liste fermée du
	 * §7.1.
	 *
	 * @var string
	 */
	public const CLIENT_ADDRESS = 'ip_restriction_for_restrict_content_client_address';

	/**
	 * Filtre : proxys de confiance déclarés par code.
	 *
	 * Par exemple depuis `wp-config.php`, ou depuis un plugin de réseau qui
	 * veut la même déclaration sur tous les sites.
	 *
	 * @var string
	 */
	public const TRUSTED_PROXIES = 'ip_restriction_for_restrict_content_trusted_proxies';

	/**
	 * Filtre : compléter une liste par une source externe.
	 *
	 * @var string
	 */
	public const RULES = 'ip_restriction_for_restrict_content_rules';

	/**
	 * Filtre : observer ou restreindre une correspondance.
	 *
	 * Ne peut que **retirer** une correspondance, jamais en ajouter : un
	 * filtre capable d'en ajouter transformerait tout plugin tiers en
	 * fournisseur d'accès au contenu payant, et mettrait le contrôle hors de
	 * portée de l'administrateur qui a rempli les listes. Le retour est
	 * intersecté avec le résultat d'origine, et un test le vérifie.
	 *
	 * @var string
	 */
	public const MATCH = 'ip_restriction_for_restrict_content_match';

	/**
	 * Filtre : dernier mot sur la décision d'accès.
	 *
	 * Soumis à la même contrainte que `MATCH` : il ne peut que retirer un
	 * octroi.
	 *
	 * @var string
	 */
	public const ACCESS_DECISION = 'ip_restriction_for_restrict_content_access_decision';

	/**
	 * Action : un accès vient d'être accordé.
	 *
	 * Pour la journalisation par l'exploitant. L'adresse transmise est
	 * expurgée (SEC-06) : ce que l'exploitant journalise ensuite relève de sa
	 * responsabilité, ce que le plugin lui remet relève de la nôtre.
	 *
	 * @var string
	 */
	public const ACCESS_GRANTED = 'ip_restriction_for_restrict_content_access_granted';
}
