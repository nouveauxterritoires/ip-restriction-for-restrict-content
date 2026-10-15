# Journal des modifications

Format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Versionnage [sémantique](https://semver.org/lang/fr/).

## [Non publié]

Lot 6 : blocage d'inscription. **Les deux fonctions du plugin existent
désormais à la portée globale** : une adresse de la liste d'autorisation accède
au contenu restreint, une adresse de la liste de blocage ne peut pas s'inscrire.

### Ajouté

- Branchement du blocage sur `rcp_form_errors`, et non sur
  `rcp_before_form_errors` : la première accroche est atteinte **après** la
  validation du niveau d'adhésion, et s'accrocher avant ferait porter au plugin
  un refus que la dépendance aurait de toute façon prononcé, en masquant la
  vraie cause à l'utilisateur.
- `Policy\Registration_Policy` et `Registration_Decision`, **distinctes** de
  celles de l'accès. A-01 interdit tout chemin commun entre les deux listes :
  deux classes, deux objets de décision, deux branchements, deux instances.
  Aucune ne reçoit la liste de l'autre, et un test l'établit sur les signatures.
- L'asymétrie de A-01 est vérifiée dans les **deux** sens : une adresse bloquée
  n'obtient ni plus ni moins d'accès au contenu que ce que la dépendance lui
  accorde (FNC-08), et une adresse de la liste d'autorisation n'est pas bloquée
  à l'inscription. C'est le test qui attraperait une confusion de champ.
- Le blocage ne touche pas l'authentification (FNC-09) : un compte existant se
  connecte depuis une adresse bloquée, et un test l'établit par
  `wp_authenticate()`.
- Message par défaut traduisible, message personnalisé rendu tel quel
  (FNC-10, I18N-04) : traduire la chaîne d'un administrateur reviendrait à la
  réécrire.
- Le message ne révèle ni l'adresse, ni la règle, ni l'existence d'une liste, et
  il est **identique quelle que soit la règle qui a filtré** (FNC-11, SEC-17) :
  un message qui varierait permettrait de deviner le contenu de la liste par
  essais successifs.
- Points d'extension du blocage : filtre `registration_blocked`, filtre
  `blocked_message` dont le retour est échappé **après** le filtre, et action
  `registration_block_applied` qui transporte une adresse expurgée.
- Stubs de la dépendance générés et versionnés (`stubs/`, 35 227 lignes) : c'est
  le premier lot où `src/` appelle une fonction de la dépendance, et l'analyse
  statique doit pouvoir la connaître. Ils sont produits par
  `bin/generate-stubs.sh` depuis l'installation réelle et jamais édités à la
  main (§12.4) ; l'archive distribuable ne les embarque pas.

### Corrigé

- Le filtre et l'action du blocage portaient le **même nom** dans le cahier des
  charges §8.1 et §8.2. WordPress partage un seul registre entre actions et
  filtres : un même nom ferait appeler les greffons du filtre par l'action, avec
  le mauvais nombre d'arguments, et réciproquement. L'action est renommée
  `..._registration_block_applied`, et la correction est consignée au §8.2.

### Documentation

- JE-023 : la dépendance interpole les messages d'erreur du formulaire
  d'inscription **sans les échapper**
  (`core/includes/error-tracking.php:47`). Notre échappement n'est donc pas une
  précaution redondante, c'est la seule barrière — et c'est pourquoi il est fait
  après le filtre, un filtre étant du code tiers.
- JE-024 : `rcp_errors()` est un `WP_Error` statique, partagé par tout le
  processus. Sept tests échouaient en lisant le message laissé par leurs
  prédécesseurs, tous avec le même symptôme trompeur — le message par défaut là
  où on attendait autre chose. Le §6 de `docs/tests.md` disait de ne pas
  dépendre de l'état laissé par un test précédent ; il faut lire aussi « ni de
  celui que la dépendance a gardé ».

---

Lot 5 : autorisation globale. **Le plugin accorde ses premiers accès** : une
adresse inscrite dans la liste d'autorisation globale accède à tout contenu
restreint du site, qu'elle soit connectée ou non.

### Ajouté

- Branchement de la décision sur `rcp_member_can_access`, la seule accroche que
  la dépendance applique sur les trois chemins de sa décision (§5.1). Le
  branchement n'a donc pas à choisir lequel sera emprunté : il répond de la même
  façon d'où qu'il vienne, et la suite `contract` du lot 1 établit les trois.
- **Le plugin n'accorde que.** Si la dépendance accorde déjà, il n'y a rien à
  ajouter ; si elle refuse, le plugin accorde ou ne se prononce pas, jamais ne
  retire. C'est FNC-31, et c'est ce qui garantit qu'un visiteur hors liste
  obtienne exactement le comportement de la dépendance seule. La décision est
  nommée « non traité » et non « refusé », pour que personne ne traduise un jour
  ce refus en blocage réel.
- `Policy\Access_Policy` ne reçoit ni utilisateur, ni adhésion, ni contenu :
  elle ne **peut** donc pas en dépendre. C'est la forme la plus solide que
  puisse prendre FNC-05, et un test l'établit sur la signature de la méthode.
- Marquage de non-cachabilité dès qu'une autorisation s'applique, et seulement
  alors (SEC-14). Un site qui met ses pages en cache et qui sert à un visiteur
  autorisé la version complète d'un contenu restreint l'expose à tous les
  visiteurs suivants : le contournement se propage sans qu'aucune liste ne le
  décide, et il survit à la correction de la liste jusqu'à l'expiration du
  cache. Marquer toutes les réponses, à l'inverse, coûterait le cache du site
  entier — quatre tests établissent que seules les réponses élargies le sont.
- SEC-13 : une autorisation accorde la lecture du contenu et **rien d'autre**.
  `current_user_can()` est inchangé pour quatre capacités, le visiteur n'est pas
  connecté, et aucun client ni adhésion n'est créé.
- Points d'extension du §8 sur le chemin d'accès — `client_address`,
  `trusted_proxies`, `rules`, `access_decision` et l'action `access_granted` —,
  avec leurs noms déclarés en constantes dans `Support\Extension_Points` et
  nulle part ailleurs : un nom écrit en clair à deux endroits finit par diverger
  d'un caractère, et le filtre cesse alors silencieusement d'être appelé.
- La contrainte de sécurité du §8 est vérifiée par trois tests : un filtre peut
  **retirer** un octroi, il ne peut pas en **créer**, et une valeur de retour
  qui n'est pas une décision retire l'octroi plutôt que de le laisser passer.
  Sans cette contrainte, n'importe quel plugin tiers deviendrait un fournisseur
  d'accès au contenu payant, hors de portée de l'administrateur qui a rempli les
  listes.
- L'action d'octroi transporte une adresse **expurgée** (SEC-06).
- `.coverage-baseline` passe de 99,28 % à 99,33 %.

### Levé

- Point ouvert V-01 : une adresse autorisée l'emporte bien sur une adhésion
  **expirée** (JE-022). V-01 prévoyait explicitement l'échec — « si le filtre
  n'est pas atteint sur ce chemin, l'attendu est révisé, pas contourné » — et le
  scénario joué dans la suite `access` confirme l'attendu :
  `RCP_Customer::can_access()` applique `rcp_member_can_access` sur son
  résultat, donc le filtre est atteint avec `false` comme sur le chemin
  déconnecté. Aucune contrainte de conception n'en découle.

### Documentation

- JE-022 : le constat sur l'adhésion expirée, et le détail d'écriture de test
  qui l'avait d'abord fait échouer — l'adresse est mémorisée par requête, et un
  test qui modifie `$_SERVER` en cours de route simule deux requêtes dans une
  seule.

---

Lot 4 : réglages globaux. Les listes se saisissent, se valident et se stockent ;
personne ne les consulte encore pour décider d'un accès — c'est le lot 5 qui
branchera la décision sur la dépendance.

### Ajouté

- Écran de réglages sous le menu de la dépendance, gardé par **sa** capacité
  `rcp_manage_settings` (FNC-24) : les deux listes, le message de blocage, les
  proxys de confiance, l'en-tête de transfert à lire parmi une liste fermée, et
  la suppression des réglages à la désinstallation.
- L'ordre des contrôles à l'enregistrement est celui qu'exige SEC-01 — **nonce
  d'abord, capacité ensuite** —, et un test l'établit pour lui-même : une
  requête à la fois sans droit et sans nonce est refusée pour le nonce. Le
  contrôle qui vaut pour tout le monde parle le premier ; vérifier la capacité
  d'abord apprendrait à un attaquant qu'il n'a pas le droit, là où il doit
  seulement apprendre que sa requête n'est pas valide.
- Validation à l'écriture par le **même analyseur** qu'à la lecture (A-02).
  Deux analyseurs, même écrits le même jour, finissent par ne plus dire la même
  chose — et celui de l'écriture serait le plus permissif, parce que personne ne
  le relit en pensant à la sécurité.
- Normalisation à l'enregistrement (FNC-28) : lignes ajustées, lignes vides
  retirées, doublons fusionnés, ordre d'origine conservé. Les entrées refusées,
  elles, sont **conservées telles quelles** (FNC-22) : refuser l'enregistrement
  entier ferait perdre les quarante lignes valides qu'on venait de coller à
  cause d'une faute de frappe sur la onzième.
- Avis **persistant** d'entrées refusées (SEC-11, A-03), nommant le champ, le
  numéro de ligne, l'entrée et le motif. Il se recalcule depuis les réglages
  stockés à chaque rendu plutôt que d'être mémorisé à l'enregistrement : il est
  donc persistant par construction, reste exact si la ligne a été écrite par un
  autre chemin, et disparaît dès qu'elle est corrigée. Les trois listes y
  figurent, celle de blocage comprise — son échec est silencieux.
- Un libellé par motif de refus, et un test qui vérifie qu'ils sont **distincts**
  et pas seulement présents : dix motifs traduits par la même phrase
  satisferaient la lettre de FNC-22 et en rateraient l'objet.
- Écran de diagnostic (FNC-29) : l'adresse retenue pour la requête en cours, sa
  provenance, et une phrase par raison d'indétermination. L'adresse y est
  complète et c'est délibéré — SEC-06 porte sur ce que le plugin **écrit**,
  alors que cet écran montre au lecteur sa propre adresse, le temps d'une
  requête, sous la capacité qui le garde. L'expurger contredirait l'objet même
  de FNC-29.
- Désinstallation conditionnelle (§6.6) : les réglages ne sont supprimés que si
  l'administrateur l'a demandé. Supprimer par défaut ferait perdre une
  configuration de plusieurs dizaines de lignes à qui désinstalle pour déboguer
  ou le temps d'une migration, et une liste d'adresses ne se retrouve pas de
  mémoire. Les comptes utilisateurs ne sont jamais touchés.
- SEC-04 : un test de la suite `unit` parcourt `src/` par analyse lexicale et
  échoue sur toute requête directe. L'analyse lexicale plutôt qu'une expression
  régulière, pour que `$wpdb` cité dans un commentaire — celui qui explique
  pourquoi on ne s'en sert pas — ne fasse pas échouer le test.
- La suite `access` est peuplée, et les **cinq** suites ont désormais leur job
  d'intégration continue.
- `.coverage-baseline` passe de 98,55 % à 99,28 %.

### Décidé

- Point ouvert O-08 : les réglages sont **propres à chaque site** d'un réseau
  (ADR-0009). C'est ce que le §11.7 supposait déjà, et une liste d'autorisation
  de réseau serait un amplificateur d'erreur — une règle trop couvrante saisie
  une fois ouvrirait le contenu restreint de tous les sites, et le §4.6 pose que
  cette erreur-là est silencieuse et durable. Un réseau qui veut la même liste
  partout la pose par le filtre du §8.1 : moins commode qu'un écran, et c'est la
  bonne commodité. La décision reste rétrocompatible avec un réglage de réseau
  servant plus tard de valeur par défaut.

---

Lot 3 : résolution de l'adresse du visiteur. Aucune fonctionnalité de
restriction n'est encore livrée — le plugin sait maintenant quelle adresse
évaluer, et personne ne lui demande encore de décider quoi que ce soit.

### Ajouté

- `Address\Client_Address_Resolver` : R-01 à R-07. Par défaut l'adresse évaluée
  est celle de la connexion TCP, et rien d'autre ; un en-tête de transfert n'est
  lu que si des proxys de confiance sont déclarés **et** que la connexion vient
  de l'un d'eux ; la chaîne est alors remontée de droite à gauche en sautant les
  proxys connus. Une liste d'autorisation évaluée sur une valeur que le visiteur
  contrôle n'est pas un contrôle d'accès dégradé, c'est un contournement
  d'authentification à une ligne d'en-tête.
- `Address\Client_Address` et `Address\Address_Source` : l'adresse retenue
  porte sa provenance — connexion, en-tête et rang, ou indéterminée — et la
  raison de son indétermination, que l'écran de diagnostic de FNC-29 affichera.
  Les deux accesseurs sont non nullables : une adresse indéterminable est une
  valeur, pas une absence.
- `Http\Server_Environment` : le seul fichier du plugin qui lise `$_SERVER`. La
  liste des en-têtes lisibles est fermée à quatre noms (§7.1) ; `Client-Ip`,
  celui auquel la dépendance fait confiance en premier, n'y est pas et n'est pas
  atteignable même écrit dans le réglage.
- `Support\Redactor` : expurgation avant toute écriture (SEC-06). Le dernier
  octet d'une adresse IPv4 et les 64 bits bas d'une adresse IPv6 sont masqués,
  le réseau est conservé — c'est lui qui sert au diagnostic, l'hôte est ce qui
  désigne une personne. Un test de propriété vérifie qu'aucune forme expurgée
  ne contient l'adresse complète.
- Vingt-sept tests de plus dans la suite `matching` (120 au total) et cinq dans
  la suite `unit` (51) : les six scénarios du §11.3 qui portent sur la
  résolution, la reconnaissance des proxys déclarés par toutes les formes de la
  grammaire, les chaînes à double pile, et les formes d'adresse-avec-port.
- Trois précisions apportées par l'écriture du résolveur, toutes énoncées au
  §4.7 : derrière un proxy déclaré, un en-tête absent rend l'adresse
  indéterminable et non l'adresse de connexion — s'y replier reviendrait à
  évaluer les règles contre l'adresse du CDN ; la lecture s'arrête au premier
  élément qui n'est pas un proxy déclaré, parce que ce qui est à sa gauche est
  du texte que le client contrôle et qu'un en-tête absurde ne doit pas pouvoir
  neutraliser la liste ; et l'adresse accompagnée de son port est lue, sous les
  deux seules formes sans équivoque.
- `.coverage-baseline` passe de 98,31 % à 98,55 %.

### Levé

- Point ouvert V-10 : la suite `contract` a été exécutée contre Restrict
  Content Pro en intégration continue — `OK (36 tests, 77 assertions)`. Le
  mécanisme de bascule de branche est bien absent de la variante commerciale,
  et son option de branche n'apparaît pas du fait de la dépendance. L'écart
  d'assertions avec la variante gratuite — 77 contre 79 — est la signature du
  dispositif du lot 1 : ce sont les tests conscients de la variante qui prennent
  l'autre branche, celle qui affirme l'absence. Les tests restent et changent de
  nature : ils ne comblent plus une inconnue, ils surveillent une propriété
  établie.

### Corrigé

- Le contrôle de branches lisait la mesure fusionnée de php-code-coverage. Dès
  qu'un fichier est exercé par deux suites, la fusion en porte deux modèles de
  branches différents, aux identifiants et aux lignes décalés, et la moitié
  était rapportée non prise alors qu'elle est exercée. La mesure est désormais
  relue d'une seule suite, celle qui exerce les répertoires purs — ce qui
  resserre le contrôle au lieu de le relâcher (JE-021).

### Documentation

- JE-021 : les données de branches ne se fusionnent pas d'un processus à l'autre.
  Un outil de mesure a des défauts comme un autre, et celui-ci se signalait par
  un échec — le même défaut dans une mesure de lignes aurait annoncé une
  couverture plus haute qu'elle n'est, sans que personne le voie.
- Le README dit quelle adresse est évaluée, et ce qu'il faut déclarer pour qu'un
  site derrière un CDN fonctionne. C'est la question que posera tout
  administrateur dont les règles « ne marchent pas ».

---

Lot 2 : moteur de correspondance. Aucune fonctionnalité de restriction n'est
encore livrée — le moteur sait dire si une adresse correspond à une liste, et
personne ne l'interroge encore.

### Ajouté

- Moteur de correspondance pur, sans aucun appel à WordPress : conversion d'une
  adresse en entier de longueur fixe (`src/Address/Ip_Number.php`),
  normalisation des quatre formes en intervalles bornés, analyse de la grammaire
  close du cahier des charges §4.2, et décision sur un ensemble de règles
  (`src/Rule/`).
- La comparaison passe par la forme binaire de `inet_pton()` et non par un
  entier natif : PHP n'a pas d'entier de 128 bits, et la virgule flottante
  perdrait des bits en silence — exactement le genre de perte qui accorde un
  accès à qui ne devait pas l'avoir.
- Quatre-vingt-treize tests dans la suite `matching` : chaque format de FNC-13 à
  FNC-18 avec les bornes attendues, chaque contrainte de G-01 à G-07 avec le
  **motif de refus** attendu, les bornes d'intervalle et leurs voisines, les
  familles croisées, et D-01 à D-05.
- Trois précisions apportées par l'écriture de l'analyseur, toutes dans le sens
  du refus : G-05 est contrôlé sur l'intervalle normalisé et non sur l'écriture,
  ce qui couvre aussi `0.0.0.0-255.255.255.255` ; un octet portant un zéro de
  tête est refusé avec un motif propre, parce que `inet_pton()` le lit
  autrement qu'une bibliothèque qui interprète l'octal (JE-020) ; un joker peut
  porter sur moins de quatre segments, ce qu'impose la lecture de l'exemple
  documenté `149.58.*-149.60.*`.
- Les commentaires introduits par `#` sont acceptés, comme la grammaire du §4.2
  le prévoit : une liste d'adresses se relit mal sans pouvoir y noter à quoi
  correspond un bloc.
- Contrôle de la couverture de **branches** sur les répertoires purs
  (`bin/lib/check-branches.php`), et extension du plancher de 100 % de lignes à
  `src/Address/` et `src/Rule/`. La mesure est relue de la mesure sérialisée
  fusionnée : le rapport Clover ne transporte pas cette information.
- `tests/unit/Tooling/PurityTest.php` : la couche de décision n'appelle aucune
  fonction qui ne soit pas interne à PHP, vérifié par analyse lexicale — un
  `esc_html()` en commentaire ne fait pas échouer le test, un `esc_html()`
  réellement appelé n'y échappe pas.
- Job d'intégration continue `matching`, la suite étant désormais peuplée.
- `.coverage-baseline` passe de 96,89 % à 98,31 %.

### Supprimé

- Seize branches de code défensif qu'aucun test ne pouvait prendre, débusquées
  par le plancher de branches : vérifications de longueur après une conversion
  qui ne peut en produire d'autre, échecs de fonctions qui ne peuvent pas
  échouer, contrôles déjà garantis par la grammaire en amont. Elles ont été
  supprimées et non annotées, et l'arithmétique d'adresse est remontée dans
  `Ip_Number`, où elle compose ses résultats sans fabrique nullable (JE-019).

### Corrigé

- La couverture de chemins d'Xdebug faisait tuer le processus par le système sur
  les suites qui chargent WordPress, sans message et avec le code 137 : le
  filtre de PHPUnit ne réduit pas l'empreinte, parce qu'il filtre après
  collecte. `tests/bootstrap.php` restreint désormais l'instrumentation à
  `src/` dans les processus de mesure (JE-018).

### Documentation

- JE-018 à JE-020 : la mémoire de la couverture de chemins, le code défensif que
  le plancher de branches a débusqué, et l'ambiguïté des zéros de tête.

---

Lot 1 : preuve des accroches. Aucune fonctionnalité de restriction n'est encore
livrée — ce lot établit que la dépendance offre bien les points d'accroche dont
les lots suivants auront besoin, et rend visible ce que le plugin constate.

### Ajouté

- Sonde de capacités étendue aux quatorze symboles du cahier des charges §5.1,
  déclarés en donnée avec, pour chacun, la fonctionnalité qui en dépend, la
  preuve amont qui l'a établie et le contexte dans lequel la dépendance le
  charge (`src/Domain/Capability_Catalogue.php`).
- Trois états de capacité et non deux : présente, absente, **non observable sur
  cette requête**. Plusieurs accroches amont n'existent qu'en administration ;
  les compter absentes sur une requête de façade annoncerait la perte d'une
  fonctionnalité intacte (JE-015).
- Diagnostic de dépendance à trois états — absente, active sur sa base de code
  historique, utilisable — et avis d'administration qui nomme la cause
  **constatée** plus l'action de retour, par capacité et sans jamais lire
  l'option amont de branche (E-05, B-02).
- Écran d'état des capacités, en lecture seule, sous l'outillage de WordPress et
  gardé par `manage_options` : il reste accessible quand la dépendance est
  absente ou revenue à sa branche historique, c'est-à-dire quand il sert. Il
  cite l'option amont de branche en disant qu'elle n'entre pas dans la décision.
- Suite `contract` peuplée — 36 tests contre Restrict Content 4.0.5, tous
  établis par l'exécution : les trois chemins d'application de
  `rcp_member_can_access`, l'absence d'accroche sur la décision de terme et le
  mécanisme de substitution du §5.1.1, `rcp_form_errors` et le retour du message
  par le canal JSON de la dépendance, les accroches et métadonnées de niveau,
  celles de la boîte de restriction, la capacité `rcp_manage_settings`, et la
  réversibilité de la bascule de branche sous capacité et sous nonce.
- Les tests dépendants de la variante **affirment, pour chacune, ce qui doit
  être vrai** plutôt que d'être ignorés : c'est ainsi que la suite répondra au
  point ouvert V-10 dès qu'elle tournera contre la variante commerciale.
- P-01 à P-04 établis dans la suite `integration` : aucune option ni aucun
  transient écrit à l'amorçage, pas davantage au second ; contrôle accroché à
  `plugins_loaded` en priorité 20 ; constat neuf à chaque amorçage ; verdict
  indépendant de l'option amont.
- SEC-16 établie à l'exécution, et non plus seulement à la relecture : un
  bouchon `pre_http_request` échoue à la moindre tentative pendant l'amorçage et
  le rendu des avis.
- Jobs d'intégration continue `contract-free` et `contract-pro`, le second
  conditionné à la mise à disposition de l'archive commerciale par un secret de
  dépôt et en `continue-on-error`, pour ne pas bloquer une contribution externe.

- `.coverage-baseline` passe de 0 à 96,89 %, mesuré sur les trois suites
  peuplées : `src/Domain/` est couvert à 100 %, comme le cahier des charges
  §11.6 l'exige du code pur. La référence ne descend pas d'elle-même — un lot
  qui la ferait baisser doit l'abaisser sciemment, et le `git diff` le montre.

### Corrigé

- `bin/install-wp-tests.sh` traduisait `WP_VERSION=latest` de deux façons
  incompatibles : dernière version **publiée** pour le cœur, `trunk` pour la
  bibliothèque de tests. Les suites échouaient à l'amorçage sur un symbole que
  seule la branche de développement définit, avec un message qui ne nommait pas
  sa cause. `latest` est désormais résolu en numéro de version, le même des deux
  côtés, et une version précédemment installée est remplacée (JE-013).

### Documentation

- JE-013 à JE-016 : la divergence cœur / bibliothèque de tests, les cinq
  gestionnaires de `wp_die()` qu'il faut brancher pour observer un `die()` amont,
  les accroches qui n'existent qu'en administration, et le relevé des positions
  du §5.1 contre la 4.0.5 — deux lignes ont glissé, aucun nom ni aucune
  signature n'a changé.

## [0.1.0-dev] — 2026-09-22

Lot 0 : outillage, amorçage et contrôle des prérequis. Aucune fonctionnalité de
restriction n'est livrée.

### Ajouté

- Environnement de développement reproductible par Docker Compose : WordPress,
  base de données, base de test séparée, capture d'e-mails, image applicative
  partagée, ports paramétrables par `.env`, provisionnement idempotent
  (`bin/setup.sh`).
- Point d'entrée du plugin avec chargement automatique PSR-4 et autoloader de
  repli, pour qu'une copie de travail sans dépendances reste activable.
- Amorçage conditionnel : `Ip_Restriction_For_Restrict_Content\Plugin` constate
  l'environnement et n'enregistre les fonctionnalités que s'il les rend
  exécutables.
- Détection de la dépendance d'adhésions **par capacité**, jamais par numéro de
  version ni par nom de plugin, et réévaluée à chaque requête : la dépendance
  peut basculer entre deux bases de code depuis son administration, ce qui
  interdit de mémoriser le résultat de la sonde (JE-003).
- Avis d'administration nommant précisément les prérequis absents, sans jamais
  produire d'erreur fatale.
- Cinq suites PHPUnit déclarées — `unit`, `integration`, `contract`, `matching`,
  `access` — avec un amorçage qui refuse explicitement une invocation sans
  `--testsuite`, les suites ne pouvant pas partager un processus.
- Suites `unit` et `integration` peuplées : avis de prérequis, honnêteté du
  `Makefile`, hygiène du dépôt, exécution effective des scanners, amorçage dans
  un WordPress réel, concordance de la version entre l'en-tête et la constante.
- `bin/scan-secrets.sh` : aucun secret, aucune adresse de courriel ni appel
  réseau dans le code livré ; adresses de documentation seules admises dans
  l'outillage et les tests (SEC-05, SEC-06).
- `bin/scan-placeholders.sh` : aucun test ignoré, aucun marqueur de
  substitution, aucune erreur d'analyse masquée, aucun identifiant de l'add-on
  commercial (§11.5, §0.3).
- `bin/run-suite.sh` : refuse un succès sans récapitulatif PHPUnit, un `die()`
  pendant un test arrêtant le processus en silence avec le code 0 (JE-001,
  JE-007).
- `bin/coverage.sh` et `bin/lib/merge-coverage.php` : mesure par suite puis
  fusion, `phpcov merge` sortant en succès sans écrire de rapport (JE-012).
- `bin/check-coverage.sh` et `.coverage-baseline` : plancher de couverture et
  interdiction de régression.
- `bin/generate-stubs.sh` : stubs régénérés depuis la dépendance installée,
  jamais édités à la main.
- `bin/install-wp-tests.sh` : installation de la bibliothèque de tests de
  WordPress et de la dépendance, dans l'une ou l'autre variante.
- `bin/build.sh` : archive distribuable sans code de test ni dépendance de
  développement.
- Configuration PHPCS avec les quatre préfixes du plugin et les exclusions
  justifiées pour `tests/`, PHPStan niveau 5, `.editorconfig`.
- Intégration continue : lint, analyse statique, sécurité, suites peuplées,
  couverture, fraîcheur du `.pot`.
- `uninstall.php`, sans effet à ce stade, mais en place avant le premier
  réglage écrit.
- Documentation complète du projet dans `docs/` : cahier des charges,
  architecture, stratégie de test, sécurité, journal empirique et huit décisions
  d'architecture.

### Décidé

- Le slug `ip-restriction-for-restrict-content` est conservé : il commence par
  une fonction générique, et la directive 17 du répertoire WordPress.org
  n'interdit que le début du slug. Le text domain, le namespace et les préfixes
  restent inchangés (ADR-0008).
- La mention de non-affiliation est maintenue dans l'en-tête du plugin et dans
  le `README.md` : elle répond à une exigence distincte de la conformité du
  slug (cahier des charges §14.3).
