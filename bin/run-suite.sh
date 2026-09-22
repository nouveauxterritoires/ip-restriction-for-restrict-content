#!/usr/bin/env bash
#
# Exécute une suite PHPUnit et refuse un succès qui n'en est pas un.
#
# Motif, consigné en JE-007 : un `die()` atteint pendant un test — la
# dépendance en émet, par exemple depuis `wp_send_json_error()` — arrête le
# processus PHP **en silence et avec le code de sortie 0**. PHPUnit n'écrit
# alors aucun récapitulatif, et l'intégration continue lit un succès là où la
# moitié des tests n'a pas été exécutée.
#
# Le code de sortie ne suffit donc pas : on exige le récapitulatif final.
#
set -uo pipefail

suite="${1:?suite attendue}"
shift || true

log="$( mktemp "${TMPDIR:-/tmp}/rcipr-suite.XXXXXX" )"

vendor/bin/phpunit --testsuite "$suite" "$@" 2>&1 | tee "$log"
status="${PIPESTATUS[0]}"

if [ "$status" -ne 0 ]; then
  rm -f "$log"
  exit "$status"
fi

# Deux choses distinctes, qu'il ne faut pas confondre :
#
#   - ce garde-ci vérifie que le processus est allé jusqu'au bout. « No tests
#     executed! » est un récapitulatif comme un autre à cet égard : PHPUnit a
#     conclu ;
#   - qu'une suite soit vide est un autre problème, traité en n'ouvrant pas de
#     job d'intégration continue pour une suite qui ne contient encore rien
#     (JE-001).
if ! grep -qaE '^(OK|OK, but|Tests:|No tests executed!)' "$log"; then
  echo
  echo "La suite « $suite » s'est terminée sans récapitulatif PHPUnit." >&2
  echo "Un traitement a très probablement appelé die() : le processus s'est arrêté" >&2
  echo "en silence, et les tests suivants n'ont pas été exécutés." >&2
  rm -f "$log"
  exit 1
fi

rm -f "$log"
