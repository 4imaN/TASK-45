# Unit Tests

Unit tests are located in `tests/Unit/` (backend) and `resources/js/tests/unit/` (frontend).

## Running via Docker (recommended)
```bash
docker compose exec app vendor/bin/phpunit --testsuite=Unit
docker compose exec app npx vitest run
```

## Running locally (requires PHP 8.3+ and Node.js)
```bash
vendor/bin/phpunit --testsuite=Unit
npx vitest run
```
