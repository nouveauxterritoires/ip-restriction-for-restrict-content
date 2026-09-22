# Stratégie de test

Référence : cahier des charges §11. Ce document détaille ce que chaque suite
doit établir, les fixtures nécessaires et les seuils.

**État au lot 0** : les suites `unit` et `integration` sont peuplées ; les
suites `contract`, `matching` et `access` sont déclarées et vides. Une suite
vide sort en succès (JE-001) : elles ne sont donc pas encore déclarées comme
jobs d'intégration continue, et leur verdict actuel ne vaut rien.

---

## 1. Le cycle

Non négociable : écrire le test, **le voir échouer**, écrire l'implémentation,
le voir passer, refactorer. « Le voir échouer » n'est pas une formalité : un
test qui passe du premier coup n'a pas prouvé qu'il teste quelque chose.

Chaque exigence numérotée du cahier des charges — `FNC-xx`, `SEC-xx`, `G-xx`,
`R-xx`, `A-xx`, `D-xx` — a au moins un test, et le nom du test cite la
référence.

---

## 2. Les cinq suites

Les suites **ne partagent pas de processus**. La suite `unit` remplace les
fonctions de WordPress par Brain Monkey, ce qui exige qu'aucune ne soit déjà
définie ; les autres chargent un WordPress complet. Les exécuter dans une même
invocation fait échouer Patchwork avec « DefinedTooEarly ».
`tests/bootstrap.php` refuse donc explicitement une invocation sans
`--testsuite`, et la couverture se fusionne a posteriori (`bin/coverage.sh`).

| Suite | Contexte | Durée visée | Commande |
|---|---|---|---|
| `unit` | WordPress mocké | < 1 s | `make test-unit` |
| `integration` | WordPress réel | quelques secondes | `make test-integration` |
| `contract` | Dépendance réelle | quelques secondes | `make test-contract`, `make test-contract-pro` |
| `matching` | WordPress réel | quelques secondes | `make test-matching` |
| `access` | WordPress réel | dizaines de secondes | `make test-access` |

### 2.1 `unit` — la logique pure et l'outillage

**Ce qu'elle doit établir.**

| Objet | Ce qui est établi |
|---|---|
| Grammaire et analyseur | Chaque forme acceptée produit la règle attendue ; chaque contrainte G-01 à G-07 produit un rejet, avec le motif attendu |
| Normalisation | Les quatre formes se ramènent au même intervalle borné ; les bornes sont celles du §4.3, y compris pour la plage à bornes jokers |
| Décision sur un ensemble | D-01 à D-05, dont : une règle invalide ne rend pas la liste invalide, et une liste entièrement invalide se comporte comme une liste vide |
| Immuabilité | Aucun objet de valeur n'a de setter ; aucune méthode ne mute un tableau reçu, vérifié par comparaison de l'original avant et après |
| Pureté | Un parcours de `src/Address/`, `src/Rule/` et `src/Policy/` échoue si une fonction de WordPress y est appelée |
| Hygiène | `defined( 'ABSPATH' ) || exit;` sur tout fichier livré (SEC-15) ; aucune édition manuelle de `stubs/` ; aucun `@phpstan-ignore` |
| Outillage | Le `Makefile` ne référence aucun script inexistant, et l'aide de chaque cible décrit ce qu'elle exécute réellement (§12.2) |
| Scanners | `bin/scan-secrets.sh` et `bin/scan-placeholders.sh` sont **exécutés**, pas relus : un scanner inerte passerait un test de lecture |

Déjà peuplée : `RequirementsNoticeTest`, `MakefileTest`,
`RepositoryHygieneTest`, `ScannersTest`, et la base commune `UnitTestCase`.

### 2.2 `integration` — le plugin dans un WordPress réel

**Ce qu'elle doit établir.** Non pas que le code existe, mais qu'il s'exécute.

| Objet | Ce qui est établi |
|---|---|
| Amorçage | Le plugin se charge, les constantes sont définies, la version de l'en-tête et `RCIPR_VERSION` coïncident |
| Détection | La dépendance est détectée par capacité, et la sonde conclut |
| Absence d'erreur | Un gestionnaire d'erreurs capture `E_ERROR`, `E_WARNING`, `E_NOTICE` et `E_DEPRECATED` pendant l'activation et le rendu ; l'émission d'une seule fait échouer le test |
| Écrans | Les écrans du §7 se rendent, et les réglages survivent à un aller-retour d'enregistrement |
| Absence de réseau | `pre_http_request` est bouchonné et échoue à la moindre tentative (SEC-16) |
| Non-mémorisation de la sonde | P-01 à P-04 : après amorçage, aucune option ni aucun transient n'a été écrit par le plugin ; puis l'option de branche de la dépendance est retournée, le plugin réamorcé, et l'avis de E-05 apparaît dès cette requête, sans réactivation |
| Scénarios d'activation | Les huit scénarios du cahier des charges §11.7, dont la bascule de branche en cours de vie |

Déjà peuplée : `BootTest`.

### 2.3 `contract` — les accroches existent et se déclenchent

C'est la suite la plus importante du lot 1, et la seule qui puisse établir
certains faits.

Un filtre ne se sonde pas par introspection : il est impossible de savoir qu'un
`apply_filters` existe dans un corps de fonction. Cette suite l'établit donc
**par l'exécution** : brancher un témoin, provoquer le chemin, constater que le
témoin a été appelé avec les arguments attendus.

**Son premier test ne porte sur aucune accroche.** Il vérifie que la branche
chargée est bien la branche moderne, et échoue avec un message qui nomme la
cause — branche historique chargée, API absente. Sans lui, un environnement mal
provisionné produit une cascade d'échecs dont aucun ne désigne la vraie cause :
les suites métier tombent plus loin, sur une fonction introuvable. Le constat
qui l'impose est JE-003, et il vaut aussi bien en environnement de test qu'en
production.

**Ce que ces tests ne sont plus.** Depuis que la source de la variante gratuite
est lisible en local, la présence du code des accroches est établie par lecture
directe, et non déduite d'un stub : le point ouvert V-07 est levé. Ces tests
changent donc de nature — ils ne comblent plus une inconnue, ils **surveillent**
une propriété établie. La distinction compte pour leur rédaction : le message
d'échec doit dire « l'amont a changé », et non « nous ne savions pas ».

| Accroche à établir | Chemin à provoquer | Source de l'attendu |
|---|---|---|
| `rcp_member_can_access`, visiteur déconnecté | Demander un contenu restreint sans être connecté | `core/includes/member-functions.php:101` |
| `rcp_member_can_access`, visiteur connecté avec client | Idem, connecté, avec un enregistrement client | `core/includes/customers/class-rcp-customer.php:772, 777, 786, 791, 806` |
| `rcp_member_can_access`, chemin historique | Passer par `RCP_Member::can_access()` | `core/includes/class-rcp-member.php:970, 975, 987` |
| Absence de filtre sur les termes | Appeler `rcp_user_can_access_term()` avec un témoin sur tout filtre plausible ; **attendu : aucun témoin appelé** | `core/includes/member-functions.php:134-175` |
| `rcp_get_term_restrictions` | Lire les restrictions d'un terme | `core/includes/misc-functions.php:998` |
| `rcp_form_errors` | Soumettre le formulaire d'inscription | `core/includes/registration-functions.php:148` |
| `rcp_errors()` collecte et renvoie | Ajouter une erreur et constater l'annulation de l'inscription | `core/includes/error-tracking.php:19`, `core/includes/registration-functions.php:151-166` |
| `rcp_edit_subscription_form`, `rcp_add_subscription_form` | Rendre les écrans de niveau | `core/includes/admin/subscriptions/edit-subscription.php:164`, `core/includes/admin/subscriptions/subscription-levels.php:195` |
| `rcp_edit_subscription_level`, `rcp_add_subscription` | Enregistrer un niveau | `core/includes/membership-levels/membership-level-functions.php:381, 278` |
| Métadonnées de niveau | Écrire puis relire une métadonnée de niveau | `core/includes/levels/meta.php:26, 69, 94` |
| `rcp_metabox_fields_before` / `_after`, `rcp_save_post_meta` | Rendre et enregistrer la boîte de restriction | `core/includes/admin/metabox.php:77, 81, 226` |
| Branche chargée | **Premier test de la suite.** Constater la présence des fonctions `rcp_*` du §5.1 et échouer avec un message nommant la branche historique si elles manquent | JE-003 |
| Capacité `rcp_manage_settings` | Vérifier qu'elle est accordée à l'administrateur | `core/includes/class-rcp-capabilities.php:53` |
| Bascule de branche | Retourner `restrict_content_chosen_version`, réamorcer, constater que la sonde conclut à l'absence — et qu'elle n'avait rien mémorisé | JE-003, P-01 à P-04 |

Le quatrième test de ce tableau est d'un genre particulier : il établit une
**absence**. C'est ce qui rend la contrainte du cahier des charges §5.1.1
opposable, et ce qui fera remonter immédiatement le jour où la dépendance
ajouterait enfin un filtre sur la décision de terme — auquel cas le test
échouera, et ce sera une bonne nouvelle à traiter, pas une régression.

**Les deux variantes.** La suite s'exécute contre Restrict Content
(`make test-contract`) et contre Restrict Content Pro
(`make test-contract-pro`). Le second job ne s'exécute en intégration continue
que si un secret de dépôt met l'archive commerciale à disposition, en
`continue-on-error` pour ne pas bloquer une contribution externe (§12.1). Un
déclencheur quotidien exécute la suite contre la dernière version publiée de la
variante gratuite : une rupture amont devient ainsi un incident diagnosticable
avant les utilisateurs.

### 2.4 `matching` — le moteur et la résolution d'adresse

**Ce qu'elle doit établir**, format par format :

| Formats | Cas exigés |
|---|---|
| Littéral IPv4, IPv6 | L'adresse exacte correspond ; l'adresse voisine ne correspond pas |
| Joker | Une adresse de l'intérieur correspond ; les deux extrémités correspondent ; l'adresse immédiatement hors bornes ne correspond pas |
| CIDR | Adresse de réseau, adresse de diffusion, adresse intérieure, adresse voisine hors bloc ; préfixes 0, 1, 31, 32 en IPv4 ; 0, 1, 127, 128 en IPv6 |
| Plage | Les deux bornes correspondent (caractère inclusif, FNC-15) ; les deux adresses immédiatement extérieures ne correspondent pas |
| Plage à bornes jokers | `149.58.*-149.60.*` accepte `149.58.0.0`, `149.59.1.1` et `149.60.255.255`, refuse `149.57.255.255` et `149.61.0.0` |
| Familles croisées | Règle IPv4 et visiteur IPv6 : aucune correspondance, aucune erreur ; et réciproquement |

Et, pour la résolution de l'adresse, les **onze scénarios d'attaque** du cahier
des charges §11.3. Chacun est un test nommé d'après son scénario. Ce n'est pas
une liste indicative : c'est la traduction de SEC-07 à SEC-09, et l'absence
d'un seul de ces tests laisse une voie de contournement non couverte.

### 2.5 `access` — le comportement observable

**Ce qu'elle doit établir**, sur un site réel, avec des niveaux et des contenus
réellement restreints :

| Objet | Cas exigés |
|---|---|
| Portée globale | Un visiteur déconnecté, depuis une adresse autorisée globalement, lit un contenu restreint à n'importe quel niveau |
| Portée par niveau | Autorisation sur « Argent » : le contenu réservé à « Argent » est lisible |
| Non-transitivité | **Le test central.** Autorisation sur « Argent », contenu réservé à « Or » : refusé |
| Cumul | Une seule portée satisfaite suffit ; deux portées satisfaites n'ont pas d'effet supplémentaire |
| Portée par contenu | Autorisation sur le contenu A : A est lisible, B ne l'est pas |
| Adhésion expirée | V-01 : autorisation et adhésion expirée, sur les deux chemins — avec client et sans client |
| Blocage d'inscription | La soumission est rejetée, et le message apparaît par le canal de la dépendance |
| Asymétrie | Une adresse en liste de blocage seule n'obtient **aucun** accès au contenu, et une adresse en liste d'autorisation seule n'est **pas** bloquée à l'inscription (A-01) |
| Entrée rejetée | Une entrée invalide dans la liste de blocage produit l'avis persistant (A-03, SEC-11) |
| Autorisation et capacités | Une adresse autorisée n'obtient aucune capacité WordPress ; l'administration reste fermée (SEC-13) |
| Nonce et capacité | Enregistrement des réglages sans nonce, avec nonce d'une autre action, sans capacité : refusé dans les trois cas (SEC-01) |
| Contournement par identifiant | Enregistrement de la liste d'un contenu avec l'identifiant d'une publication qu'on ne peut pas modifier : refusé (SEC-02) |
| Échappement | Un message de blocage porteur de balises est rendu inerte (SEC-03) |
| Cache | Une réponse élargie par une autorisation porte le marquage de non-cachabilité (SEC-14) |
| Réponse minimale | Le message de blocage ne contient ni l'adresse, ni le texte d'une règle (SEC-17) |

---

## 3. Fixtures réelles

Les charges utiles fabriquées à la main mentent. Une fixture synthétique est un
pis-aller temporaire, nommée `synthetic-*`, remplacée dès qu'une capture est
possible.

| Fixture | Ce qu'elle établit | Capture |
|---|---|---|
| `server/*.json` | La forme réelle des variables `$_SERVER` derrière un proxy : ordre des en-têtes, espaces, ports accolés aux adresses IPv6 | Site derrière CDN ; à défaut `synthetic-*` |
| `registration/*.json` | La forme réelle de la charge `$_POST` du formulaire d'inscription et le canal de retour des erreurs | Environnement Docker du dépôt |
| `levels/*.json` | Les clés et les types réels d'un niveau d'adhésion et de ses métadonnées, dans les deux variantes | Environnement Docker |
| `lists/*.txt` | Le comportement de l'analyseur sur des listes de taille réelle, avec leurs fautes de frappe réelles | Listes de production anonymisées |

**Règle d'anonymisation.** Aucune fixture ne contient d'adresse réellement
attribuée. Les exemples utilisent les plages réservées à la documentation :
`192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24` (RFC 5737) et
`2001:db8::/32` (RFC 3849). Les adresses des exemples de la documentation amont
sont conservées telles quelles dans le seul cahier des charges, parce que ce
sont des citations.

**Règle d'adresses de courriel.** `bin/scan-secrets.sh` n'admet dans l'outillage
et les tests que les domaines réservés par la RFC 2606. Toute fixture qui porte
une adresse de courriel utilise `example.test`, `example.com`, `example.org`,
`example.net` ou `.invalid`.

---

## 4. Interdits

| Interdit | Pourquoi |
|---|---|
| `markTestSkipped`, `markTestIncomplete` | Un test qui ne s'exécute pas masque toujours une faiblesse de conception. Rendre le test exécutable partout, au besoin en lisant la configuration depuis l'environnement plutôt que depuis un fichier local |
| `.only` | Idem |
| `TODO:`, `FIXME:`, `XXX:` | Une question ouverte se note au cahier des charges §15 |
| Branche non implémentée | Un chemin qui ne fait rien est indistinguable d'un chemin correct, jusqu'à l'incident |
| `@phpstan-ignore` | Écrire le stub manquant ou corriger la cause |

Vérifiés par `bin/scan-placeholders.sh`, bloquant en intégration continue, et
couvert par `tests/unit/Tooling/ScannersTest.php`, qui **exécute** le scanner.

---

## 5. Seuils

| Périmètre | Plancher | Contrôle |
|---|---|---|
| `src/` entier | 80 % des lignes | `bin/check-coverage.sh` sur un Clover fusionné |
| `src/Address/`, `src/Rule/`, `src/Policy/` | 100 % des lignes et des branches | Même script, plancher propre au périmètre |
| D'une version à l'autre | Aucune régression | `.coverage-baseline` |

Le plancher renforcé porte sur les trois répertoires purs, et sur eux seuls. Ce
sont les seuls composants dont un défaut se traduit directement par un accès
accordé à qui ne devait pas l'avoir, et les seuls où 100 % soit atteignable sans
contorsion, parce qu'ils n'ont ni environnement ni effet de bord.

La couverture ne se mesure jamais suite par suite : une suite ciblée affiche un
taux partiel trompeur. `phpunit.xml.dist` ne demande donc aucun rapport par
défaut ; `make coverage` mesure chaque suite et fusionne.

---

## 6. Ce qu'un test ne doit jamais faire

| Interdit | Motif |
|---|---|
| Asserter qu'une chaîne de code source contient un motif, à la place d'exécuter | `ScannersTest` exécute les scanners précisément pour cette raison : un scanner inerte passerait toute lecture |
| Assouplir un test qui échoue avant d'avoir cherché ce qu'il révèle | Un test qui échoue est une information |
| Dépendre de l'ordre des tests, ou d'un état laissé par un test précédent | Les suites sont rejouées isolément en diagnostic |
| Mémoriser l'état de la dépendance entre deux tests pour aller plus vite | Ce serait reproduire dans les tests exactement le défaut que P-01 interdit dans le code |
| Utiliser une adresse réellement attribuée | §3 |
| Bouchonner le moteur de correspondance dans la suite `access` | La suite `access` doit établir le comportement observable réel, moteur compris |
