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
	 * Filtre : dernier mot sur le blocage d'inscription.
	 *
	 * Contrairement à `MATCH` et `ACCESS_DECISION`, ce filtre peut aussi
	 * **ajouter** un blocage. L'asymétrie est celle du §4.6 : une erreur par
	 * excès sur la liste d'autorisation est une fuite d'accès silencieuse et
	 * durable, alors qu'une erreur par excès sur la liste de blocage refuse des
	 * inscriptions légitimes — ce qui est bruyant, et donc corrigé.
	 *
	 * @var string
	 */
	public const REGISTRATION_BLOCKED = 'ip_restriction_for_restrict_content_registration_blocked';

	/**
	 * Filtre : message affiché lorsqu'une inscription est bloquée.
	 *
	 * Le retour est **échappé après le filtre**, et ce n'est pas une
	 * précaution : la dépendance interpole le message dans son balisage sans
	 * l'échapper (`core/includes/error-tracking.php:47`, JE-023). Un filtre est
	 * du code tiers ; lui faire confiance pour l'échappement reviendrait à ne
	 * pas échapper.
	 *
	 * @var string
	 */
	public const BLOCKED_MESSAGE = 'ip_restriction_for_restrict_content_blocked_message';

	/**
	 * Action : une inscription vient d'être bloquée.
	 *
	 * Le nom diffère de celui du filtre ci-dessus, et il le doit : WordPress
	 * partage un seul registre entre actions et filtres, de sorte qu'un même
	 * nom ferait appeler les greffons du filtre par l'action — et
	 * réciproquement. Le cahier des charges §8.2 donnait le même nom aux deux ;
	 * la correction est consignée là-bas.
	 *
	 * @var string
	 */
	public const REGISTRATION_BLOCK_APPLIED = 'ip_restriction_for_restrict_content_registration_block_applied';

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
