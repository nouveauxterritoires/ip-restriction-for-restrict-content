# ADR-0007 — Les deux listes ne partagent ni stockage ni chemin de décision

- **Statut** : accepté
- **Date** : 22 septembre 2026
- **Portée** : cahier des charges §4.6, règles A-01 à A-03
- **Exigences associées** : SEC-11, SEC-12

## Contexte

Le plugin porte deux listes qui se ressemblent beaucoup : deux zones de texte,
la même grammaire, le même moteur de correspondance. La tentation de les traiter
par un code commun paramétré — une fonction qui reçoit un nom de clé et renvoie
une décision — est forte, et elle est conforme au principe de non-répétition.

Elle est pourtant dangereuse, parce que les deux listes n'ont pas les mêmes
conséquences en cas d'erreur.

| | Liste d'autorisation | Liste de blocage |
|---|---|---|
| Effet | Accorde un accès | Refuse une inscription |
| Erreur par excès | **Fuite d'accès** : du contenu payant devient public. Silencieuse | Inscriptions légitimes refusées. Bruyante |
| Erreur par défaut | Accès refusé. Bruyante | **Blocage inopérant**. Silencieuse |
| Gravité maximale | Élevée | Modérée |

## Options envisagées

| Option | Code | Risque de confusion |
|---|---|---|
| A — un service générique paramétré par un nom de liste | Le plus court | Une inversion d'argument, une clé mal passée, un cache partagé indexé par une clé mal calculée : la liste de blocage devient liste d'autorisation |
| **B — deux chemins distincts, un moteur commun** | Le moteur est partagé ; les politiques, les stockages et les caches ne le sont pas | Une confusion exige une erreur dans deux fichiers à la fois |
| C — duplication complète, moteur compris | Aucun partage | Deux moteurs qui divergent : la grammaire se met à différer entre les deux listes, ce qui est un autre défaut grave |

## Décision

**Option B**, formalisée par trois règles.

| Réf. | Règle |
|---|---|
| A-01 | Les deux listes ne partagent jamais un stockage, un cache ni un chemin de décision. `Policy\Access_Policy` et `Policy\Registration_Policy` sont deux classes distinctes, qui n'ont pas de parent commun et ne s'appellent pas |
| A-02 | Toute règle de la liste d'autorisation est validée **à l'écriture** avec la même sévérité qu'à la lecture, et une règle trop couvrante (G-05) est refusée à l'écriture |
| A-03 | Une entrée rejetée dans la liste de blocage produit un avis d'administration **persistant**, parce que son échec est silencieux |

Le moteur — `Rule\Rule_Parser` et `Rule\Matcher` — reste commun : il répond à la
question « cette adresse correspond-elle à cette règle », qui est la même des
deux côtés et n'accorde ni ne refuse rien par elle-même.

## Justification

**La ligne de partage est celle de la décision, pas celle du calcul.** Un
moteur qui calcule une correspondance est neutre : il n'a pas de sens
d'autorisation. Une politique qui traduit une correspondance en accès, ou en
refus d'inscription, en a un. Dupliquer le moteur ferait diverger la grammaire ;
partager la politique ferait confondre les sens.

**A-02 corrige une asymétrie de vigilance.** Valider seulement à la lecture
paraît suffisant, puisque la fermeture sur l'inconnu (§4.5) protège de toute
façon. Mais la protection est asymétrique : sur la liste d'autorisation, la
fermeture refuse un accès, ce que quelqu'un finit par signaler. Sur la liste de
blocage, elle n'applique pas un blocage, ce que personne ne signale jamais.
Valider à l'écriture est le seul moment où l'administrateur est présent pour
apprendre son erreur.

**A-03 compense l'absence de signal.** Un avis éphémère, affiché une fois après
l'enregistrement, ne compense rien : le réglage fautif survit à l'avis. L'avis
persiste tant que l'entrée est rejetée.

## Conséquences

| Positive | Négative | Mitigation |
|---|---|---|
| Une confusion de liste exige deux erreurs indépendantes | Un peu plus de code que la version générique | Le moteur, qui est la partie volumineuse, reste unique |
| Une entrée fautive est signalée au moment où l'auteur peut la corriger | Une liste collée depuis un fichier externe peut produire plusieurs avis | Les avis sont agrégés en un seul, listant les lignes |
| Le test de non-confusion est écrivable et lisible | — | Suite `access` : une adresse en liste de blocage seule n'obtient aucun accès, et une adresse en liste d'autorisation seule n'est pas bloquée |

## Conditions de révision

Aucune. Cette décision ne se révise pas : elle formalise une propriété de
sécurité, pas un arbitrage technique.
