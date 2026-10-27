#!/bin/sh
set -eu

SOCK="${WEB_MQ_DB_SOCKET:-/tmp/webmq-mariadb.sock}"
PIDFILE="/tmp/webmq-mariadb.pid"

if mariadb-admin --socket="$SOCK" -u root ping >/dev/null 2>&1; then
    mariadb-admin --socket="$SOCK" -u root shutdown
    echo "MariaDB web-mqannuur dihentikan."
else
    echo "MariaDB web-mqannuur tidak sedang berjalan."
fi

rm -f "$PIDFILE"
