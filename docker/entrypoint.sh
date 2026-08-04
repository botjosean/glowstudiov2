#!/bin/sh
set -e

cd /var/www/html

php artisan config:cache
php artisan route:cache
php artisan view:cache
[ -L public/storage ] || php artisan storage:link

# Only the app container (running supervisord) runs migrations, so the
# worker/scheduler containers starting concurrently don't race each other.
case "$1" in
    *supervisord*)
        php artisan migrate --force
        ;;
esac

exec "$@"
