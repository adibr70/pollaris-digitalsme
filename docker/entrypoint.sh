#!/bin/sh
set -e

# Wait for PostgreSQL to accept connections before running migrations.
# We use Doctrine rather than pg_isready: the image only has pdo_pgsql.
echo "[pollaris] waiting for PostgreSQL..."
tries=0
until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
    tries=$((tries + 1))
    if [ "$tries" -ge 30 ]; then
        echo "[pollaris] ERROR: the database did not answer within 60s." >&2
        exit 1
    fi
    sleep 2
done
echo "[pollaris] PostgreSQL is available."

# Only the application container runs migrations, never the worker.
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    echo "[pollaris] running migrations..."
    php bin/console doctrine:migrations:migrate \
        --no-interaction --allow-no-migration
fi

exec "$@"
