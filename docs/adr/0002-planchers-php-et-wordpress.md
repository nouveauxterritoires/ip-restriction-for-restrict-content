# ADR-0002 — Planchers PHP 8.1 et WordPress 6.4

- **Statut** : accepté
- **Date** : 22 septembre 2026
- **Portée** : en-tête du plugin, `composer.json`, `phpcs.xml.dist`, matrice d'intégration continue
- **Point ouvert associé** : O-02

## Contexte

Un plancher de version est un arbitrage entre la part du parc que l'on couvre et
ce que l'on peut écrire. Il est déclaré à quatre endroits qui doivent
s'accorder : `Requires PHP` et `Requires at least` dans l'en-tête du plugin,
`require.php` dans `composer.json`, `testVersion` dans `phpcs.xml.dist`, et la
matrice des jobs d'intégration continue.

## Options envisagées

### PHP

| Option | Ce qu'elle permet | Ce qu'elle coûte |
|---|---|---|
| 7.4 | Couvre le parc le plus large | Ni types d'union, ni propriétés promues, ni énumérations, ni `readonly`. Sortie de support de sécurité depuis 2022 |
| 8.0 | Types d'union, propriétés promues | Ni énumérations, ni `readonly`, ni `never`. Sortie de support de sécurité |
| **8.1** | Énumérations, `readonly`, `never`, `array_is_list()` | Exclut un parc résiduel |
| 8.2 | `readonly` sur les classes entières | Exclut un parc encore significatif, sans gain déterminant ici |

### WordPress

| Option | Ce qu'elle permet | Ce qu'elle coûte |
|---|---|---|
| 5.9 | Couvre très large | Antérieure à plusieurs correctifs d'échappement et d'API d'options |
| 6.0 à 6.3 | — | Aucun gain net par rapport à 6.4 sur les API utilisées ici |
| **6.4** | Parc majoritaire et à jour, API d'administration stabilisée | Exclut les sites très en retard, qui ont d'autres problèmes |

## Décision

**PHP 8.1 et WordPress 6.4.**

## Justification

**PHP 8.1 pour les énumérations et `readonly`.** Ce plugin repose entièrement
sur des objets de valeur immuables (`Client_Address`, `Rule`, `Match_Result`,
`Access_Decision`) et sur des ensembles fermés de valeurs (`Address_Source`,
`Rule_Family`, `Scope`). En 8.0, chacun de ces ensembles s'écrirait comme une
classe de constantes de chaîne, dont rien ne garantirait l'exhaustivité au
moment de la comparaison ; et l'immuabilité reposerait sur une discipline de
relecture plutôt que sur le langage.

Ce n'est pas une préférence esthétique. Dans un plugin dont un défaut de
décision accorde un accès non autorisé, la différence entre « une valeur hors
ensemble est impossible » et « une valeur hors ensemble tombe dans le `else` »
est une différence de classe de bug.

**WordPress 6.4 parce que rien en dessous n'apporte de gain.** Les API utilisées
— options, métadonnées de publication, avis d'administration, boîtes de
métadonnées — sont stables depuis longtemps. Descendre le plancher élargirait la
matrice de test sans élargir la couverture réelle : les sites concernés ne
pourraient de toute façon pas exécuter PHP 8.1.

**PHP est le plancher contraignant, pas WordPress.** Un site sous WordPress 6.4
mais sous PHP 8.0 est refusé par le premier prérequis, et l'avis le nomme
(`src/Plugin.php`).

## Conséquences

| Positive | Négative | Mitigation |
|---|---|---|
| Immuabilité garantie par le langage | Une part du parc est exclue | L'avis de prérequis nomme la version requise **et** la version installée, ce qui rend l'action évidente |
| Ensembles de valeurs exhaustifs, vérifiés par PHPStan | — | — |
| Matrice d'intégration continue réduite | Une régression propre à PHP 8.4 passerait inaperçue | La matrice couvre 8.1 à la dernière version stable |

## Conditions de révision

Le plancher PHP remonte lorsque 8.1 sort du support de sécurité, et pas avant :
un plancher qui monte par confort casse des sites en état de marche. Le plancher
WordPress remonte si une API utilisée le rend nécessaire, jamais par principe.
