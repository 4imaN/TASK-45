# Campus Resource Lending & Membership Operations Platform

An offline-first, full-stack Laravel + Vue.js application for managing shared equipment, venues, and entitlements across departments in educational organizations.

## Quick Start

```bash
docker compose up --build
```

The platform will be available at **https://localhost** (self-signed certificate).

## Test Accounts

The following accounts are seeded on first boot with static passwords for testing:

| Role    | Username  | Password      |
|---------|-----------|---------------|
| Admin   | `admin`   | `Admin123!`   |
| Teacher | `teacher` | `Teacher123!` |
| TA      | `ta`      | `TA123!`      |
| Student | `student` | `Student123!` |

## Architecture

- **Backend**: Laravel 13 (PHP 8.3) with REST APIs
- **Frontend**: Vue 3 + Pinia + Vue Router + Tailwind CSS
- **Database**: MySQL 8.0
- **Queue**: Database-backed local queue with worker container
- **Scheduler**: Laravel scheduler for reminders and hold expiry
- **HTTPS**: Self-signed certificates generated on first boot

## Services

| Service   | Purpose                                      |
|-----------|----------------------------------------------|
| app       | Laravel PHP-FPM application                  |
| web       | Nginx HTTPS reverse proxy                    |
| worker    | Queue worker (retries, reports, reminders)   |
| scheduler | Scheduled tasks (reminders, hold expiry)     |
| mysql     | MySQL 8.0 database                           |

## Core Features

- **Catalog & Availability**: Browse resources/venues with real-time available quantity
- **Lending**: Request, approve, checkout, check-in, renew with 7-day default loans
- **Reservations**: Venue time-slot booking with conflict detection
- **Transfers**: Inter-department resource movement with chained custody records
- **Membership**: Basic/Plus/Premium tiers with stored value, points, and entitlements
- **Data Quality**: Bulk import with duplicate detection, taxonomy validation, prohibited word checks
- **Recommendations**: Class-aware suggestions with explainable rule traces
- **Administration**: Scope management, blacklists/allowlists, holds, audit logs
- **Security**: Salted/hashed passwords, masked sensitive fields, encrypted at rest, HTTPS

## Concurrency & Safety

- Transactional writes with row-level locking on inventory lots
- Idempotency keys on all state-changing requests
- Exponential backoff retries (max 3 attempts) via database queue
- Deterministic conflict responses

## Running Tests

### Fast suite (SQLite in-memory, sync queue)
Runs all Unit, Feature, and Integration tests against SQLite for speed:

```bash
# Via Docker (recommended)
docker compose exec app vendor/bin/phpunit

# Frontend
docker compose exec app npx vitest run

# Or run everything via the test script
./run_tests.sh
```

### MySQL integration suite (production-parity)
Runs the Integration tests against a real MySQL instance to verify:
- Row-level locking (SELECT ... FOR UPDATE)
- Composite unique constraints on idempotency keys
- DateTime column precision for due dates
- Transaction rollback behavior

```bash
# Create the test database first
docker compose exec mysql mysql -uroot -proot_secret -e "CREATE DATABASE IF NOT EXISTS campus_platform_test;"

# Run integration tests against MySQL
docker compose exec app vendor/bin/phpunit -c phpunit.mysql.xml
```

### What each suite covers

| Guarantee | Fast (SQLite) | MySQL Integration |
|-----------|:---:|:---:|
| Business rule enforcement | ✓ | ✓ |
| Authorization / scope checks | ✓ | ✓ |
| Idempotency middleware replay/conflict | ✓ | ✓ |
| Scoped idempotency uniqueness (user+key) | N/A (SQLite global unique) | **composite unique enforced** |
| Transaction rollback | ✓ | ✓ |
| Availability math | ✓ | ✓ |
| Row-level locking (FOR UPDATE) | logic only | **lock contention proven via two-connection test** |
| Composite unique constraints | N/A (SQLite skip) | **enforced** |
| DateTime precision | cast-level | **column-level** |

### Manual verification required
- Sustained concurrent load testing (the two-connection lock test proves the mechanism; load-scale contention requires a dedicated harness)
- Queue retry behavior with exponential backoff (requires async worker runtime)
- Docker compose end-to-end startup sequence

## File Structure

```
app/Domain/         - Business logic services
app/Http/           - Controllers, middleware, requests, resources
app/Models/         - Eloquent models
app/Policies/       - Authorization policies
app/Jobs/           - Queue jobs (reminders, reports)
app/Console/        - Artisan commands
resources/js/app/   - Vue.js SPA
infra/              - Docker and bootstrap scripts
database/           - Migrations, seeders, factories
tests/              - PHPUnit feature and unit tests
```

## Offline-First Architecture

This platform is designed to run entirely from local infrastructure with zero external dependencies:

- **No cloud services**: No external storage, CDN, search, identity provider, or message broker
- **No internet required**: All services (API, database, queue, scheduler) run locally in Docker
- **Local authentication**: Username/password auth with bcrypt hashing — no external IdP or SSO
- **Local queue**: Database-backed queue worker for retries and async jobs — no Redis or cloud queues
- **Local file storage**: Uploads stored on the local filesystem with checksum verification
- **Local recommendations**: Scoring based on local enrollment and checkout data — no external ML/AI
- **Self-contained startup**: `docker compose up --build` from a clean clone with no manual env setup

The Vue.js SPA communicates exclusively with the co-located Laravel API over the local network. Auth state (bearer token) is persisted in the browser for session continuity. All business data lives in the local MySQL instance.

## Local Network HTTPS

The platform generates a self-signed certificate on first boot. Your browser will show a security warning — this is expected for local network deployments. Accept the certificate to proceed.

## Security

All API traffic is served over HTTPS.

### Encryption at Rest
The following fields are encrypted at rest using Laravel's `encrypted` cast with the application key:

- **User PII**: `users.email`, `users.phone`
- **Financial ledger descriptions**: `stored_value_ledger.description` (may contain PII or transaction context)
- **File metadata**: `file_assets.storage_path`, `file_assets.original_filename` (prevents path disclosure)

Financial ledger numeric fields (`amount_cents`, `balance_after_cents`, `points`, `balance_after`) use exact integer storage and are not encrypted to preserve query and aggregation capability.
File assets are additionally protected with SHA-256 checksums for integrity verification.
