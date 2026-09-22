# ADR-0001 — Deux formes du préfixe

- **Statut** : accepté
- **Date** : 22 septembre 2026
- **Portée** : tout le dépôt
- **Point ouvert associé** : O-01

## Contexte

La directive 17 du répertoire WordPress.org impose la forme « fonction pour
marque » lorsqu'un nom de plugin mentionne une marque tierce : le slug retenu
est `ip-restriction-for-restrict-content`, soit 35 caractères. La forme longue
du préfixe qui en dérive — `ip_restriction_for_restrict_content_` — fait
36 caractères à elle seule, ce qui produit des noms de hooks longs.

Le projet de référence applique deux formes : une longue dérivée du slug pour
les symboles globaux PHP et les hooks, une courte pour les constantes, les
tables, les métadonnées et les chaînes destinées au balisage.

## Options envisagées

| Option | Hook produit | Constante produite | Fidélité à la convention |
|---|---|---|---|
| A — forme unique courte `rcipr` partout | `rcipr_access_granted` (20 car.) | `RCIPR_VERSION` | Dérogation |
| B — deux formes, comme le projet de référence | `ip_restriction_for_restrict_content_access_granted` (50 car.) | `RCIPR_VERSION` | Conforme |
| C — forme unique longue partout | idem B | `IP_RESTRICTION_FOR_RESTRICT_CONTENT_VERSION` (43 car.) | Conforme, mais illisible |

## Décision

**Option B.** Le partage des périmètres :

| Périmètre | Forme | Exemple |
|---|---|---|
| Fonctions globales | longue, `ip_restriction_for_restrict_content_` | `ip_restriction_for_restrict_content_boot()` |
| Hooks et filtres | longue | `ip_restriction_for_restrict_content_access_granted` |
| Options | longue | `ip_restriction_for_restrict_content_settings` |
| Namespace PSR-4 | longue, en Pascal_Snake | `Ip_Restriction_For_Restrict_Content\` |
| Constantes | courte, `RCIPR_` | `RCIPR_VERSION`, `RCIPR_MIN_PHP` |
| Métadonnées | courte, `rcipr_` / `_rcipr_` | `_rcipr_ip_allow_list` |
| Classes CSS, handles, variables Docker | courte | `RCIPR_HTTP_PORT` |
| Text domain | = slug | `ip-restriction-for-restrict-content` |

Les quatre préfixes sont déclarés dans `phpcs.xml.dist`, sur la règle
`WordPress.NamingConventions.PrefixAllGlobals` : ce fichier est la valeur de
référence, et le présent document n'en est que la justification.

## Justification

Les symboles globaux PHP et les hooks cohabitent dans un espace de noms partagé
par tous les plugins d'un site : c'est là que la collision coûte cher, et c'est
exactement le périmètre que contrôle `PrefixAllGlobals`. Un préfixe long y est
une assurance bon marché.

Les constantes, les métadonnées et les classes CSS ne sont pas dans le même
espace. Une constante `IP_RESTRICTION_FOR_RESTRICT_CONTENT_MIN_PHP` n'apporte
aucune protection supplémentaire face à `RCIPR_MIN_PHP`, et rend chaque
comparaison de version illisible. Une clé de métadonnée longue est écrite dans
chaque ligne de la table de métadonnées : la longueur y a un coût réel.

## Conséquences

| Positive | Négative | Mitigation |
|---|---|---|
| Conformité à `PrefixAllGlobals` sans exclusion | Deux formes à retenir | La règle de partage tient en une ligne : ce qui est public à WordPress porte la forme longue, le reste la forme courte |
| Hooks sans risque de collision | Noms de hooks longs à écrire | Les noms sont des constantes de `Support\Extension_Points`, jamais des chaînes littérales |
| Constantes et métadonnées lisibles | Un lecteur peut croire à une incohérence | Le présent document, et le commentaire en tête de `phpcs.xml.dist` |

## Conditions de révision

Un renommage du slug entraînerait mécaniquement un renommage de la forme longue
et du namespace. C'est pourquoi la question du slug a été instruite séparément
et tranchée avant le lot 1 : **le slug est conservé** (ADR-0008, O-03 clos). Le
présent ADR n'est donc pas exposé à une migration de nommage.

Il le redeviendrait si l'une des deux conditions de révision d'ADR-0008 se
réalisait — changement de nom du produit amont, ou réclamation de l'éditeur.
Dans ce cas, les deux ADR se révisent ensemble, jamais l'un sans l'autre.
