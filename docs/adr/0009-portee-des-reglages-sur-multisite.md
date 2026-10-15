# ADR-0009 — Les réglages sont propres à chaque site d'un réseau

- **Statut** : accepté
- **Date** : 15 octobre 2026
- **Portée** : cahier des charges §6.2, §11.7, §15.1
- **Point ouvert associé** : O-08, tranché par cette décision

## Contexte

Le lot 4 écrit le premier réglage persistant du plugin. Il faut donc décider ce
qu'un réseau multisite voit : un réglage par site, ou un réglage de réseau.

La question n'est pas théorique. Les deux listes accordent et refusent des
accès : leur portée décide de ce qu'une erreur de saisie peut atteindre.

## Options envisagées

| Option | Configuration d'un réseau de cent sites | Portée d'une erreur de saisie | Coût |
|---|---|---|---|
| **A — par site** | Cent fois | Un site | Aucun : `get_option()` l'est par construction |
| B — par réseau | Une fois | **Tous les sites du réseau** | Un écran de réseau, et un modèle de données à deux niveaux |
| C — par réseau avec surcharge par site | Une fois, puis les exceptions | Tous les sites, sauf ceux qui surchargent | Le plus élevé : deux écrans, deux stockages, et une règle de précédence à documenter et à tester |

## Décision

**Option A.** Les réglages sont propres à chaque site du réseau, par
`get_option()` et `update_option()`, qui le sont par construction. Aucun écran
de réseau n'est fourni.

## Justification

**C'est ce que le cahier des charges supposait déjà.** Le scénario d'activation
multisite du §11.7 énonce « les réglages sont propres à chaque site du réseau ».
Trancher autrement aurait demandé de réécrire ce scénario ; le trancher ainsi ne
demande que de le vérifier.

**Une liste d'autorisation de réseau est un amplificateur d'erreur.** Une règle
trop couvrante saisie une fois ouvrirait le contenu restreint de **tous** les
sites, et sur un réseau, celui qui administre le réseau n'est pas toujours celui
qui connaît les plages d'adresses de chaque site. Le §4.6 pose que l'erreur par
excès sur la liste d'autorisation est silencieuse et durable : lui donner la
portée du réseau multiplie sa gravité par le nombre de sites.

**La contrainte invoquée par l'option B est réelle mais contournable.** Un
réseau qui veut la même liste partout peut la poser par le filtre
`ip_restriction_for_restrict_content_rules` (§8.1) depuis un plugin de réseau,
en une dizaine de lignes et sous le contrôle de qui administre le réseau. C'est
moins commode qu'un écran, et c'est exactement la bonne commodité : poser une
liste pour cent sites doit demander un geste délibéré.

**L'option C est la plus coûteuse et la plus risquée.** Une règle de précédence
entre un réglage de réseau et un réglage de site est précisément le genre de
règle qu'on croit connaître et qu'on applique de travers une fois sur dix. Sur
un contrôle d'accès, une fois sur dix est beaucoup.

## Conséquences

| Positive | Négative | Mitigation |
|---|---|---|
| Une erreur de saisie reste confinée à un site | Un réseau de cent sites se configure cent fois | Le filtre du §8.1 permet une liste commune, par code, sous contrôle du réseau |
| Aucun écran de réseau, aucun modèle à deux niveaux | Aucune vue d'ensemble des réglages du réseau | Hors périmètre de la v1.0.0 ; un réseau qui en a besoin a déjà des outils d'inventaire d'options |
| La désinstallation réseau supprime site par site, et c'est écrit ainsi | Une boucle sur les sites à la désinstallation | `uninstall.php` la porte, et elle est explicite |
| Rétrocompatible avec l'option C | — | Un réglage de réseau pourrait plus tard servir de **valeur par défaut** aux sites, sans changer le stockage par site |

## Conditions de révision

Si un exploitant de réseau établit le besoin — plusieurs sites partageant la
même liste, administrés par la même personne —, l'option C se rouvre sous la
forme rétrocompatible décrite ci-dessus : un réglage de réseau lu comme valeur
par défaut, jamais comme une surcharge. L'ordre de précédence serait alors à
documenter avant d'être écrit.
