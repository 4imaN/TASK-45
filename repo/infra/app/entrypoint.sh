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

# Run migrations
php artisan migrate --force

# Seed if needed
php artisan db:seed --force

# Cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# If a command was passed (e.g. from docker-compose command:), execute it.
# Otherwise start PHP-FPM as the default.
if [ $# -gt 0 ]; then
    exec "$@"
else
    exec php-fpm
fi
