# Journal des modifications

Format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Versionnage [sémantique](https://semver.org/lang/fr/).

## [Non publié]

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
