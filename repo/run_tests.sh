#!/bin/bash
set -euo pipefail

echo "=== Campus Resource Platform Test Suite ==="
echo ""
echo "Running all tests via Docker containers..."
echo ""

# Ensure containers are running
if ! docker compose ps --status running app 2>/dev/null | grep -q "app"; then
    echo "Error: Docker containers are not running."
    echo "Start them with: docker compose up --build -d"
    exit 1
fi

BACKEND_PASS=0
FRONTEND_PASS=0

# Backend Tests
echo "--- Running Backend Tests ---"
if docker compose exec -T app vendor/bin/phpunit 2>&1; then
    BACKEND_PASS=1
    echo "Backend tests: PASSED"
else
    echo "Backend tests: FAILED"
fi
echo ""

# Frontend Tests
echo "--- Running Frontend Tests ---"
if docker compose exec -T app npx vitest run 2>&1; then
    FRONTEND_PASS=1
    echo "Frontend tests: PASSED"
else
    echo "Frontend tests: FAILED"
fi
echo ""

# Summary
echo "=== Summary ==="
if [ "$BACKEND_PASS" -eq 1 ] && [ "$FRONTEND_PASS" -eq 1 ]; then
    echo "ALL SUITES PASSED"
    exit 0
else
    echo "FAILURES DETECTED"
    [ "$BACKEND_PASS" -eq 0 ] && echo "  - Backend tests failed"
    [ "$FRONTEND_PASS" -eq 0 ] && echo "  - Frontend tests failed"
    exit 1
fi
