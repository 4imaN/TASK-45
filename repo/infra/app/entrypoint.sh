#!/bin/bash
set -e

# Ensure .env exists (may be missing if .gitignore excluded it from COPY)
if [ ! -f /var/www/.env ]; then
    cp /var/www/.env.example /var/www/.env
    echo ".env created from .env.example"
fi

echo "Waiting for MySQL..."
while ! php -r "try { new PDO('mysql:host=mysql;port=3306;dbname=campus_platform', 'campus', 'campus_secret'); echo 'ok'; } catch(Exception \$e) { exit(1); }" 2>/dev/null; do
    sleep 1
done
echo "MySQL is ready."

# Bootstrap secrets
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"
"$SCRIPT_DIR/bootstrap-secrets.sh"

# Bootstrap HTTPS certs
"$SCRIPT_DIR/bootstrap-https.sh"

# Only the main app process runs migrations/seeding/caching.
# Worker and scheduler skip this to avoid race conditions.
if [ $# -eq 0 ] || [ "$1" = "php-fpm" ]; then
    echo "Running migrations..."
    php artisan migrate --force

    echo "Seeding database..."
    php artisan db:seed --force

    echo "Caching config..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    echo "Bootstrap complete."
else
    # Worker/scheduler: wait for migrations to be done (check for a key table)
    echo "Waiting for app to finish migrations..."
    while ! php -r "try { (new PDO('mysql:host=mysql;port=3306;dbname=campus_platform', 'campus', 'campus_secret'))->query('SELECT 1 FROM users LIMIT 1'); echo 'ok'; } catch(Exception \$e) { exit(1); }" 2>/dev/null; do
        sleep 2
    done
    echo "Migrations ready."
fi

# If a command was passed (e.g. from docker-compose command:), execute it.
# Otherwise start PHP-FPM as the default.
if [ $# -gt 0 ]; then
    exec "$@"
else
    exec php-fpm
fi
