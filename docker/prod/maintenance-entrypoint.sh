#!/bin/sh
# One-off job only: never starts HTTP, Horizon or a scheduler with admin SQL rights.
set -eu
case "${1:-migrate}" in
    migrate|initialize) action="${1:-migrate}" ;;
    *) echo 'Usage: starter-maintenance [migrate|initialize]' >&2; exit 1 ;;
esac
[ "$#" -le 1 ] || { echo 'Unexpected maintenance arguments' >&2; exit 1; }
: "${STARTER_RUNTIME_DB_USERNAME:?Set a dedicated runtime SQL username}"
. /usr/local/bin/starter-prepare-environment
if [ "$source_mode" = files ]; then
    password_file="${STARTER_RUNTIME_DB_PASSWORD_FILE:-/run/secrets/DB_PASSWORD}"
else
    : "${STARTER_RUNTIME_DB_PASSWORD:?Set the runtime SQL password in the maintenance environment}"
    umask 077
    password_file=$(mktemp)
    trap 'rm -f "$password_file"' EXIT
    printf '%s' "$STARTER_RUNTIME_DB_PASSWORD" > "$password_file"
fi
php artisan migrate --force --no-interaction
if [ "$action" = initialize ]; then
    php artisan db:seed --class='Database\Seeders\DatabaseSeeder' --force --no-interaction
fi
php artisan starter:runtime-database "$STARTER_RUNTIME_DB_USERNAME" --password-file="$password_file" --no-interaction
