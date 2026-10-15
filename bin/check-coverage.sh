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

# §11.6 : les répertoires purs sont tenus à 100 % des lignes. C'est du code
# sans dépendance et sans entrée-sortie : il n'y a pas de ligne qu'on ne puisse
# exercer, et donc pas d'excuse. Le seuil global, lui, laisse de la place au
# code de transport.
PURE_DIRECTORIES="${PURE_DIRECTORIES:-src/Domain src/Address src/Rule src/Policy}"

for directory in $PURE_DIRECTORIES; do
  measure="$(
    php -r '
      $xml = simplexml_load_file( $argv[1] );
      $covered = 0;
      $total   = 0;

      foreach ( $xml->project->file as $file ) {
          if ( false === strpos( (string) $file["name"], "/" . trim( $argv[2], "/" ) . "/" ) ) {
              continue;
          }

          $covered += (int) $file->metrics["coveredstatements"];
          $total   += (int) $file->metrics["statements"];
      }

      printf( "%d %d", $covered, $total );
    ' "$file" "$directory"
  )"

  pure_covered="${measure% *}"
  pure_total="${measure#* }"

  # Un répertoire absent ou vide ne fait pas échouer : les répertoires de la
  # liste arrivent lot par lot, et un plancher sur du vide ne protège rien.
  if [ "$pure_total" -eq 0 ]; then
    echo "Lignes     : ${directory} — aucune ligne mesurée"
    continue
  fi

  if [ "$pure_covered" -lt "$pure_total" ]; then
    echo "Lignes     : ${directory} — ${pure_covered}/${pure_total}, le cahier des charges §11.6 en exige 100 %." >&2
    exit 1
  fi

  echo "Lignes     : ${directory} — 100% (${pure_covered}/${pure_total})"
done

# Le moteur est en outre tenu à 100 % des **branches**. Une couverture de
# lignes n'y suffit pas : `if ( $a && $b )` tient sur une ligne et porte
# plusieurs chemins. La mesure n'est pas dans le rapport Clover, et elle est
# relue de la mesure d'une seule suite : les données de branches ne se
# fusionnent pas de façon fiable d'un processus à l'autre (JE-021). La suite
# retenue est celle qui exerce les répertoires purs, ce qui resserre le
# contrôle au lieu de le relâcher.
ENGINE_DIRECTORIES="${ENGINE_DIRECTORIES:-src/Address src/Rule src/Policy}"
ENGINE_SUITE="${ENGINE_SUITE:-matching}"
engine_measure="${ENGINE_COVERAGE:-$( dirname "$file" )/parts/${ENGINE_SUITE}.cov}"

if [ -f "$engine_measure" ]; then
  # shellcheck disable=SC2086
  php bin/lib/check-branches.php "$engine_measure" $ENGINE_DIRECTORIES || exit 1
else
  echo "Branches   : mesure de la suite ${ENGINE_SUITE} absente (${engine_measure}) — exécutez « make coverage »." >&2
  exit 1
fi

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
