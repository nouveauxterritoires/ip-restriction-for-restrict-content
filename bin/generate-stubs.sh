#!/usr/bin/env bash
#
# Génère les stubs de la dépendance d'adhésions depuis l'installation réelle.
#
# Les stubs ne sont jamais édités à la main : un stub écrit à la main dérive de
# la réalité qu'il prétend décrire (cahier des charges §12.4). Ils sont
# régénérés à chaque montée de version de la dépendance, et leur diff est relu —
# c'est un signal de rupture amont au même titre que la suite `contract`.
#
# Depuis le conteneur :  make stubs
# Depuis l'hôte :        RC_SOURCE=/chemin/vers/restrict-content bash bin/generate-stubs.sh
#
set -euo pipefail

RC_SOURCE="${RC_SOURCE:-/var/www/html/wp-content/plugins/restrict-content}"
OUT="${OUT:-stubs/restrict-content.stub.php}"

log() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }

[ -d "$RC_SOURCE" ] || { echo "Source introuvable : $RC_SOURCE" >&2; exit 1; }

version="$( grep -m1 "^ \* Version:" "$RC_SOURCE/restrictcontent.php" | sed -E 's/.*Version:[[:space:]]*//' )"
[ -n "$version" ] || version="inconnue"

log "Génération des stubs depuis $RC_SOURCE (version $version)"

#
# Périmètre : le fichier principal, `core/includes` et `core/src`, qui portent
# l'API sur laquelle ce plugin s'appuie.
#
# Sont exclus : `vendor/` (dépendances tierces préfixées par Strauss), `lib/`,
# `legacy/`, `core/build/`, `lang/` et `languages/`.
#
# Restreindre le périmètre n'est pas une commodité : un fichier de stubs de
# plusieurs mégaoctets produit des diffs que personne ne relit, ce qui ferait
# perdre à l'exercice sa seule raison d'être.
#
finder="$( mktemp "${TMPDIR:-/tmp}/rcipr-finder.XXXXXX" )"
cat > "$finder" <<FINDER
<?php
return StubsGenerator\Finder::create()
    ->in('$RC_SOURCE/core/includes')
    ->in('$RC_SOURCE/core/src')
    ->append(array( '$RC_SOURCE/restrictcontent.php' ))
    ->exclude(array( 'vendor', 'node_modules', 'build', 'batch' ));
FINDER

vendor/bin/generate-stubs --force --finder="$finder" --out="$OUT"
rm -f "$finder"

# La provenance figure dans le fichier : sans elle, un relecteur ne peut pas
# savoir de quelle version le stub décrit l'API.
tmp="$( mktemp )"
{
	cat <<HEADER
<?php

/*
 * Stubs de la dépendance d'adhésions — GÉNÉRÉ, NE PAS ÉDITER.
 *
 * Source  : ${RC_SOURCE##*/}
 * Version : $version
 * Généré  : $( date -u +%Y-%m-%dT%H:%M:%SZ ) par bin/generate-stubs.sh
 *
 * Régénérer : make stubs. Le diff obtenu est relu : il signale une rupture
 * amont au même titre que la suite contract.
 */
HEADER
	tail -n +2 "$OUT"
} > "$tmp"
mv "$tmp" "$OUT"

log "Terminé : $OUT ($( wc -l < "$OUT" | tr -d ' ' ) lignes, $( du -h "$OUT" | cut -f1 ))"
