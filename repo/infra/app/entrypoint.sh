#!/bin/bash
# Note: deliberately NOT using `set -e`. A migration/seed failure (e.g. a partial
# previous run left a half-created table) must NOT crash the container or we get
# a restart loop. We log failures and keep PHP-FPM running — tests and admins
# can diagnose and recover from there.
set -u

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
    php artisan migrate --force || echo "WARN: migrations reported a failure (continuing; may be partial from a prior interrupted run)"

    echo "Seeding database..."
    php artisan db:seed --force || echo "WARN: seeding reported a failure (continuing)"

    echo "Caching config..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true

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
