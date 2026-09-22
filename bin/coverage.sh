#!/usr/bin/env bash
#
# Couverture de code fusionnée.
#
# Les suites ne peuvent pas partager un processus (voir tests/bootstrap.php) :
# chacune est mesurée séparément, puis les rapports sont fusionnés par phpcov.
#
set -euo pipefail

SUITES="${SUITES:-unit integration contract matching access}"

# Sans pilote de couverture, PHPUnit se contente d'un avertissement et sort
# avec le code 0 : la fusion échouerait plus loin sur une absence de mesures,
# loin de sa cause. On refuse ici, en nommant ce qui manque.
if ! php -m | grep -qiE '^(xdebug|pcov)$'; then
  echo "Aucun pilote de couverture chargé (xdebug ou pcov)." >&2
  echo "Dans la pile Docker du projet, xdebug est présent : utilisez « make coverage »." >&2
  exit 1
fi

OUT_DIR="${OUT_DIR:-tests/coverage}"
PARTS_DIR="$OUT_DIR/parts"
LOG_DIR="$OUT_DIR/logs"

rm -rf "$PARTS_DIR" "$LOG_DIR"
mkdir -p "$PARTS_DIR" "$LOG_DIR"

for suite in $SUITES; do
  printf '\033[1;34m==>\033[0m Couverture — suite %s\n' "$suite"
  XDEBUG_MODE=coverage vendor/bin/phpunit \
    --testsuite "$suite" \
    --coverage-php "$PARTS_DIR/$suite.cov" \
    > "$LOG_DIR/$suite.log" 2>&1 \
    || { echo "Échec de la suite $suite :"; tail -30 "$LOG_DIR/$suite.log"; exit 1; }

  # Voir bin/run-suite.sh : le garde porte sur la fin du processus, pas sur
  # la présence de tests.
  grep -qaE '^(OK|OK, but|Tests:|No tests executed!)' "$LOG_DIR/$suite.log" \
    || { echo "La suite $suite s'est terminée sans récapitulatif — voir JE-001."; tail -20 "$LOG_DIR/$suite.log"; exit 1; }
done

printf '\033[1;34m==>\033[0m Fusion des mesures\n'
php bin/lib/merge-coverage.php "$PARTS_DIR" "$OUT_DIR/clover.xml" "$OUT_DIR/html"
