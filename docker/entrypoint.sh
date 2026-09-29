#!/bin/sh
set -e
# Lien symbolique pour le storage (Important pour Dokploy)
if [ ! -L /var/www/public/storage ]; then
    echo "Creating storage link..."
    php artisan storage:link
fi
# Cache (Optimisation)
# php artisan config:cache
# php artisan route:cache
php artisan optimize:clear
composer dump-autoload
# Migration (Optionnel, décommentez si vous voulez que ça se fasse auto)
# php artisan migrate --force

exec "$@"
