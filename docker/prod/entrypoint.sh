#!/bin/sh
set -eu
. /usr/local/bin/starter-prepare-environment
php artisan starter:runtime-access --no-interaction
exec "$@"
