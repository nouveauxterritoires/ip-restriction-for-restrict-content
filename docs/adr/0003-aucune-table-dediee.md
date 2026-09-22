# ADR-0003 — Aucune table dédiée

- **Statut** : accepté
- **Date** : 22 septembre 2026
- **Portée** : modèle de données, cahier des charges §6
- **Point ouvert associé** : O-04 pour le stockage du réglage de niveau

## Contexte

Le projet de référence crée quatre tables. La question se pose donc : ce plugin
en a-t-il besoin ?

Ce qu'il doit persister tient en trois objets : une liste d'autorisation
globale et une liste de blocage, une liste d'autorisation par niveau
d'adhésion, une liste d'autorisation par contenu. Rien d'autre — pas de compte
rendu, pas de journal, pas d'état par visiteur.

## Options envisagées

| Option | Volume | Requêtage | Intégrité référentielle | Coût |
|---|---|---|---|---|
| A — table `{$wpdb->prefix}rcipr_rules`, une ligne par règle | Quelques dizaines à quelques milliers de lignes | Possible et rapide | À écrire à la main | Schéma, migrations, nettoyage à la suppression d'un niveau ou d'un contenu, désinstallation |
| **B — option + métadonnées de niveau + métadonnées de publication** | Trois clés + une par niveau et par contenu configuré | Inutile | Fournie par WordPress et par la dépendance | Aucun schéma, aucune migration de table |
| C — une option unique contenant tout, y compris les listes de niveau et de contenu | Une clé | Inutile | Aucune | Nettoyage manuel à la suppression d'un niveau ou d'un contenu ; option qui grossit sans borne |

## Décision

**Option B.**

| Donnée | Support |
|---|---|
| Liste globale, liste de blocage, message, proxys, en-tête | Option `ip_restriction_for_restrict_content_settings` |
| Liste d'un niveau d'adhésion | Métadonnée de niveau (ADR-0004) |
| Liste d'un contenu | Métadonnée de publication `_rcipr_ip_allow_list` |

## Justification

**Une table se justifie par un besoin de requêtage ou de volume.** Il n'y en a
aucun ici. Le plugin ne demande jamais « quelles règles correspondent à cette
adresse » à la base : il charge la liste pertinente pour la portée en cours et
l'évalue en mémoire. Une liste de mille entrées s'évalue en une fraction de
milliseconde, parce que chaque règle se ramène à une comparaison d'entiers
(cahier des charges §4.3).

**L'intégrité référentielle est le vrai argument.** Avec une table, la
suppression d'un niveau d'adhésion ou d'un contenu laisse des règles
orphelines, qu'il faut nettoyer par des accroches sur des actions de
suppression — accroches qu'il est facile d'oublier, et dont l'oubli produit
exactement le défaut le plus dangereux de ce plugin : **une règle
d'autorisation qui survit à son objet**. Avec des métadonnées, la suppression
emporte le réglage sans écrire une ligne de code.

**L'option unique du choix C** reproduit le même défaut, en pire : rien ne
signale qu'une entrée correspond à un niveau supprimé, et l'option grossit
indéfiniment.

## Conséquences

| Positive | Négative | Mitigation |
|---|---|---|
| Aucun schéma, aucune migration de table, aucune version de schéma à tenir | Impossible de répondre à « quels contenus sont ouverts à cette adresse » par une requête | L'écran de diagnostic de FNC-29 répond à la question inverse, qui est celle qu'on se pose réellement : quelle adresse est évaluée, et quelle règle s'applique |
| Suppression d'un niveau ou d'un contenu : le réglage disparaît avec lui | — | — |
| Désinstallation simple, et révocation d'accès garantie par la seule absence du plugin | Les métadonnées orphelines d'un contenu restauré depuis la corbeille reviennent avec lui | C'est le comportement attendu : restaurer un contenu restaure son réglage d'accès |
| Aucun `$wpdb` dans le code livré, donc SEC-04 se vérifie par un simple parcours | — | — |

## Conditions de révision

Si un besoin d'audit apparaît — savoir qui a accédé à quoi par quelle règle —,
il ne se traitera pas en ajoutant une table à ce plugin, mais par le point
d'extension `ip_restriction_for_restrict_content_access_granted`, que
l'exploitant branche sur son propre journal. Une table de journal dans ce plugin
signifierait persister des adresses de visiteurs, ce que le cahier des
charges §6.5 exclut.
