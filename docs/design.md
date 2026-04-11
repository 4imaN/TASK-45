# Campus Resource Lending Platform - System Design

## 1. Overview

An offline-first, full-stack web platform for managing shared equipment, venues, and entitlements across departments in educational organizations. Built with Laravel 13 (PHP 8.3) + Vue 3 SPA, deployed as a Docker Compose stack on a local network.

**Key design principle:** Zero external dependencies. All services (API, database, queue, scheduler) run locally with no cloud services, CDN, identity provider, or message broker.

## 2. Architecture

### 2.1 System Topology

```
 Browser (Vue 3 SPA)
       |
    HTTPS (self-signed cert)
       |
   +---------+
   |  Nginx  |  (web container - reverse proxy)
   +---------+
       |
   +---------+       +-----------+
   | Laravel |------>|  MySQL 8  |
   | PHP-FPM |       +-----------+
   +---------+
       |
   +--------+   +-----------+
   | Worker |   | Scheduler |
   +--------+   +-----------+
```

| Container | Purpose |
|-----------|---------|
| `app` | Laravel PHP-FPM application server |
| `web` | Nginx HTTPS reverse proxy |
| `worker` | Database-backed queue worker (retries, async jobs) |
| `scheduler` | Laravel scheduler (reminders, hold expiry) |
| `mysql` | MySQL 8.0 database |

### 2.2 Technology Stack

- **Backend:** Laravel 13, PHP 8.3, Sanctum (token auth)
- **Frontend:** Vue 3, Pinia (state), Vue Router, Tailwind CSS, Vite
- **Database:** MySQL 8.0 (SQLite for test suite)
- **Queue:** Database-backed, exponential backoff (max 3 retries)
- **Auth:** Username/password with bcrypt, Sanctum bearer tokens

## 3. Domain Model

### 3.1 Core Entities

```
Department
  |-- Resource (equipment | venue | entitlement_package)
  |     |-- InventoryLot (quantity tracking per location)
  |     |-- Venue --> VenueTimeSlot
  |     |-- LoanRequest --> Checkout --> Renewal
  |     '-- ReservationRequest --> Approval
  |
Course --> ClassModel --> Assignment
  |
User
  |-- Role (admin | teacher | ta | student)
  |-- PermissionScope (full | course | class | assignment | department)
  |-- Membership --> MembershipTier
  |-- PointsLedger
  |-- StoredValueLedger
  |-- EntitlementGrant --> EntitlementConsumption
  |-- Hold (frequency | high_value | manual | system)
  |-- FileAsset
  '-- Blacklist / Allowlist
```

### 3.2 Resource Types

| Type | Description | Booking Mechanism |
|------|-------------|-------------------|
| `equipment` | Physical items (cameras, laptops, tools) | Loan request with quantity, date range, inventory-lot availability |
| `venue` | Rooms, studios, labs | Reservation with venue time-slot selection |
| `entitlement_package` | Consumable service grants (print credits, hours) | Entitlement consumption against a grant |

### 3.3 Status Enums

**Resource status:** `active`, `delisted`, `sensitive`, `maintenance`

**Loan request status:** `pending` -> `approved` -> `checked_out` -> `returned` (or `rejected` / `cancelled`)

**Reservation status:** `pending` -> `approved` (or `rejected` / `cancelled`)

**Transfer status:** `pending` -> `approved` -> `in_transit` -> `completed` (or `cancelled`)

**Membership status:** `active`, `suspended`, `expired`

**Hold status:** `active`, `released`, `expired`

## 4. Authorization Model

### 4.1 Roles

| Role | Capabilities |
|------|-------------|
| `admin` | Full system access. Manage scopes, holds, allowlists/blacklists, reveal encrypted fields, import data, assign memberships. |
| `teacher` | Approve/reject loans and reservations within their scoped classes/courses. View scoped audit data. |
| `ta` | Same as teacher but typically with narrower scope (assignment-level). |
| `student` | Request loans, reserve venues, view own data, consume entitlements. Cannot see sensitive resources. |

### 4.2 Permission Scopes

Each user can have one or more `PermissionScope` records controlling what data they can see and act on:

| Scope Type | Foreign Key | Effect |
|------------|------------|--------|
| `full` | none | Unrestricted access (admin equivalent for non-admin roles) |
| `course` | `course_id` | Access to all classes/assignments under that course |
| `class` | `class_id` | Access to a specific class section and its assignments |
| `assignment` | `assignment_id` | Access to a single assignment's loan/reservation data |
| `department` | `department_id` | Department-level scope for transfers and resource management |

Scope resolution is hierarchical: `full` > `course` > `class` > `assignment`.

### 4.3 Access Control Lists

**Allowlist** (department, global): When allowlist entries exist for a department, only listed users can access that department's resources.

**Blacklist** (department, global): Explicitly blocked users. Takes precedence. Supports optional expiration.

Enforcement only occurs for `department` and `global` scope types. The API validates and rejects other scope types.

### 4.4 Reservation Assignment Validation

When a student submits a reservation with an `assignment_id`:
1. The assignment must exist.
2. If `class_id` is also provided, the assignment must belong to that class.
3. If `class_id` is omitted, it is auto-resolved from the assignment.
4. The student must have a permission scope covering the assignment's class or course.

Teachers/TAs can only approve reservations where the assignment's class matches the reservation's class and falls within their scope.

## 5. Key Workflows

### 5.1 Lending Lifecycle

```
Student creates LoanRequest
  -> pending (staff sees it in approval queue)
  -> Staff approves or rejects
  -> If approved: staff performs Checkout (decrement inventory, set due date)
  -> Student uses item
  -> Staff performs Checkin (increment inventory, record condition)
  -> Optional: Renewal before due date (extends due date, limited by tier)
```

**Availability calculation:** `serviceable_quantity - active_checkouts - approved_loans - overlapping_reservations - in_transit_transfers`

**Renewal rules:**
- Controlled by `MembershipTier.max_renewals` (default: 1)
- Blocked if other users are on the waitlist for that resource

### 5.2 Venue Reservation

```
Student selects resource (type=venue) -> picks venue -> picks time slot
  -> System locks slot (SELECT FOR UPDATE), checks availability
  -> Creates ReservationRequest, marks slot as occupied
  -> Staff approves/rejects
  -> On cancellation/rejection: slot is released
```

### 5.3 Inter-Department Transfer

```
Staff initiates TransferRequest (from_dept -> to_dept, quantity)
  -> Scoped staff approves
  -> Mark in-transit (creates CustodyRecord)
  -> Complete (updates inventory lot department, creates receiving CustodyRecord)
```

### 5.4 Data Quality Import

```
Staff uploads CSV/JSON file
  -> System validates rows (required fields, taxonomy, prohibited terms, duplicates)
  -> If validate_only=true: returns report only, no persistence
  -> If validate_only=false: valid rows create Resource records
  -> Batch status: 'validated' (dry run) or 'completed' (persisted)
  -> Remediation queue for invalid/duplicate rows
  -> Downloadable JSON validation report per batch
```

Only the `resources` import type is currently implemented. Other types (users, memberships, loans) are not supported and are rejected by the API.

### 5.5 Membership & Stored Value

```
Admin assigns MembershipTier to user
  -> Tier controls: max_active_loans, max_loan_days, max_renewals, points_multiplier
  -> Admin deposits stored value (cents)
  -> Student redeems stored value or points
  -> Admin grants entitlement packages -> Student consumes units
```

## 6. Concurrency & Safety

| Mechanism | Where Applied |
|-----------|--------------|
| Transactional writes (`DB::transaction`) | All state-changing service methods |
| Row-level locking (`lockForUpdate`) | Venue time slots, reservations, checkouts, inventory lots |
| Idempotency keys | All state-changing API endpoints (middleware enforced) |
| Exponential backoff | Queue worker (max 3 retries) |
| Deterministic conflict responses | Double-booking, slot conflicts, idempotency replays |

## 7. Security

### 7.1 Authentication

- Bcrypt-hashed passwords (cost factor 12)
- Sanctum bearer tokens (stateless API auth)
- Force password change on first login for bootstrap accounts
- Account lockout after repeated failed attempts

### 7.2 Encryption at Rest

| Field | Model | Encryption |
|-------|-------|-----------|
| `email` | User | Laravel `encrypted` cast |
| `phone` | User | Laravel `encrypted` cast |
| `description` | StoredValueLedger | Laravel `encrypted` cast |
| `storage_path` | FileAsset | Laravel `encrypted` cast |
| `original_filename` | FileAsset | Laravel `encrypted` cast |

Financial numeric fields (`amount_cents`, `balance_after_cents`, `points`, `balance_after`) use integer storage without encryption to preserve aggregation capability.

File assets use SHA-256 checksums for integrity verification.

### 7.3 Field Masking

Sensitive fields (`email`, `phone`) are hidden from API responses by default. Admins with appropriate scope can explicitly reveal them via the `POST /admin/reveal-field` endpoint, which creates an audit log entry.

### 7.4 HTTPS

Self-signed TLS certificate generated on first Docker boot. All API traffic served over HTTPS through the Nginx reverse proxy.

## 8. Audit Trail

Every significant action creates an `AuditLog` record:

| Field | Purpose |
|-------|---------|
| `user_id` | Actor who performed the action |
| `action` | Machine-readable event name (e.g. `reservation_created`, `hold_released`) |
| `auditable_type/id` | Polymorphic reference to the affected record |
| `old_values` / `new_values` | JSON snapshots of changed data |
| `ip_address`, `user_agent`, `url` | Request context |
| `context` | Additional structured metadata |

Audit logs are append-only (no `UPDATED_AT` column). Supports filtering by user, action, search text, event type, and date range. Exportable to CSV.

## 9. Frontend Architecture

### 9.1 SPA Structure

```
resources/js/app/
  views/
    student/     - LoansView, ReservationsView, MembershipView, CatalogView
    admin/       - AdminDashboard, AuditLogView, ImportView, DataQualityView,
                   AllowlistView, BlacklistView, ScopeManagementView
    staff/       - ApprovalsView, CheckoutsView, TransfersView
  stores/        - Pinia stores (loans, catalog, auth)
  services/      - API client (axios-based)
  components/    - Shared components (ConflictBanner, OverdueCountdown)
```

### 9.2 State Management

- **Pinia stores** for domain state (loans, catalog)
- **Bearer token** persisted in browser for session continuity
- **Optimistic UI** with conflict banners for error states

## 10. Testing Strategy

| Suite | Engine | Scope |
|-------|--------|-------|
| Unit | PHPUnit + SQLite | Domain services, model helpers, availability math |
| Feature | PHPUnit + SQLite | API endpoints, authorization, business rules, contract alignment |
| Integration | PHPUnit + MySQL | Row locking, composite unique constraints, datetime precision |
| Frontend | Vitest | Component logic |

262 tests, 507 assertions. All passing with 3 skips (MySQL-only features in SQLite mode).
