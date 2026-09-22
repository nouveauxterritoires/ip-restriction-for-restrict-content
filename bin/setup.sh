#!/usr/bin/env bash
#
# Provisionne le site WordPress de développement.
# Idempotent : relançable sans détruire l'existant (cahier des charges §12.1).
#
set -euo pipefail

WP_PATH="${WP_PATH:-/var/www/html}"
WP_URL="${WP_URL:-http://localhost:8082}"
WP_TITLE="${WP_TITLE:-IP Restriction — Dev}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-admin}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.test}"
RCP_VARIANT="${RCP_VARIANT:-free}"
RCP_VERSION="${RCP_VERSION:-latest}"
VENDOR_PLUGINS_DIR="${VENDOR_PLUGINS_DIR:-/vendor-plugins}"

wp() { command wp --path="$WP_PATH" --allow-root "$@"; }

log() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }

# Voir bin/install-wp-tests.sh : le client MariaDB refuse le certificat
# auto-signé de MySQL 8. Réseau Docker interne, vérification désactivée pour le
# seul client en ligne de commande.
MYSQL_CLIENT_OPTS="${MYSQL_CLIENT_OPTS:---skip-ssl}"

log "Attente de la base de données"
for _ in $(seq 1 60); do
  mysqladmin ping $MYSQL_CLIENT_OPTS \
    -h"${WORDPRESS_DB_HOST:-db}" \
    -u"${WORDPRESS_DB_USER:-wordpress}" \
    -p"${WORDPRESS_DB_PASSWORD:-wordpress}" --silent 2>/dev/null && break
  sleep 2
done

mysqladmin ping $MYSQL_CLIENT_OPTS \
  -h"${WORDPRESS_DB_HOST:-db}" \
  -u"${WORDPRESS_DB_USER:-wordpress}" \
  -p"${WORDPRESS_DB_PASSWORD:-wordpress}" --silent 2>/dev/null \
  || { echo "Base de données injoignable après 120 s." >&2; exit 1; }

log "Attente des fichiers du cœur WordPress"
for _ in $(seq 1 60); do
  [ -f "$WP_PATH/wp-settings.php" ] && break
  sleep 2
done
[ -f "$WP_PATH/wp-settings.php" ] || { echo "Cœur WordPress absent de $WP_PATH" >&2; exit 1; }

if ! wp core is-installed 2>/dev/null; then
  log "Installation de WordPress"
  wp core install \
    --url="$WP_URL" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
else
  log "WordPress déjà installé"
fi

# Le port publié peut changer d'un poste à l'autre : on réaligne les URL.
wp option update siteurl "$WP_URL"
wp option update home "$WP_URL"
wp option update timezone_string 'Europe/Paris'
wp rewrite structure '/%postname%/' --hard

# --- Dépendance d'adhésions ---------------------------------------------------
case "$RCP_VARIANT" in
  pro)
    shopt -s nullglob
    archives=( "$VENDOR_PLUGINS_DIR"/restrict-content-pro*.zip )
    shopt -u nullglob

    if [ ${#archives[@]} -eq 0 ]; then
      echo "RCP_VARIANT=pro demandé mais aucune archive dans $VENDOR_PLUGINS_DIR." >&2
      echo "Déposez-y l'archive commerciale, ou repassez à RCP_VARIANT=free." >&2
      exit 1
    fi

    log "Installation de Restrict Content Pro depuis $(basename "${archives[-1]}")"
    wp plugin deactivate restrict-content 2>/dev/null || true
    wp plugin install "${archives[-1]}" --force --activate
    ;;
  free)
    if ! wp plugin is-installed restrict-content; then
      log "Installation de Restrict Content"
      if [ "$RCP_VERSION" = "latest" ]; then
        wp plugin install restrict-content --activate
      else
        wp plugin install restrict-content --version="$RCP_VERSION" --activate
      fi
    else
      wp plugin activate restrict-content || true
    fi
    ;;
  *)
    echo "RCP_VARIANT inconnu : $RCP_VARIANT (attendu : free ou pro)" >&2
    exit 1
    ;;
esac

# --- Le plugin en cours de développement --------------------------------------
log "Activation de ip-restriction-for-restrict-content"
wp plugin activate ip-restriction-for-restrict-content

# --- Tables de la dépendance --------------------------------------------------
# Restrict Content crée ses tables sur le hook `admin_init`. WP-CLI ne passant
# pas par l'administration, on déclenche le hook explicitement, faute de quoi
# toute création de niveau d'adhésion échoue.
log "Initialisation des tables de la dépendance"
wp eval '
if ( function_exists( "rcp_setup_components" ) ) {
    rcp_setup_components();
}
do_action( "admin_init" );
echo "tables initialisees\n";
'

# --- Jeux de données de démonstration -----------------------------------------
# Deux niveaux d'adhésion de prix et de durée différents : c'est le minimum
# pour exercer un comportement qui dépend du niveau, et pour distinguer le cas
# d'un client porteur de plusieurs adhésions.
log "Création des niveaux d'adhésion de démonstration"
wp eval '
if ( ! function_exists( "rcp_add_membership_level" ) || ! function_exists( "rcp_get_membership_levels" ) ) {
    echo "dependance indisponible — niveaux non crees\n";
    return;
}

$existing = wp_list_pluck( rcp_get_membership_levels( array( "number" => 100 ) ), "name" );

$levels = array(
    array( "name" => "Argent", "price" => 29,  "duration" => 1, "duration_unit" => "month" ),
    array( "name" => "Or",     "price" => 199, "duration" => 1, "duration_unit" => "year"  ),
);

$created = 0;
foreach ( $levels as $level ) {
    if ( in_array( $level["name"], $existing, true ) ) {
        continue;
    }
    if ( rcp_add_membership_level( wp_parse_args( $level, array( "status" => "active" ) ) ) ) {
        ++$created;
    }
}

printf( "%d niveau(x) cree(s), %d deja present(s)\n", $created, count( $existing ) );
' || log "Création des niveaux ignorée"

log "Création des comptes de démonstration"
wp user create adherent adherent@example.test --role=subscriber --user_pass=demo 2>/dev/null || true
wp user create visiteur visiteur@example.test --role=subscriber --user_pass=demo 2>/dev/null || true

log "Création d'une page de contenu réservé"
wp post create --post_type=page --post_title="Contenu réservé" \
  --post_content="Contenu réservé aux adhérents." --post_status=publish 2>/dev/null || true

log "Provisionnement terminé — $WP_URL/wp-admin ($WP_ADMIN_USER / $WP_ADMIN_PASSWORD)"
