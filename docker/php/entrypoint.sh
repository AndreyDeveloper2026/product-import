#!/bin/sh
set -e

if [ "$RUN_MIGRATIONS" = "1" ]; then
  echo "Running migrations..."
  until vendor/bin/doctrine-migrations migrate --no-interaction \
        --configuration=migrations.php --db-configuration=migrations-db.php; do
    echo "DB is not ready yet, retrying in 3s..."
    sleep 3
  done
fi

exec "$@"
