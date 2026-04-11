# API Tests

API, workflow, and security tests are in `tests/Feature/`.

## Running via Docker (recommended)
```bash
docker compose exec app vendor/bin/phpunit --testsuite=Feature
```

## Running locally (requires PHP 8.3+ and MySQL or SQLite)
```bash
vendor/bin/phpunit --testsuite=Feature
```
