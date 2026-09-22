#!/usr/bin/env bash
#
# Cahier des charges §11.5 : pas de fausse complétion.
#
# Tests ignorés, marqueurs de substitution, branches non implémentées et
# identifiants de l'add-on commercial (§0.3) bloquent l'intégration continue.
#
set -uo pipefail

status=0
fail() { printf '\033[0;31mÉCHEC\033[0m %s\n' "$1"; status=1; }
pass() { printf '\033[0;32mOK\033[0m    %s\n' "$1"; }

paths=()
for p in src templates tests bin assets ip-restriction-for-restrict-content.php uninstall.php readme.txt; do
  [ -e "$p" ] && paths+=("$p")
done
[ ${#paths[@]} -eq 0 ] && { echo "Rien à analyser."; exit 0; }

EXCLUDES=(
  --exclude-dir=coverage
  --exclude-dir=vendor
  --exclude-dir=node_modules
  --exclude-dir=dist
  --exclude-dir=build
  --exclude-dir=.git
)

# Les tests et les scanners citent nécessairement les motifs qu'ils interdisent.
# Les exclure nommément vaut mieux qu'affaiblir le motif : la règle reste
# stricte partout ailleurs.
SELF_REFERENTIAL='^(bin/scan-placeholders\.sh|tests/unit/Tooling/RepositoryHygieneTest\.php):'

check() { # libellé, motif
  local hits
  hits="$( grep -rInE --binary-files=without-match "${EXCLUDES[@]}" "$2" "${paths[@]}" 2>/dev/null | grep -vE "$SELF_REFERENTIAL" || true )"

  if [ -n "$hits" ]; then
    printf '%s\n' "$hits"
    fail "$1"
  else
    pass "$1"
  fi
}

check "Aucun test marqué skipped/incomplete" '(markTestSkipped|markTestIncomplete|@group[[:space:]]+skip)'
check "Aucun test isolé (only)"              '\.only\('
check "Aucun marqueur de substitution"       '(TODO:|FIXME:|XXX:|@todo[[:space:]]+implement|not implemented)'
check "Aucune erreur d'analyse masquée"      '@phpstan-ignore'

# §0.3 — développement en salle blanche : aucun identifiant de l'add-on
# commercial ne doit apparaître. Le cahier des charges cite ces chaînes en
# annexe à titre de correspondance de conception ; docs/ est donc hors
# périmètre, et n'est de toute façon pas distribué.
check "Aucun identifiant de l'add-on commercial" '(rcp-ip-restriction|rcpipr)'

exit $status
