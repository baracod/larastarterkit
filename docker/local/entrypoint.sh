#!/bin/sh
set -eu
if [ "${APP_ENV:-}" != local ] || [ "${DB_HOST:-}" != db ] || [ "${DB_DATABASE:-}" != starter_docker_local ]; then
    echo 'This entrypoint only accepts the isolated local Docker database.' >&2
    exit 1
fi
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
if [ "$(id -u)" = 0 ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi
php artisan config:cache --no-interaction
exec "$@"
