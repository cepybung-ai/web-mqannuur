#!/bin/sh
set -eu

APP_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SOCK="${WEB_MQ_DB_SOCKET:-/tmp/webmq-mariadb.sock}"
DATADIR="${WEBMQ_DB_DATADIR:-/var/lib/webmq-mariadb}"
PIDFILE="/tmp/webmq-mariadb.pid"
ERRLOG="/tmp/webmq-mariadb.err"
DB_NAME="${WEB_MQ_DB_NAME:-web_mqannuur}"
DB_USER="${WEB_MQ_DB_USER:-web_mq_local}"
DB_PASS="${WEB_MQ_DB_PASS:-}"

is_alive() {
    mariadb-admin --socket="$SOCK" -u root ping >/dev/null 2>&1
}

if ! is_alive; then
    if [ ! -d "$DATADIR/mysql" ]; then
        echo "Menyiapkan datadir MariaDB baru di $DATADIR ..."
        mkdir -p "$DATADIR"
        chown -R mysql:mysql "$DATADIR"
        mariadb-install-db --user=mysql --datadir="$DATADIR" \
            --auth-root-authentication-method=normal --skip-test-db
    fi

    mkdir -p /run/mysqld
    chown mysql:mysql /run/mysqld
    echo "Menjalankan MariaDB khusus web-mqannuur pada $SOCK ..."
    nohup mariadbd --user=mysql --datadir="$DATADIR" --socket="$SOCK" \
        --pid-file="$PIDFILE" --skip-networking --log-error="$ERRLOG" \
        >/tmp/webmq-mariadb.out 2>&1 &

    i=0
    while [ "$i" -lt 40 ]; do
        is_alive && break
        i=$((i + 1))
        sleep 0.5
    done
    if ! is_alive; then
        echo "MariaDB gagal dijalankan. Periksa $ERRLOG" >&2
        exit 1
    fi
fi

mariadb --socket="$SOCK" -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

HAS_PAGES=$(mariadb --socket="$SOCK" -u root -N -B -e \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='pages';")
if [ "$HAS_PAGES" = "0" ]; then
    echo "Mengimpor schema awal ..."
    mariadb --socket="$SOCK" -u root "$DB_NAME" < "$APP_DIR/database/schema.sql"
fi

NEED_MIGRATION=0
for TABLE in posts links events staff downloads gallery settings templates form_submissions; do
    HAS_TABLE=$(mariadb --socket="$SOCK" -u root -N -B -e \
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='$TABLE';")
    if [ "$HAS_TABLE" = "0" ]; then
        NEED_MIGRATION=1
    fi
done
HAS_ROLE=$(mariadb --socket="$SOCK" -u root -N -B -e \
    "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$DB_NAME' AND table_name='users' AND column_name='role';")
if [ "$HAS_ROLE" = "0" ]; then
    NEED_MIGRATION=1
fi
if [ "$NEED_MIGRATION" = "1" ]; then
    echo "Menerapkan migrasi modul dashboard ..."
    mariadb --socket="$SOCK" -u root "$DB_NAME" < "$APP_DIR/database/migrations/20261026_create_dashboard_modules.sql"
fi

HAS_THEME_SETTING=$(mariadb --socket="$SOCK" -u root -N -B -e \
    "SELECT COUNT(*) FROM \`$DB_NAME\`.\`settings\` WHERE \`setting_key\`='active_theme';")
if [ "$HAS_THEME_SETTING" = "0" ]; then
    echo "Menambahkan pengaturan template aktif ..."
    mariadb --socket="$SOCK" -u root "$DB_NAME" < "$APP_DIR/database/migrations/20261026_add_theme_setting.sql"
fi

ROLE_COLUMN=$(mariadb --socket="$SOCK" -u root -N -B -e \
    "SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema='$DB_NAME' AND table_name='users' AND column_name='role';")
LEGACY_ROLE_COUNT=$(mariadb --socket="$SOCK" -u root -N -B -e \
    "SELECT COUNT(*) FROM \`$DB_NAME\`.\`users\` WHERE LOWER(\`role\`) NOT IN ('super_admin', 'editor');")
if [ "$ROLE_COLUMN" != "enum('super_admin','editor')" ] || [ "$LEGACY_ROLE_COUNT" != "0" ]; then
    echo "Menerapkan RBAC super_admin/editor ..."
    mariadb --socket="$SOCK" -u root "$DB_NAME" < "$APP_DIR/database/migrations/20261026_rbac_super_admin.sql"
fi

HAS_VISITS=$(mariadb --socket="$SOCK" -u root -N -B -e \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='visits';")
HAS_SEO_SETTINGS=$(mariadb --socket="$SOCK" -u root -N -B -e \
    "SELECT COUNT(*) FROM \`$DB_NAME\`.\`settings\` WHERE \`setting_key\` IN ('seo_meta_title','seo_meta_description','seo_og_image','seo_site_url');")
if [ "$HAS_VISITS" = "0" ] || [ "$HAS_SEO_SETTINGS" != "4" ]; then
    echo "Menerapkan modul SEO dan Analytics ..."
    mariadb --socket="$SOCK" -u root "$DB_NAME" < "$APP_DIR/database/migrations/20261026_seo_analytics.sql"
fi

HAS_AUDIT=$(mariadb --socket="$SOCK" -u root -N -B -e \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='activity_logs';")
HAS_LOGIN_ATTEMPTS=$(mariadb --socket="$SOCK" -u root -N -B -e \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='login_attempts';")
HAS_BACKUP_SETTINGS=$(mariadb --socket="$SOCK" -u root -N -B -e \
    "SELECT COUNT(*) FROM \`$DB_NAME\`.\`settings\` WHERE \`setting_key\` IN ('backup_schedule','backup_retention','backup_token','backup_last_run','backup_last_status','backup_last_file');")
if [ "$HAS_AUDIT" = "0" ] || [ "$HAS_LOGIN_ATTEMPTS" = "0" ] || [ "$HAS_BACKUP_SETTINGS" != "6" ]; then
    echo "Menerapkan Security Hardening, Audit Log, dan Backup Terjadwal ..."
    mariadb --socket="$SOCK" -u root "$DB_NAME" < "$APP_DIR/database/migrations/20261026_security_audit_backup.sql"
fi

echo "MariaDB siap. Socket: $SOCK | Database: $DB_NAME | User: $DB_USER"
