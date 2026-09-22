#!/usr/bin/env bash
#
# Installe la bibliothèque de tests de WordPress (wordpress-tests-lib) et la
# dépendance d'adhésions, utilisées par toutes les suites sauf « unit ».
#
set -euo pipefail

DB_NAME="${WP_TESTS_DB_NAME:-wordpress_test}"
DB_USER="${WP_TESTS_DB_USER:-root}"
DB_PASS="${WP_TESTS_DB_PASSWORD:-root}"
DB_HOST="${WP_TESTS_DB_HOST:-db_test}"
WP_VERSION="${WP_VERSION:-latest}"

WP_TESTS_DIR="${WP_TESTS_DIR:-/wp-tests/lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/wp-tests/core}"

log() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }

# Le client MariaDB embarqué dans l'image WordPress vérifie par défaut le
# certificat TLS du serveur ; MySQL 8 en présente un auto-signé, ce qui fait
# échouer la connexion. La communication reste interne au réseau Docker de
# développement : la vérification est désactivée pour le client en ligne de
# commande uniquement (PHP/mysqli n'est pas concerné).
MYSQL_CLIENT_OPTS="${MYSQL_CLIENT_OPTS:---skip-ssl}"

if [ "$WP_VERSION" = "latest" ] || [ "$WP_VERSION" = "trunk" ]; then
  WP_TESTS_TAG="trunk"
else
  WP_TESTS_TAG="tags/$WP_VERSION"
fi

if [ ! -d "$WP_CORE_DIR" ]; then
  log "Téléchargement du cœur WordPress ($WP_VERSION)"
  mkdir -p "$WP_CORE_DIR"
  if [ "$WP_VERSION" = "latest" ] || [ "$WP_VERSION" = "trunk" ]; then
    url="https://wordpress.org/latest.tar.gz"
  else
    url="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
  fi
  curl -sSL "$url" | tar --strip-components=1 -zx -C "$WP_CORE_DIR"
fi

if [ ! -d "$WP_TESTS_DIR" ]; then
  log "Récupération de la bibliothèque de tests ($WP_TESTS_TAG)"
  mkdir -p "$WP_TESTS_DIR"
  svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
  svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/"     "$WP_TESTS_DIR/data"
fi

if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
  log "Écriture de wp-tests-config.php"
  curl -sSL "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" \
    -o "$WP_TESTS_DIR/wp-tests-config.php"
  sed -i "s:dirname( __FILE__ ) . '/src/':'${WP_CORE_DIR}/':" "$WP_TESTS_DIR/wp-tests-config.php"
  sed -i "s/youremptytestdbnamehere/${DB_NAME}/"              "$WP_TESTS_DIR/wp-tests-config.php"
  sed -i "s/yourusernamehere/${DB_USER}/"                     "$WP_TESTS_DIR/wp-tests-config.php"
  sed -i "s/yourpasswordhere/${DB_PASS}/"                     "$WP_TESTS_DIR/wp-tests-config.php"
  sed -i "s|localhost|${DB_HOST}|"                            "$WP_TESTS_DIR/wp-tests-config.php"
fi

log "Création de la base de tests"
mysqladmin create "$DB_NAME" $MYSQL_CLIENT_OPTS \
  --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" 2>/dev/null \
  || log "Base de tests déjà présente"

# La dépendance d'adhésions doit être présente dans le répertoire de plugins de
# test : les suites d'intégration, de contrat et les suites métier en dépendent.
#
# RCP_VARIANT=free : Restrict Content, publié sur WordPress.org (par défaut).
# RCP_VARIANT=pro  : Restrict Content Pro, archive commerciale déposée dans
#                    vendor-plugins/ et jamais versionnée (§12.1).
PLUGIN_DIR="${WP_CORE_DIR}/wp-content/plugins"
RCP_VARIANT="${RCP_VARIANT:-free}"
VENDOR_PLUGINS_DIR="${VENDOR_PLUGINS_DIR:-/vendor-plugins}"
mkdir -p "$PLUGIN_DIR"

install_dependency_pro() {
  # Les deux variantes ne peuvent pas coexister : elles définissent les mêmes
  # classes et les mêmes constantes.
  rm -rf "$PLUGIN_DIR/restrict-content" "$PLUGIN_DIR/restrict-content-pro"

  if [ -n "${RCP_PRO_SOURCE:-}" ]; then
    if [ ! -f "$RCP_PRO_SOURCE/restrict-content-pro.php" ]; then
      echo "RCP_PRO_SOURCE ne contient pas restrict-content-pro.php : $RCP_PRO_SOURCE" >&2
      exit 1
    fi

    log "Installation de Restrict Content Pro depuis $RCP_PRO_SOURCE"
    cp -R "$RCP_PRO_SOURCE" "$PLUGIN_DIR/restrict-content-pro"
    return
  fi

  local archive
  archive="$(find "$VENDOR_PLUGINS_DIR" -maxdepth 1 -name 'restrict-content-pro*.zip' 2>/dev/null | sort | tail -1)"

  if [ -z "$archive" ]; then
    echo "RCP_VARIANT=pro demandé mais aucune source trouvée." >&2
    echo "Fournissez RCP_PRO_SOURCE=<répertoire> ou déposez une archive dans ${VENDOR_PLUGINS_DIR}." >&2
    echo "Sinon, utilisez RCP_VARIANT=free." >&2
    exit 1
  fi

  log "Installation de Restrict Content Pro depuis $(basename "$archive")"
  unzip -q -o "$archive" -d "$PLUGIN_DIR"
}

install_dependency_free() {
  rm -rf "$PLUGIN_DIR/restrict-content-pro"

  if [ -d "$PLUGIN_DIR/restrict-content" ]; then
    log "Restrict Content déjà présent"
    return
  fi

  log "Téléchargement de Restrict Content"
  if [ "${RCP_VERSION:-latest}" = "latest" ]; then
    curl -sSL "https://downloads.wordpress.org/plugin/restrict-content.zip" -o /tmp/rc.zip
  else
    curl -sSL "https://downloads.wordpress.org/plugin/restrict-content.${RCP_VERSION}.zip" -o /tmp/rc.zip
  fi
  unzip -q -o /tmp/rc.zip -d "$PLUGIN_DIR"
}

case "$RCP_VARIANT" in
  pro)  install_dependency_pro ;;
  free) install_dependency_free ;;
  *)    echo "RCP_VARIANT inconnu : $RCP_VARIANT (attendu : free ou pro)" >&2; exit 1 ;;
esac

log "Environnement de tests prêt"
