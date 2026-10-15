#!/usr/bin/env bash
#
# Récupère l'archive de Restrict Content Pro depuis le lien de licence porté
# par RCP_PRO_ARCHIVE_URL, et ne la laisse en place qu'après l'avoir reconnue
# comme une archive contenant le plugin attendu.
#
# Motif : l'appel se faisait sans `--fail`. Un lien périmé ou révoqué renvoie
# une page d'erreur HTTP ; `curl` l'écrivait alors dans un fichier nommé
# « .zip » et sortait en succès. L'échec n'apparaissait qu'à la décompression,
# loin de sa cause — et nommait un ZIP corrompu, jamais la licence.
#
# Le lien porte un jeton de licence : il ne doit apparaître ni dans un log, ni
# dans un message d'erreur, ni dans un fichier. Les diagnostics de `curl` sont
# donc écartés — ils citent l'URL demandée — au profit du seul code de retour
# et du code HTTP, obtenus par `--write-out`.
#
set -euo pipefail

DEST="${1:-vendor-plugins/restrict-content-pro.zip}"

# `bin/install-wp-tests.sh` décompresse l'archive dans le répertoire de
# plugins, et `tests/bootstrap.php` charge ce chemin exact : une archive dont
# la racine diffère produirait une absence de dépendance, pas une erreur.
MAIN_FILE='restrict-content-pro/restrict-content-pro.php'

if [ -z "${RCP_PRO_ARCHIVE_URL:-}" ]; then
  echo "RCP_PRO_ARCHIVE_URL n'est pas définie : aucune archive commerciale à récupérer." >&2
  exit 1
fi

# Deux échecs distincts, et aucun des deux ne cite le lien. Le premier désigne
# la licence : c'est le cas de loin le plus probable, et celui qu'un message
# vague laisserait chercher. Le second désigne l'archive elle-même, dont la
# racine ne serait plus celle qu'attend le reste de l'outillage.
echec_licence() {
  echo "::error::Archive commerciale inexploitable : $1" >&2
  echo "Cause probable : le lien de licence de Restrict Content Pro est périmé ou révoqué." >&2
  echo "Action : régénérer le lien de téléchargement, puis redéposer le secret RCP_PRO_ARCHIVE_URL." >&2
  rm -f "$DEST"
  exit 1
}

echec_contenu() {
  echo "::error::Archive commerciale inattendue : $1" >&2
  echo "Cause : l'archive obtenue n'est pas celle de Restrict Content Pro, ou sa racine a changé." >&2
  echo "Action : vérifier que le lien de licence sert bien l'archive du plugin, et la redéposer." >&2
  rm -f "$DEST"
  exit 1
}

mkdir -p "$( dirname "$DEST" )"
rm -f "$DEST"

code=0
http=""
http="$(
  curl --fail --silent --show-error --location \
    --write-out '%{http_code}' \
    --output "$DEST" \
    "$RCP_PRO_ARCHIVE_URL" 2>/dev/null
)" || code=$?

if [ "$code" -ne 0 ]; then
  echec_licence "téléchargement refusé (curl ${code}, HTTP ${http:-inconnu})."
fi

# `--fail` ne couvre pas tout : une réponse 200 peut parfaitement porter une
# page de connexion ou un message de licence expirée. Le fichier n'est donc
# accepté qu'après lecture de son catalogue.
if ! unzip -l "$DEST" >/dev/null 2>&1; then
  echec_licence "le fichier reçu n'est pas une archive ZIP (HTTP ${http:-inconnu})."
fi

if ! unzip -Z1 "$DEST" 2>/dev/null | grep -Fxq "$MAIN_FILE"; then
  echec_contenu "l'archive reçue ne contient pas ${MAIN_FILE}."
fi

printf '\033[1;34m==>\033[0m %s\n' "Archive commerciale vérifiée : $DEST"
