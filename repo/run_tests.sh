#!/bin/bash
set -euo pipefail

echo "=== Campus Resource Platform Test Suite ==="
echo ""
echo "Running all tests via Docker containers..."
echo ""

# Ensure containers are running. Bring them up if not — CI harnesses typically
# hand off a stopped stack and expect this script to manage its own lifecycle.
if ! docker compose ps --status running app 2>/dev/null | grep -q "app"; then
    echo "Containers not running — starting the stack..."
    docker compose up -d --wait >/dev/null 2>&1 || docker compose up -d
fi

# Wait for the app container's entrypoint to finish its migrate/seed/cache sequence.
# `php artisan --version` succeeds as soon as PHP is up, but we care about the
# entrypoint printing "Bootstrap complete." — after that the config cache is
# stable and our PHPUnit invocations won't race the entrypoint.
echo "Waiting for app container bootstrap..."
for i in $(seq 1 90); do
    if docker compose logs app 2>/dev/null | grep -q "Bootstrap complete."; then
        break
    fi
    sleep 2
done
echo "App container is ready."
echo ""

BACKEND_PASS=0
MYSQL_PASS=0
FRONTEND_PASS=0

clear_config_cache() {
    # Drop any baked config so phpunit.xml <env> values actually apply.
    docker compose exec -T app rm -f bootstrap/cache/config.php 2>/dev/null || true
}

# Backend Tests — SQLite (fast path, full feature coverage)
# DB_* must be passed on the command line because the container's .env sets
# DB_CONNECTION=mysql (production) and Laravel's env loader preserves that
# unless the var is already present in the real environment.
echo "--- Running Backend Tests (SQLite) ---"
clear_config_cache
if docker compose exec -T \
    -e DB_CONNECTION=sqlite \
    -e DB_DATABASE=:memory: \
    -e DB_URL= \
    -e CACHE_STORE=array \
    -e QUEUE_CONNECTION=sync \
    -e SESSION_DRIVER=array \
    app vendor/bin/phpunit 2>&1; then
    BACKEND_PASS=1
    echo "Backend (SQLite) tests: PASSED"
else
    echo "Backend (SQLite) tests: FAILED"
fi
echo ""

# Integration Tests — MySQL (row-locking, composite unique, datetime precision)
echo "--- Running Integration Tests (MySQL) ---"
# Ensure the MySQL test database exists
docker compose exec -T mysql mysql -u root -proot_secret -e "CREATE DATABASE IF NOT EXISTS campus_platform_test; GRANT ALL ON campus_platform_test.* TO 'campus'@'%';" 2>/dev/null || true
clear_config_cache
if docker compose exec -T \
    -e DB_CONNECTION=mysql \
    -e DB_HOST=mysql \
    -e DB_PORT=3306 \
    -e DB_DATABASE=campus_platform_test \
    -e DB_USERNAME=campus \
    -e DB_PASSWORD=campus_secret \
    -e CACHE_STORE=array \
    -e QUEUE_CONNECTION=sync \
    -e SESSION_DRIVER=array \
    app vendor/bin/phpunit -c phpunit.mysql.xml 2>&1; then
    MYSQL_PASS=1
    echo "Integration (MySQL) tests: PASSED"
else
    echo "Integration (MySQL) tests: FAILED"
fi
echo ""

# Frontend Tests (unit + view + router guards + composables)
echo "--- Running Frontend Unit Tests ---"
if docker compose exec -T app npx vitest run 2>&1; then
    FRONTEND_PASS=1
    echo "Frontend unit tests: PASSED"
else
    echo "Frontend unit tests: FAILED"
fi
echo ""

# Do NOT re-cache production config here: a subsequent run of this script would
# start with that cache in place, which can leak the wrong DB into the SQLite or
# Integration-MySQL suites before we get a chance to clear it. If the live app
# needs the cache, it's rebuilt by the container entrypoint on restart.

# Summary
echo "=== Summary ==="
if [ "$BACKEND_PASS" -eq 1 ] && [ "$MYSQL_PASS" -eq 1 ] && [ "$FRONTEND_PASS" -eq 1 ]; then
    echo "ALL SUITES PASSED"
    exit 0
else
    echo "FAILURES DETECTED"
    [ "$BACKEND_PASS" -eq 0 ] && echo "  - Backend (SQLite) tests failed"
    [ "$MYSQL_PASS" -eq 0 ] && echo "  - Integration (MySQL) tests failed"
    [ "$FRONTEND_PASS" -eq 0 ] && echo "  - Frontend unit tests failed"
    exit 1
fi
