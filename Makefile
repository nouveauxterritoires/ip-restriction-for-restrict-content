# ip-restriction-for-restrict-content — commandes de développement et de test.
#
# Prérequis : Docker + Docker Compose v2.
# Copier .env.example vers .env avant le premier `make up`.

SHELL := /bin/bash
DC    := docker compose
PLUGIN_PATH := /var/www/html/wp-content/plugins/ip-restriction-for-restrict-content
CLI   := $(DC) run --rm cli
IN    := $(CLI) "cd $(PLUGIN_PATH) &&

.DEFAULT_GOAL := help
.PHONY: help up down clean setup shell wp logs prepare-tests \
        lint fix stan scan stubs \
        test test-unit test-integration test-contract test-matching test-access test-contract-pro \
        coverage check-coverage pot build ci

help: ## Liste les cibles et ce qu'elles font
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}'

# --- Cycle de vie de la pile --------------------------------------------------

up: ## Démarre la pile Docker et provisionne le site
	$(DC) up -d db db_test wordpress mailpit
	$(MAKE) --no-print-directory setup
	@echo "Site    : http://localhost:$${RCIPR_HTTP_PORT:-8082}"
	@echo "E-mails : http://localhost:$${RCIPR_MAIL_PORT:-8027}"

down: ## Arrête la pile Docker (conserve les données)
	$(DC) down

clean: ## Détruit la pile Docker, les volumes et les données
	$(DC) down -v --remove-orphans

setup: ## Provisionne WordPress, la dépendance et les données de démonstration
	$(CLI) "bash $(PLUGIN_PATH)/bin/setup.sh"

shell: ## Ouvre un shell dans le conteneur cli
	$(DC) run --rm cli "bash"

wp: ## Exécute une commande WP-CLI — make wp CMD="plugin list"
	$(CLI) "wp --path=/var/www/html --allow-root $(CMD)"

logs: ## Suit les journaux du conteneur WordPress
	$(DC) logs -f wordpress

prepare-tests: ## Installe la bibliothèque de tests WordPress et la dépendance
	$(CLI) "bash $(PLUGIN_PATH)/bin/install-wp-tests.sh"

# --- Qualité ------------------------------------------------------------------

lint: ## PHPCS sur src/, templates/, tests/ et le fichier principal
	$(IN) composer install --no-interaction --quiet && vendor/bin/phpcs --report-full --report-summary"

fix: ## PHPCBF sur le même périmètre que lint
	$(IN) vendor/bin/phpcbf || true"

stan: ## PHPStan niveau 5 avec les stubs de stubs/
	$(IN) vendor/bin/phpstan analyse --memory-limit=1G --no-progress"

scan: ## Exécute bin/scan-secrets.sh puis bin/scan-placeholders.sh
	bash bin/scan-secrets.sh && bash bin/scan-placeholders.sh

# --- Tests --------------------------------------------------------------------
# Les suites tournent dans des processus distincts : la suite unit remplace les
# fonctions de WordPress, les autres les chargent (voir tests/bootstrap.php).

test: test-unit test-integration test-contract test-matching test-access ## Exécute toutes les suites, séquentiellement

test-unit: ## Suite unit — logique pure et outillage, WordPress mocké
	$(IN) composer install --no-interaction --quiet && bash bin/run-suite.sh unit"

test-integration: prepare-tests ## Suite integration — WordPress réel
	$(IN) bash bin/run-suite.sh integration"

test-contract: prepare-tests ## Suite contract — Restrict Content ou Restrict Content Pro réel
	$(IN) bash bin/run-suite.sh contract"

test-contract-pro: ## Suite contract contre Restrict Content Pro — RCP_PRO_SOURCE=<dossier> ou archive dans vendor-plugins/
	RCP_VARIANT=pro $(DC) run --rm -e RCP_VARIANT=pro cli "bash $(PLUGIN_PATH)/bin/install-wp-tests.sh && cd $(PLUGIN_PATH) && bash bin/run-suite.sh contract"

test-matching: prepare-tests ## Suite matching — évaluation des adresses, plages, CIDR et jokers
	$(IN) bash bin/run-suite.sh matching"

test-access: prepare-tests ## Suite access — contournement des restrictions et blocage d'inscription
	$(IN) bash bin/run-suite.sh access"

coverage: prepare-tests ## Mesure chaque suite et fusionne les rapports dans tests/coverage/
	$(IN) bash bin/coverage.sh"

check-coverage: ## Échoue sous le plancher de couverture ou en cas de régression
	$(IN) bash bin/check-coverage.sh"

# --- Preuve contre la dépendance ----------------------------------------------

stubs: ## Régénère stubs/ depuis la dépendance installée (diff à relire)
	$(CLI) "cd $(PLUGIN_PATH) && bash bin/generate-stubs.sh"

# --- Livraison ----------------------------------------------------------------

pot: ## Régénère languages/ip-restriction-for-restrict-content.pot
	$(CLI) "cd $(PLUGIN_PATH) && wp i18n make-pot . languages/ip-restriction-for-restrict-content.pot --slug=ip-restriction-for-restrict-content --allow-root"

build: ## Produit le zip distribuable dans dist/
	bash bin/build.sh

ci: lint stan scan test check-coverage ## Enchaîne lint, stan, scan, toutes les suites et le seuil de couverture
