#!/usr/bin/env bash
#
# Contrôles de sécurité statiques (cahier des charges SEC-05, SEC-06).
#
# Deux périmètres :
#   - « livré »  : le code du plugin distribué. Aucun secret, aucune adresse
#                  de courriel en dur.
#   - « dépôt »  : tout le dépôt, outillage et tests compris. Aucun secret ;
#                  les adresses de courriel de test y sont admises, mais
#                  seulement celles des domaines réservés par la RFC 2606.
#
# Les appels réseau sortants sont traités par un contrôle dédié, dont la règle
# dépend du plugin : voir plus bas.
#
set -uo pipefail

status=0

fail() { printf '\033[0;31mÉCHEC\033[0m %s\n' "$1"; status=1; }
pass() { printf '\033[0;32mOK\033[0m    %s\n' "$1"; }

shipped=()
for path in src templates assets ip-restriction-for-restrict-content.php uninstall.php; do
  [ -e "$path" ] && shipped+=("$path")
done

repository=()
for path in src templates assets bin tests docker ip-restriction-for-restrict-content.php uninstall.php; do
  [ -e "$path" ] && repository+=("$path")
done

EXCLUDES=(
  --exclude-dir=coverage
  --exclude-dir=vendor
  --exclude-dir=node_modules
  --exclude-dir=dist
  --exclude-dir=build
  --exclude-dir=.git
)

# Les commentaires citent légitimement les motifs recherchés : la documentation
# du code explique par exemple pourquoi un appel réseau est cantonné.
strip_comments() {
  grep -vE '^[^:]+:[0-9]+:[[:space:]]*(\*|//|#|;)'
}

check() { # libellé, motif, chemins…
  local label="$1" pattern="$2"; shift 2
  local hits

  if [ $# -eq 0 ]; then pass "$label (rien à analyser)"; return; fi

  hits="$( grep -rInE --binary-files=without-match "${EXCLUDES[@]}" "$pattern" "$@" 2>/dev/null | strip_comments || true )"

  if [ -n "$hits" ]; then
    printf '%s\n' "$hits"
    fail "$label"
  else
    pass "$label"
  fi
}

NETWORK='(wp_remote_(get|post|head|request)|curl_exec|file_get_contents[[:space:]]*\([[:space:]]*.https?://|fsockopen)'

echo "Périmètre : dépôt complet"
check "  Aucune clé privée"             '-----BEGIN [A-Z ]*PRIVATE KEY-----'                   "${repository[@]}"
check "  Aucun jeton de fournisseur"    '(sk|rk|pk)_(live|test)_[A-Za-z0-9]{16,}|whsec_[A-Za-z0-9]{16,}|gh[pousr]_[A-Za-z0-9]{20,}|AKIA[0-9A-Z]{16}|xkeysib-[A-Za-z0-9]{16,}' "${repository[@]}"
check "  Aucun mot de passe en dur"     '(password|passwd|secret|api_key|apikey)[[:space:]]*=[[:space:]]*.[A-Za-z0-9/+=_-]{12,}' "${repository[@]}"

echo "Périmètre : code livré"
# Le plugin ne contacte aucun serveur externe. Le test d'intégration le prouve
# à l'exécution ; ce contrôle attrape l'intention dès la relecture.
check "  Aucun appel réseau sortant"    "$NETWORK" "${shipped[@]}"
check "  Aucune ressource distante"     'https?://(cdn|unpkg|cdnjs|ajax\.googleapis)' "${shipped[@]}"
check "  Aucune adresse de courriel"    '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}'       "${shipped[@]}"

# Les adresses présentes dans l'outillage et les tests doivent relever des
# domaines réservés à la documentation (RFC 2606) : example.test, example.com,
# example.org, example.net, et le domaine .invalid.
echo "Périmètre : adresses de courriel de l'outillage et des tests"
unknown="$(
  grep -rIohE --binary-files=without-match "${EXCLUDES[@]}" '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}' "${repository[@]}" 2>/dev/null \
    | sort -u \
    | grep -vE '@(example\.(test|com|org|net)|[A-Za-z0-9.-]+\.invalid|localhost)$' || true
)"

if [ -n "$unknown" ]; then
  printf '%s\n' "$unknown"
  fail "  Toutes les adresses sont des adresses de documentation (RFC 2606)"
else
  pass "  Toutes les adresses sont des adresses de documentation (RFC 2606)"
fi

exit $status
