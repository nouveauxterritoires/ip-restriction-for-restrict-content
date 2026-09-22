# ADR-0004 — Stockage du réglage de niveau en métadonnée de niveau

- **Statut** : accepté
- **Date** : 22 septembre 2026
- **Portée** : cahier des charges §6.3
- **Point ouvert associé** : O-04

## Contexte

La liste d'autorisation propre à un niveau d'adhésion doit être persistée. La
dépendance expose une interface de métadonnées de niveau, mais cette interface
appartient à un plugin tiers : s'y appuyer crée une dépendance supplémentaire.

## Options envisagées

| Option | Suppression d'un niveau | Dépendance amont | Lecture |
|---|---|---|---|
| A — clé de l'option globale, indexée par identifiant de niveau | Laisse une entrée orpheline | Aucune | Déjà en mémoire |
| **B — métadonnée de niveau, par l'API de la dépendance** | Emportée avec le niveau | Quatre fonctions de `core/` | Une lecture par niveau évalué |
| C — option distincte par niveau | Laisse une option orpheline | Aucune | Une lecture par niveau |

## Décision

**Option B**, par `rcp_add_membership_level_meta()`,
`rcp_get_membership_level_meta()`, `rcp_update_membership_level_meta()` et
`rcp_delete_membership_level_meta()`, sous la clé `rcipr_ip_allow_list`.

Ces quatre fonctions sont dans `core/includes/levels/meta.php:26, 48, 69, 94`,
donc disponibles dans les deux variantes, et confirmées présentes dans le stub
de la variante gratuite v4.0.4 (`restrict-content.stub.php:33887, 33918`).

## Justification

**Le critère décisif est ce qui se passe quand un niveau est supprimé.** Une
règle d'autorisation qui survit à l'objet auquel elle s'appliquait est le
défaut le plus dangereux que ce plugin puisse produire : l'identifiant d'un
niveau supprimé peut être réattribué, et le réglage se rattacherait alors
silencieusement à un autre niveau. Les options A et C exigent une accroche de
nettoyage — donc une accroche qu'on peut oublier, qu'on peut mal brancher, et
dont l'échec est silencieux. L'option B fait porter la garantie par la
dépendance elle-même.

**La dépendance supplémentaire est acceptable parce qu'elle est déjà là.** Le
plugin ne fonctionne pas sans la dépendance : dépendre de quatre fonctions de
plus ne change pas sa condition d'existence. Ces quatre fonctions entrent dans
la sonde de capacités (§5.2) et dans la suite `contract`.

**Le coût de lecture est borné.** Une évaluation ne consulte que les niveaux
auxquels le contenu demandé est restreint, ce qui fait une à trois lectures de
métadonnée par requête, mises en cache par WordPress pour la durée de la
requête.

## Conséquences

| Positive | Négative | Mitigation |
|---|---|---|
| La suppression d'un niveau emporte son réglage, sans code | Dépendance à quatre fonctions de plus | Sonde de capacités, et un test de contrat par fonction |
| Le réglage suit le niveau dans les exports et les sauvegardes de la dépendance | Le réglage n'est pas visible dans une exportation propre au plugin | Sans objet : aucune exportation propre n'est livrée |
| Modèle de données homogène avec celui de la dépendance | Si la dépendance retirait cette API, le réglage deviendrait illisible | La suite `contract` le verrait le jour même, et le plugin échouerait proprement en désactivant la seule portée par niveau (E-04) |

## Conditions de révision

Si la suite `contract` établissait que l'API de métadonnées de niveau n'est pas
disponible dans une variante ou une version visée, l'option A deviendrait le
repli — accompagnée, alors, de l'accroche de nettoyage et de son test.
