# ADR-0005 — Étendue du support IPv6

- **Statut** : accepté
- **Date** : 22 septembre 2026
- **Portée** : cahier des charges §3.3 et §4.2
- **Point ouvert associé** : O-05, vérification V-03

## Contexte

La documentation de l'add-on de référence annonce « Both IPv4 and IPv6 are
supported » et ne donne **aucun exemple IPv6** : ni littéral, ni CIDR, ni plage,
ni joker. L'étendue réelle du support amont est donc inconnue, et non
vérifiable — lire le code de l'add-on est exclu par le développement en salle
blanche (cahier des charges §0.3). C'est le point ouvert V-03.

Il faut néanmoins décider ce que **ce** plugin accepte, parce que l'analyseur
doit être écrit.

## Options envisagées

| Option | Formes IPv6 acceptées | Risque |
|---|---|---|
| A — parité complète avec IPv4 : littéral, CIDR, plage, joker | Toutes | Le joker IPv6 n'a pas de sémantique évidente : `2001:db8::*` désigne-t-il le dernier groupe, les quatre derniers, les 64 bits bas ? Chaque lecture est défendable, aucune n'est documentée |
| **B — littéral et CIDR** | `2001:db8::1`, `2001:db8::/32` | Un utilisateur qui écrit un joker IPv6 reçoit un rejet explicite |
| C — aucun support IPv6 | Aucune | Un site dual-stack devient inconfigurable pour la moitié de ses visiteurs |

## Décision

**Option B.** Sont acceptées en IPv6 :

| Forme | Exemple | Accepté |
|---|---|---|
| Littéral, forme complète | `2001:0db8:0000:0000:0000:0000:0000:0001` | oui |
| Littéral, forme compressée | `2001:db8::1` | oui |
| CIDR | `2001:db8::/32` | oui |
| Plage entre deux littéraux | `2001:db8::1-2001:db8::ff` | oui |
| Joker | `2001:db8::*` | **non**, rejeté avec un motif explicite |
| Plage à bornes jokers | `2001:db8:1::*-2001:db8:3::*` | **non**, rejeté |
| Forme mixte IPv4 dans IPv6 | `::ffff:192.0.2.1` | oui, normalisée en IPv6 |

La plage entre deux littéraux est acceptée parce qu'elle n'a aucune ambiguïté :
deux bornes explicites, un intervalle. Le joker, lui, exige de décider ce qu'un
« groupe » signifie, et la notation compressée rend cette décision instable —
`2001:db8::*` et `2001:db8:0:0:0:0:0:*` s'écrivent différemment sans désigner
forcément la même chose.

## Justification

**Le joker IPv6 est une ambiguïté qu'on ne peut pas trancher sans source.**
Trois lectures sont défendables, et rien ne permet de choisir. Or accepter une
règle dont l'auteur et la machine ne s'accordent pas sur le sens est exactement
le défaut que la grammaire close du §4.2 existe pour prévenir : une règle
d'autorisation mal comprise ouvre du contenu qu'on croyait fermé.

**Le CIDR couvre le besoin réel.** Ce qu'un administrateur veut exprimer par un
joker IPv6, il l'exprime exactement par un CIDR, et c'est la notation que les
hébergeurs et les opérateurs publient : un réseau IPv6 se communique sous la
forme `2001:db8::/32`, jamais sous forme de joker.

**Le rejet explicite vaut mieux que l'acceptation approximative.** Un
utilisateur qui saisit `2001:db8::*` reçoit un message nommant la ligne, le
motif, et la forme à employer à la place. C'est une expérience moins bonne
qu'un joker qui marcherait, et bien meilleure qu'un joker dont il croirait
savoir ce qu'il fait.

## Conséquences

| Positive | Négative | Mitigation |
|---|---|---|
| Aucune règle IPv6 ambiguë ne peut exister | Parité incomplète avec IPv4 | La documentation utilisateur l'énonce, et le message de rejet nomme la forme de remplacement |
| L'analyseur IPv6 reste petit et exhaustivement testable | Divergence possible avec le comportement de l'add-on de référence | Cette divergence est assumée : l'annexe B du cahier des charges dit que le comportement amont est inconnu, et le §0.3 interdit de le découvrir |
| Un site dual-stack est configurable | Une même personne peut atteindre le site sous deux familles d'adresses, et n'être couverte que par l'une | Point d'exploitation documenté : la documentation utilisateur recommande d'inscrire les deux |

## Conditions de révision

Si une source publique venait établir la sémantique du joker IPv6 amont, la
décision se rouvre — et l'ajout serait rétrocompatible, puisqu'il ne ferait
qu'accepter des entrées aujourd'hui rejetées.
