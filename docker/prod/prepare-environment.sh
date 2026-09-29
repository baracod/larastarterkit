#!/bin/sh
# Sourced by the runtime and the one-off maintenance entrypoints.
set -eu
source_mode="${STARTER_SECRETS_SOURCE:-files}"
case "$source_mode" in
    files|environment) ;;
    *) echo 'STARTER_SECRETS_SOURCE must be files or environment' >&2; exit 1 ;;
esac
for name in APP_KEY DB_PASSWORD REDIS_PASSWORD; do
    if [ "$source_mode" = files ]; then
        secret=$(printenv "${name}_FILE" || true)
        secret="${secret:-/run/secrets/$name}"
        [ -s "$secret" ] || { echo "Missing secret file: $name" >&2; exit 1; }
        value=$(cat "$secret")
    else
        value=$(printenv "$name" || true)
    fi
    [ -n "$value" ] || { echo "Missing secret: $name" >&2; exit 1; }
    export "$name=$value"
done
php docker/prod/check-environment.php
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
if [ "$(id -u)" = 0 ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi
php artisan config:cache --no-interaction
