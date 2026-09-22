#!/usr/bin/env bash
#
# Contrôle du seuil de couverture à partir d'un rapport Clover.
#
# Cahier des charges §11.6 : le seuil effectif est le maximum entre le plancher
# (80 %) et la couverture de la version précédente, mémorisée dans
# `.coverage-baseline`. La couverture ne peut donc pas diminuer d'une version à
# l'autre.
#
# Usage : check-coverage.sh [rapport clover] [plancher en %]
#
set -euo pipefail

file="${1:-tests/coverage/clover.xml}"
floor="${2:-80}"
baseline_file="${COVERAGE_BASELINE:-.coverage-baseline}"

[ -f "$file" ] || { echo "Rapport introuvable : $file" >&2; exit 1; }

metrics="$(
  php -r '
    $xml = simplexml_load_file( $argv[1] );

    if ( false === $xml || ! isset( $xml->project->metrics ) ) {
        fwrite( STDERR, "Rapport Clover illisible ou incomplet.\n" );
        exit( 1 );
    }

    $m = $xml->project->metrics;

    printf( "%d %d\n", (int) $m["coveredstatements"], (int) $m["statements"] );
  ' "$file"
)"

covered="${metrics% *}"
total="${metrics#* }"

[ "$total" -gt 0 ] || { echo "Aucune instruction mesurée." >&2; exit 1; }

percent="$( php -r 'printf( "%.2f", $argv[1] / $argv[2] * 100 );' "$covered" "$total" )"

baseline="0"
[ -f "$baseline_file" ] && baseline="$( tr -d '[:space:]' < "$baseline_file" )"
[ -n "$baseline" ] || baseline="0"

threshold="$( php -r 'printf( "%.2f", max( (float) $argv[1], (float) $argv[2] ) );' "$floor" "$baseline" )"

# §11.6 : `src/Domain/` est tenu à 100 %. C'est du code pur, sans dépendance,
# sans entrée-sortie : il n'y a pas de ligne qu'on ne puisse exercer, et donc
# pas d'excuse. Le seuil global, lui, laisse de la place au code de transport.
domain="$(
  php -r '
    $xml = simplexml_load_file( $argv[1] );
    $covered = 0;
    $total   = 0;

    foreach ( $xml->project->file as $file ) {
        if ( false === strpos( (string) $file["name"], "/src/Domain/" ) ) {
            continue;
        }

        $covered += (int) $file->metrics["coveredstatements"];
        $total   += (int) $file->metrics["statements"];
    }

    printf( "%d %d", $covered, $total );
  ' "$file"
)"

domain_covered="${domain% *}"
domain_total="${domain#* }"

if [ "$domain_total" -gt 0 ] && [ "$domain_covered" -lt "$domain_total" ]; then
  echo "Domaine   : ${domain_covered}/${domain_total} — src/Domain/ doit être couvert à 100 %." >&2
  exit 1
fi

echo "Domaine    : 100% (${domain_covered}/${domain_total})"
echo "Couverture : ${percent}% (${covered}/${total})"
echo "Seuil      : ${threshold}% (plancher ${floor}%, référence ${baseline}%)"

php -r 'exit( (float) $argv[1] >= (float) $argv[2] ? 0 : 1 );' "$percent" "$threshold" \
  || { echo "Couverture insuffisante : régression ou plancher non atteint." >&2; exit 1; }

# La référence ne progresse que si la couverture progresse : un `git diff` sur
# ce fichier rend la montée du seuil visible en revue.
if [ "${UPDATE_BASELINE:-0}" = "1" ]; then
  php -r 'exit( (float) $argv[1] > (float) $argv[2] ? 0 : 1 );' "$percent" "$baseline" \
    && { echo "$percent" > "$baseline_file"; echo "Référence portée à ${percent}%."; }
fi
