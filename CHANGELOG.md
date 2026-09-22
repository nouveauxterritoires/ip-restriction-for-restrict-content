# Journal des modifications

Format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Versionnage [sémantique](https://semver.org/lang/fr/).

## [Non publié]

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
  architecture, stratégie de test, sécurité, journal empirique et sept décisions
  d'architecture.
