#!/bin/bash
set -e

RUNTIME_DIR="/var/www/storage/app/private/runtime"
mkdir -p "$RUNTIME_DIR"

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    if [ -f "$RUNTIME_DIR/app_key" ]; then
        export APP_KEY=$(cat "$RUNTIME_DIR/app_key")
    else
        APP_KEY=$(php artisan key:generate --show)
        echo "$APP_KEY" > "$RUNTIME_DIR/app_key"
    fi
    # Write to .env
    sed -i "s|APP_KEY=.*|APP_KEY=$APP_KEY|" /var/www/.env
fi

# Note: Laravel's encrypted casts use APP_KEY for encryption.
# No separate encryption key is needed.

echo "Secrets bootstrapped."
