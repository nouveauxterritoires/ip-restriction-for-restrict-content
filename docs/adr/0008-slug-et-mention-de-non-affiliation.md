# ADR-0008 — Conserver le slug, et maintenir la mention de non-affiliation

- **Statut** : accepté
- **Date** : 22 septembre 2026
- **Portée** : slug, text domain, namespace, préfixes, en-tête du plugin, `readme.txt`
- **Point ouvert clos** : O-03

## Contexte

Le plugin s'appelle `ip-restriction-for-restrict-content`, et son nom mentionne
« Restrict Content », marque d'un tiers. La publication sur le répertoire
WordPress.org est envisagée, ce qui rend la directive 17 opposable.

**Ce que dit la directive 17.** Elle interdit qu'un slug **commence** par la
marque d'autrui, et recommande la forme « fonction pour marque ». Elle
n'interdit pas la mention de la marque après le « for ».

Le slug retenu commence par `ip-restriction`, qui est une **fonction
générique** — la restriction par adresse IP —, suivie de `for-restrict-content`.
Il est donc déjà dans la forme recommandée.

Cette décision s'inscrit dans une série de sept plugins, arbitrés ensemble :

| Sort | Plugins |
|---|---|
| **Conformes, conservés** | `ip-restriction-for-restrict-content` (celui-ci), `custom-redirects-for-restrict-content`, `rest-api-for-restrict-content`, `coupons-for-restrict-content` |
| **Commençaient par une marque, renommés** | `help-scout-…` → `support-sidebar-for-restrict-content` ; `mailchimp-…` → `audience-sync-for-restrict-content` ; `brevo-…` → `contact-sync-for-restrict-content` |

La distinction entre les deux groupes est exactement celle que pose la
directive : ce qui est en tête du slug.

## Options envisagées

| Option | Conformité à la directive 17 | Découvrabilité | Coût |
|---|---|---|---|
| **A — conserver `ip-restriction-for-restrict-content`** | Conforme : le slug commence par une fonction générique | Bonne : la recherche « restrict content ip » aboutit | Nul |
| B — renommer sans la marque, par exemple `ip-access-rules-for-memberships` | Conforme, trivialement | Faible : rien ne relie le plugin à la dépendance dans une recherche | Élevé — le slug conditionne le text domain, le namespace `Ip_Restriction_For_Restrict_Content\` et la forme longue du préfixe (ADR-0001) |
| C — conserver, et demander une autorisation écrite à l'éditeur avant soumission | Conforme de toute façon | Bonne | Dépend d'un tiers, pour lever un risque que la directive ne pose pas |

## Décision

**Option A : le slug est conservé.** Aucune modification n'est apportée au slug,
au text domain, au namespace ni aux préfixes.

**Et la mention de non-affiliation est maintenue.** Elle figure dans la
description de l'en-tête du plugin
(`ip-restriction-for-restrict-content.php`, ligne 5) et en tête du `README.md` :

> Extension indépendante, ni développée ni approuvée par les éditeurs de
> Restrict Content ou de Restrict Content Pro.

## Justification

**Pourquoi conserver.** La directive vise l'appropriation d'une marque en tête
de slug, qui laisse croire à une publication officielle. « ip-restriction »
n'est la marque de personne. La forme « fonction pour marque » est celle que la
directive recommande, et renommer reviendrait à s'écarter de la recommandation
pour se prémunir d'une règle qui ne s'applique pas — au prix de la
découvrabilité, qui est la seule raison de mentionner la dépendance.

**Pourquoi la mention de non-affiliation reste nécessaire, et ne se confond pas
avec la conformité du slug.** C'est le point que ce document existe surtout pour
fixer, parce que la confusion est facile et la suppression tentante.

| | Conformité du slug | Mention de non-affiliation |
|---|---|---|
| Ce qu'elle traite | La **forme** du nom : ce qui est en tête | L'**impression** produite : qui publie ce plugin |
| Ce qu'elle empêche | Qu'un slug s'approprie la marque d'autrui | Qu'un utilisateur croie acheter un produit officiel de l'éditeur |
| Qui l'impose | Directive 17 du répertoire | Le droit des marques, et l'honnêteté envers l'utilisateur |
| Où elle s'exerce | Le slug, une seule fois | La description, le `readme.txt`, les écrans d'administration |
| Satisfaite par l'autre ? | **Non** | **Non** |

Un slug conforme n'informe personne : il ne dit pas qui édite le plugin. Une
mention de non-affiliation, elle, ne rend pas conforme un slug qui commencerait
par une marque. Les deux exigences sont indépendantes, et satisfaire l'une ne
dispense pas de l'autre.

**Conséquence pratique** : un successeur qui constaterait que le slug est
conforme ne doit **pas** en conclure que la mention est superflue. Et
réciproquement, un successeur qui ajouterait la mention à un slug non conforme
n'aurait rien réglé.

## Conséquences

| Positive | Négative | Mitigation |
|---|---|---|
| Aucun renommage : le text domain, le namespace, les préfixes et les chaînes traduites restent stables | Le nom dépend d'une marque que ce projet ne contrôle pas | Conditions de révision ci-dessous |
| Découvrabilité conservée : le plugin se trouve en cherchant la dépendance | — | — |
| ADR-0001 n'est pas rouvert | — | — |
| O-03 est clos, et le lot 10 n'a plus de décision de nommage en attente | — | — |

## Conditions de révision

Deux événements rouvrent la question, et un seul suffit :

| Événement | Ce qu'il impose |
|---|---|
| **Changement de nom du produit amont** — il est déjà partiellement engagé : l'en-tête de la variante gratuite 4.0.4 annonce `Plugin Name: Kadence Memberships` (JE-003) | Le slug désignerait alors un produit qui ne s'appelle plus ainsi. La question devient celle de la découvrabilité, pas de la conformité |
| **Réclamation de l'éditeur** (Kadence) | Renommage, selon l'option B, avec la migration de text domain, de namespace et de préfixes que cela entraîne |

Dans les deux cas, le coût est celui d'ADR-0001 : le slug conditionne le text
domain, le namespace et la forme longue du préfixe. C'est pourquoi ces deux
conditions sont écrites ici, plutôt que laissées à l'appréciation du moment.
