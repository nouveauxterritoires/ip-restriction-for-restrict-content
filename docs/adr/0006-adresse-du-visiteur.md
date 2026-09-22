# ADR-0006 — L'adresse évaluée est `REMOTE_ADDR`, sauf déclaration explicite de proxys de confiance

- **Statut** : accepté
- **Date** : 22 septembre 2026
- **Portée** : cahier des charges §4.7, exigences SEC-07 à SEC-09
- **Constats associés** : JE-005
- **Vérification associée** : V-02

## Contexte

Tout ce plugin repose sur une question : **quelle est l'adresse du visiteur ?**
Une liste d'autorisation évaluée sur une mauvaise réponse n'est pas un contrôle
d'accès dégradé — c'est un contournement d'authentification.

Les valeurs disponibles ne se valent pas :

| Valeur | Origine | Falsifiable par le client |
|---|---|---|
| `$_SERVER['REMOTE_ADDR']` | Renseignée par le serveur web depuis la connexion TCP | **Non** |
| `$_SERVER['HTTP_X_FORWARDED_FOR']` | En-tête HTTP de la requête | **Oui** |
| `$_SERVER['HTTP_CLIENT_IP']` | En-tête HTTP de la requête | **Oui** |
| `$_SERVER['HTTP_CF_CONNECTING_IP']` et analogues | En-tête HTTP de la requête | **Oui** |

La dépendance, elle, fait le mauvais choix : `rcp_get_ip()`
(`core/includes/misc-functions.php:731`) retient `HTTP_CLIENT_IP` en premier,
puis le **premier** élément de `HTTP_X_FORWARDED_FOR`, et ne tombe sur
`REMOTE_ADDR` qu'en dernier recours (JE-005).

La documentation de l'add-on de référence ne dit rien du comportement derrière
un proxy ou un CDN, et lire son code est exclu (V-02).

## Options envisagées

| Option | Site sans proxy | Site derrière CDN | Contournement |
|---|---|---|---|
| A — réutiliser `rcp_get_ip()` | Adresse contrôlée par le visiteur | Fonctionne par accident | **Trivial** : une ligne d'en-tête |
| B — `REMOTE_ADDR` seul, toujours | Correct | Inutilisable : tous les visiteurs ont l'adresse du CDN | Impossible |
| **C — `REMOTE_ADDR` par défaut, en-tête sur déclaration explicite de proxys de confiance** | Correct | Fonctionne une fois configuré | Impossible tant que la liste de proxys est juste |
| D — comme C, mais découverte automatique des proxys | Correct | Fonctionne sans configuration | Selon la méthode de découverte ; aucune n'est fiable |

## Décision

**Option C**, avec les sept règles R-01 à R-07 du cahier des charges §4.7. Les
trois qui portent la sécurité :

| Règle | Énoncé |
|---|---|
| R-01 | Par défaut, l'adresse évaluée est `REMOTE_ADDR`, et rien d'autre |
| R-03 | Un en-tête n'est lu que si `REMOTE_ADDR` correspond à un proxy déclaré |
| R-04 | L'en-tête est lu **de droite à gauche**, en sautant les proxys déclarés |

Et deux règles de non-interférence :

| Règle | Énoncé |
|---|---|
| R-05 | Une valeur non analysable produit une adresse **absente**, jamais un repli arbitraire |
| R-06 | Le plugin n'utilise pas `rcp_get_ip()` et ne filtre pas `rcp_get_ip` |

## Justification

**Option A exclue par ce qu'elle rend possible.** Un visiteur qui devine une
adresse autorisée — et sur un site d'entreprise, la plage du siège se devine —
obtient l'accès complet au contenu payant en ajoutant un en-tête. Sans compte,
sans trace distinctive, et à volonté.

**Option B exclue par ce qu'elle rend impossible.** Derrière un CDN, tous les
visiteurs se présentent avec l'adresse du CDN : soit aucune règle ne
correspond, soit une règle sur l'adresse du CDN ouvre le site à tout l'internet.

**Option D exclue parce que la découverte automatique n'est pas fiable.** Un
proxy ne se distingue pas d'un client par sa seule adresse. Toute heuristique de
découverte est une heuristique de sécurité, c'est-à-dire un contournement en
attente.

**Pourquoi la lecture de droite à gauche.** `X-Forwarded-For` s'accroît par la
gauche à chaque saut de proxy. Un client qui envoie déjà
`X-Forwarded-For: 192.0.2.10` fait arriver au serveur
`192.0.2.10, <sa vraie adresse>` : lire le premier élément, comme le fait
`rcp_get_ip()`, retient la valeur injectée. Remonter depuis la droite en sautant
les proxys déclarés retient la dernière valeur qu'aucun proxy de confiance n'a
pu inventer. C'est la seule lecture sûre, et elle n'est sûre que parce que la
liste de proxys est déclarée.

**Pourquoi ne pas filtrer `rcp_get_ip`.** Corriger un comportement de la
dépendance par un filtre global changerait le comportement de toute autre
extension qui l'appelle, sans qu'elle le sache et sans qu'elle l'ait demandé.
Une telle correction dépasse le périmètre de ce plugin et crée une dépendance
invisible entre extensions. Le plugin résout l'adresse pour lui-même.

## Conséquences

| Positive | Négative | Mitigation |
|---|---|---|
| Aucun contournement par en-tête sur une installation par défaut | Un site derrière CDN ne fonctionne pas tant qu'il n'est pas configuré | L'écran de diagnostic de FNC-29 affiche l'adresse retenue et sa source : l'administrateur voit immédiatement que c'est l'adresse du CDN |
| La configuration de proxy est explicite et relisible | Une liste de proxys erronée casse la résolution | La défaillance va vers le refus d'accès (§4.5), donc elle est visible |
| Le comportement de la dépendance n'est pas modifié pour les tiers | Deux résolutions d'adresse coexistent sur le site | Documenté ; les deux ne servent pas au même usage, et seule la nôtre décide d'un accès |
| Onze scénarios d'attaque testables, tous purs | — | — |

## Conditions de révision

Si la dépendance corrigeait `rcp_get_ip()` pour exiger une déclaration de
proxys, la question de la réutiliser se reposerait. La réponse resterait
probablement négative : ce plugin a besoin de connaître la **source** de
l'adresse (R-07), et une fonction qui renvoie une chaîne ne la donne pas.
