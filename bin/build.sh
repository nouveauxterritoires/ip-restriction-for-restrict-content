#!/usr/bin/env bash
#
# Construit l'archive distribuable du plugin.
#
# N'embarque que ce qui est nécessaire à l'exécution : ni tests, ni outillage,
# ni dépendances de développement (cahier des charges §14.2).
#
# Synchronise également les trois endroits où la version est écrite. Une
# divergence entre l'en-tête du plugin, `RCIPR_VERSION` et le `Stable tag`
# du readme.txt produit un paquet que WordPress.org refuse : la construction
# échoue plutôt que de livrer cette incohérence.
#
set -euo pipefail

SLUG="ip-restriction-for-restrict-content"
BUILD_DIR="${BUILD_DIR:-dist}"
TARGET="$BUILD_DIR/$SLUG"

log() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }

header_version="$( grep -m1 "^ \* Version:" "$SLUG.php" | sed -E 's/.*Version:[[:space:]]*//' )"
[ -n "$header_version" ] || { echo "Version introuvable dans l'en-tête de $SLUG.php" >&2; exit 1; }

constant_version="$( grep -m1 "^const RCIPR_VERSION" "$SLUG.php" | sed -E "s/.*'([^']+)'.*/\1/" )"
[ "$header_version" = "$constant_version" ] \
  || { echo "En-tête ($header_version) et RCIPR_VERSION ($constant_version) divergent." >&2; exit 1; }

if [ -f readme.txt ]; then
  stable_tag="$( grep -m1 "^Stable tag:" readme.txt | sed -E 's/.*Stable tag:[[:space:]]*//' )"
  [ "$header_version" = "$stable_tag" ] \
    || { echo "En-tête ($header_version) et Stable tag ($stable_tag) divergent." >&2; exit 1; }
fi

if [ -f CHANGELOG.md ] && ! grep -q "\[$header_version\]" CHANGELOG.md; then
  echo "CHANGELOG.md ne porte aucune entrée pour $header_version." >&2
  exit 1
fi

log "Construction de $SLUG $header_version"

# Ni secret ni fausse complétion ne doit partir dans une archive.
bash bin/scan-secrets.sh > /dev/null
bash bin/scan-placeholders.sh > /dev/null

rm -rf "$TARGET"
mkdir -p "$TARGET"

for item in "$SLUG.php" uninstall.php src templates assets languages readme.txt LICENSE; do
  [ -e "$item" ] && cp -R "$item" "$TARGET/"
done

log "Autoloader d'exécution"
composer install --no-dev --classmap-authoritative --quiet
[ -d vendor ] && cp -R vendor "$TARGET/"

# Restaure l'outillage de développement.
composer install --quiet

archive="$BUILD_DIR/$SLUG-$header_version.zip"
rm -f "$archive"

log "Archive"
( cd "$BUILD_DIR" && zip -qr "$SLUG-$header_version.zip" "$SLUG" )

log "Terminé : $archive ($( du -h "$archive" | cut -f1 ))"
