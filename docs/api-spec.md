# Campus Resource Lending Platform - API Specification

Base URL: `https://localhost/api`

All endpoints except `POST /auth/login` require a Sanctum bearer token in the `Authorization: Bearer <token>` header.

State-changing endpoints require an `X-Idempotency-Key` header for replay/conflict detection.

---

## Authentication

### `POST /auth/login`
Authenticate and receive a bearer token.

**Request:**
```json
{
  "username": "string (required)",
  "password": "string (required)"
}
```

**Response (200):**
```json
{
  "user": { "id", "username", "display_name", "roles", ... },
  "token": "string",
  "force_password_change": "boolean"
}
```

**Errors:** `401` invalid credentials, `403` account suspended/locked.

---

### `POST /auth/logout`
Revoke the current token.

**Response (200):** `{ "message": "Logged out." }`

---

### `GET /auth/me`
Return the authenticated user profile with reminder count.

**Response (200):**
```json
{
  "id": 1,
  "username": "student",
  "display_name": "John Doe",
  "roles": ["student"],
  "reminders_count": 2
}
```

---

### `POST /auth/change-password`
Change the current user's password. Required on first login when `force_password_change` is true.

**Request:**
```json
{
  "current_password": "string (required unless force_password_change)",
  "new_password": "string (required, min:8)"
}
```

---

## User Context

### `GET /my-classes`
Returns classes the authenticated user is enrolled in (derived from permission scopes).

**Response (200):** Array of class objects with nested course.

---

### `GET /reminders`
Returns unacknowledged reminder events for the current user.

**Response (200):** Array of reminder event objects.

---

### `POST /reminders/{reminder}/acknowledge`
Mark a reminder as acknowledged.

**Response (200):** `{ "message": "Acknowledged." }`

---

## Catalog

### `GET /catalog`
Browse resources with filtering and pagination.

**Query params:**
| Param | Type | Description |
|-------|------|-------------|
| `resource_type` | string | Filter: `equipment`, `venue`, `entitlement_package` |
| `category` | string | Filter by category |
| `department_id` | integer | Filter by department |
| `search` | string | Search name/description |
| `per_page` | integer | Items per page (default: 20) |

**Response (200):** Paginated resource collection. Students cannot see `is_sensitive` or `delisted` resources.

**Resource object:**
```json
{
  "id": 1,
  "name": "Canon EOS R5",
  "description": "Full-frame mirrorless camera",
  "resource_type": "equipment",
  "type": "equipment",
  "category": "Photography",
  "subcategory": "Cameras",
  "department": "Media Arts",
  "department_id": 1,
  "vendor": "Canon",
  "manufacturer": "Canon Inc.",
  "model_number": "EOS-R5",
  "status": "active",
  "tags": ["camera", "video"],
  "available_quantity": 3,
  "loan_rules": {
    "max_renewals": 1,
    "max_loan_days": 7
  },
  "created_at": "2024-01-15T..."
}
```

---

### `GET /catalog/{resource}`
Detailed view of a single resource including availability breakdown, lot details, and venue slots (if applicable).

**Response (200):** Resource object with additional `availability` and optional `venue` data.

---

## Loans

### `GET /loans`
List loan requests. Students see their own. Staff see requests within their permission scope.

**Query params:**
| Param | Type | Description |
|-------|------|-------------|
| `status` | string | Filter by status |
| `search` | string | Search by user name/username |

**Response (200):** Paginated loan request collection.

**LoanRequest object:**
```json
{
  "id": 1,
  "user": { "id", "username", "display_name" },
  "resource": { "id", "name", "type", "loan_rules": { "max_renewals", "max_loan_days" } },
  "quantity": 1,
  "status": "checked_out",
  "requested_at": "2024-...",
  "due_date": "2024-...",
  "notes": "For photo project",
  "approval": { ... },
  "checkout": {
    "id": 1,
    "checked_out_at": "2024-...",
    "due_date": "2024-...",
    "returned_at": null,
    "is_overdue": false,
    "renewal_count": 0,
    "quantity": 1,
    "condition_at_checkout": "good"
  },
  "created_at": "2024-..."
}
```

---

### `POST /loans`
Create a loan request. Students only. Blocked if user has an active hold.

**Request:**
```json
{
  "resource_id": "integer (required)",
  "inventory_lot_id": "integer (required)",
  "quantity": "integer (required, min:1)",
  "notes": "string (optional)",
  "idempotency_key": "string (required)",
  "class_id": "integer (optional)",
  "assignment_id": "integer (optional)"
}
```

**Response (201):** LoanRequest object.
**Errors:** `403` not a student or hold active, `422` validation/business rule failure.

---

### `GET /loans/{loan}`
View a single loan request with full details.

**Authorization:** Owner, admin, or staff with matching scope.

---

### `POST /loans/{loan}/approve`
Approve or reject a loan request.

**Request:**
```json
{
  "status": "approved|rejected (required)",
  "reason": "string (optional)"
}
```

**Authorization:** Admin or teacher/TA with matching scope.

---

### `POST /loans/{loan}/checkout`
Perform physical checkout. Creates a Checkout record, decrements inventory.

**Authorization:** Admin or staff with scope. Loan must be in `approved` status.

**Response (201):** Checkout object.

---

### `POST /checkouts/{checkout}/checkin`
Return an item. Increments inventory.

**Request:**
```json
{
  "condition": "string (optional: new, good, fair, poor)",
  "notes": "string (optional)"
}
```

---

### `POST /checkouts/{checkout}/renew`
Renew a checkout. Extends due date. Limited by `max_renewals` from membership tier.

**Constraints:**
- Renewal count must be below `max_renewals`
- Cannot renew if others are on the waitlist

**Response (200):** `{ "message": "Renewed.", "new_due_date": "2024-..." }`

---

### `GET /checkouts`
Staff-only list of all checkouts within scope.

**Query params:** `status` (active), `search` (user name)

---

## Reservations

### `GET /reservations`
List reservations. Students see their own. Staff see within scope.

**Response (200):** Paginated reservation collection.

---

### `POST /reservations`
Create a reservation. Students only. Blocked if user has an active hold.

**For equipment:**
```json
{
  "resource_id": "integer (required)",
  "reservation_type": "equipment (required)",
  "start_date": "date (required)",
  "end_date": "date (required, after_or_equal start_date)",
  "notes": "string (optional)",
  "idempotency_key": "string (required)",
  "class_id": "integer (optional)",
  "assignment_id": "integer (optional)"
}
```

**For venue:**
```json
{
  "resource_id": "integer (required)",
  "reservation_type": "venue (required)",
  "venue_id": "integer (required)",
  "venue_time_slot_id": "integer (required)",
  "notes": "string (optional)",
  "idempotency_key": "string (required)",
  "class_id": "integer (optional)",
  "assignment_id": "integer (optional)"
}
```

**Validation rules for `assignment_id`:**
- If provided, the assignment must exist and belong to the specified `class_id`
- If `class_id` is omitted, it is auto-resolved from the assignment
- Student must have a permission scope covering the assignment's class or course
- Students with class/course scopes must provide class context (class_id or assignment_id)

**Response (201):** Reservation object.
**Errors:** `422` business rule violation (slot conflict, inventory exhausted, scope mismatch).

---

### `GET /reservations/{reservation}`
View a single reservation.

---

### `POST /reservations/{reservation}/approve`
Approve or reject a reservation.

**Request:** `{ "status": "approved|rejected", "reason": "string (optional)" }`

**Authorization:** Admin or teacher/TA with matching scope. Teachers with assignment scope can only approve if the assignment belongs to the reservation's class.

---

### `POST /reservations/{reservation}/cancel`
Cancel a reservation. Releases venue time slot if applicable.

**Authorization:** Owner or admin.

---

## Transfers

### `GET /transfers`
List inter-department transfer requests within scope.

---

### `POST /transfers`
Initiate a transfer request.

**Request:**
```json
{
  "inventory_lot_id": "integer (required)",
  "from_department_id": "integer (required)",
  "to_department_id": "integer (required, different from from_department_id)",
  "quantity": "integer (optional, default:1)",
  "reason": "string (optional)",
  "idempotency_key": "string (required)"
}
```

---

### `POST /transfers/{transfer}/approve`
Approve a pending transfer.

### `POST /transfers/{transfer}/in-transit`
Mark transfer as in-transit.

### `POST /transfers/{transfer}/complete`
Complete the transfer. Updates inventory lot department.

### `POST /transfers/{transfer}/cancel`
Cancel the transfer.

---

## Memberships

### `GET /memberships/tiers`
List all membership tiers.

**Response (200):** Array of tier objects with `max_active_loans`, `max_loan_days`, `max_renewals`, `points_multiplier`.

---

### `GET /memberships/me`
Current user's membership details including points balance, stored value, entitlements, and loan rules.

**Response (200):**
```json
{
  "membership": {
    "tier_name": "Plus",
    "tier": { "max_active_loans": 5, "max_loan_days": 14, "max_renewals": 2 },
    "status": "active",
    "expires_at": "2025-..."
  },
  "points_balance": 150,
  "stored_value_cents": 2500,
  "entitlements": [
    { "package_name": "Print Credits", "remaining_quantity": 45, "unit": "pages", "expires_at": "..." }
  ],
  "loan_rules": { "max_active": 5, "max_days": 14, "max_renewals": 2 }
}
```

---

### `GET /memberships/packages`
List all available entitlement packages.

---

### `POST /memberships/redeem-points`
Spend points.

**Request:** `{ "points": 50, "description": "Reward redemption" }`

---

### `POST /memberships/redeem-stored-value`
Redeem stored value balance.

**Request:** `{ "amount_cents": 500, "description": "Service payment", "idempotency_key": "..." }`

---

### `POST /memberships/entitlements/{grant}/consume`
Consume units from an entitlement grant.

**Request:** `{ "quantity": 10 }`

---

## Recommendations

### `POST /recommendations/for-class`
Generate resource recommendations for a class.

**Request:** `{ "class_id": "integer (optional)" }`

**Response (200):**
```json
{
  "batch_id": 1,
  "recommendations": [
    {
      "resource": { "id", "name", ... },
      "rank": 1,
      "score": 0.95,
      "factors": [
        { "type": "course_enrollment_match", "label": "Course match", "score": 0.6 }
      ]
    }
  ]
}
```

---

### `GET /recommendations/batches/{batch}`
View full trace data for a recommendation batch.

---

### `POST /recommendations/override`
Record a manual override for a recommendation.

**Request:**
```json
{
  "batch_id": "integer (required)",
  "resource_id": "integer (required)",
  "override_type": "string (required)",
  "reason": "string (required, min:10)"
}
```

---

## Data Quality (Staff Only)

All endpoints under `/data-quality` require the `staff` gate (admin, teacher, or TA).

### `GET /data-quality/stats`
Data quality dashboard statistics.

**Response (200):**
```json
{
  "total_records": 150,
  "records_with_issues": 5,
  "duplicate_candidates": 3,
  "completeness_pct": 87,
  "field_stats": { "description": 92, "category": 100, "vendor": 78, ... }
}
```

---

### `POST /data-quality/import`
Import or validate resource data from CSV/JSON.

**Request (multipart/form-data):**
| Field | Type | Description |
|-------|------|-------------|
| `file` | file | CSV or JSON file (optional if `rows` provided) |
| `rows` | array | Inline row data (optional if `file` provided) |
| `type` | string | Import type. Only `resources` is supported. Other values return `422`. |
| `validate_only` | string | Set to `"1"` for dry-run validation without persistence |

**Response (200):**
```json
{
  "batch_id": 1,
  "filename": "items.csv",
  "validate_only": true,
  "summary": { "total_rows": 10, "valid": 8, "invalid": 1, "duplicates": 1 },
  "issues": [
    { "row": 3, "status": "invalid", "errors": ["Name is required."], "data": {...} }
  ]
}
```

**Behavior:**
- `validate_only=1`: Creates batch with status `validated`, no Resource records created
- `validate_only=0` (default): Creates batch with status `completed`, valid rows persisted as Resources
- `type=users|memberships|loans`: Returns `422` with error message

---

### `GET /data-quality/batches`
List import batches (paginated).

---

### `GET /data-quality/batches/{batch}`
View validation report for a specific batch.

---

### `GET /data-quality/batches/{batch}/download`
Download the validation report as a JSON file attachment.

**Response:** JSON file with `Content-Disposition: attachment` header.

---

### `GET /data-quality/remediation`
List items in the remediation queue (invalid/duplicate rows).

---

### `POST /data-quality/remediation/{item}`
Act on a remediation item.

**Request:** `{ "action": "remediate|skip" }`

---

### `GET /data-quality/duplicates`
List pending duplicate candidates.

---

### `POST /data-quality/duplicates/{candidate}`
Resolve a duplicate candidate.

**Request:** `{ "action": "confirmed|dismissed" }`

---

### `GET /data-quality/vendor-aliases`
### `POST /data-quality/vendor-aliases`
### `PUT /data-quality/vendor-aliases/{alias}`

Manage vendor name aliases for normalization.

---

### `GET /data-quality/manufacturer-aliases`
### `POST /data-quality/manufacturer-aliases`
### `PUT /data-quality/manufacturer-aliases/{alias}`

Manage manufacturer name aliases for normalization.

---

## Administration (Admin Only)

All endpoints under `/admin` require the `admin` gate.

### `GET /admin/stats`
Dashboard statistics.

**Response (200):**
```json
{
  "total_users": 50,
  "total_resources": 120,
  "total_members": 35,
  "active_loans": 12,
  "pending_approvals": 3,
  "active_holds": 1,
  "overdue_items": 2,
  "recent_audit": [...]
}
```

---

### `POST /admin/scopes`
Assign a permission scope to a user.

**Request:**
```json
{
  "user_id": "integer (required)",
  "scope_type": "full|course|class|assignment|department (required)",
  "course_id": "integer (required if scope_type=course)",
  "class_id": "integer (required if scope_type=class)",
  "assignment_id": "integer (required if scope_type=assignment)",
  "department_id": "integer (required if scope_type=department)"
}
```

---

### `GET /admin/scopes`
List all permission scopes (paginated).

### `GET /admin/scopes/user?user={username_or_id}`
List scopes for a specific user.

### `DELETE /admin/scopes/{scope}`
Remove a permission scope.

---

### `POST /admin/allowlists`
Add a user to the allowlist.

**Request:**
```json
{
  "scope_type": "department|global (required)",
  "scope_id": "integer (required)",
  "user_id": "integer (required)",
  "reason": "string (required)"
}
```

**Note:** Only `department` and `global` scope types are accepted. Other values return `422`.

### `GET /admin/allowlists`
### `DELETE /admin/allowlists/{allowlist}`

---

### `POST /admin/blacklists`
Add a user to the blacklist.

**Request:**
```json
{
  "scope_type": "department|global (required)",
  "scope_id": "integer (required)",
  "user_id": "integer (required)",
  "reason": "string (required)",
  "expires_at": "datetime (optional)"
}
```

**Note:** Only `department` and `global` scope types are accepted. Other values return `422`.

### `GET /admin/blacklists`
### `DELETE /admin/blacklists/{blacklist}`

---

### `GET /admin/holds`
List active account holds.

### `POST /admin/holds`
Create a manual hold.

**Request:**
```json
{
  "user_id": "integer (required)",
  "hold_type": "manual|system (required)",
  "reason": "string (required, min:5)",
  "expires_at": "datetime (optional, must be future)"
}
```

### `POST /admin/holds/{hold}/release`
Release an active hold.

**Request:** `{ "reason": "string (required, min:5)" }`

---

### `GET /admin/audit-logs`
Query audit logs with filtering and pagination.

**Query params:**
| Param | Type | Description |
|-------|------|-------------|
| `user_id` | integer | Filter by actor |
| `action` | string | Exact action match |
| `event` | string | Partial action match (LIKE) |
| `search` | string | Search user display_name, username, or action |
| `range` | string | `today`, `week`, or `month` |
| `page` | integer | Page number |
| `per_page` | integer | Items per page (default: 25, max: 100) |

**Response (200):** Paginated audit log entries with eager-loaded `user` relation.

```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "user": { "id": 1, "username": "admin", "display_name": "Admin User" },
      "action": "scope_assigned",
      "auditable_type": "App\\Models\\PermissionScope",
      "auditable_id": 5,
      "ip_address": "192.168.1.10",
      "context": { "reason": "..." },
      "created_at": "2024-..."
    }
  ],
  "meta": { "current_page": 1, "last_page": 5, "total": 120 }
}
```

---

### `GET /admin/audit-logs/export`
Export audit logs as CSV.

**Query params:** `user_id`, `action`, `from`, `to` (date filters).

**Response:** CSV file download.

---

### `POST /admin/reveal-field`
Reveal encrypted PII fields. Creates an audit trail entry.

**Request:**
```json
{
  "model_type": "User (or App\\Models\\User)",
  "model_id": "integer (required)",
  "fields": ["email", "phone"],
  "reason": "string (required, min:5)"
}
```

**Revealable models/fields:** Only `User.email` and `User.phone`.

**Authorization:** Requires admin with full scope, or overlapping course/class scope with the target user.

**Response (200):**
```json
{
  "revealed": { "email": "user@example.com", "phone": "555-1234" }
}
```

---

### `GET /admin/interventions`
List intervention log entries.

---

### Admin Membership Management

### `POST /admin/memberships/assign-tier`
**Request:** `{ "user_id": integer, "tier_id": integer }`

### `POST /admin/memberships/deposit`
**Request:** `{ "user_id": integer, "amount_cents": integer, "description": string }`

### `POST /admin/memberships/grant-entitlement`
**Request:** `{ "user_id": integer, "package_id": integer }`

---

## Files

### `POST /files/upload`
Upload a file, optionally attached to a loan request, reservation, or resource.

**Request (multipart/form-data):**
| Field | Type | Description |
|-------|------|-------------|
| `file` | file | Required. Max 10MB. Allowed: pdf, jpg, jpeg, png |
| `attachable_type` | string | Optional: `loan_request`, `reservation`, `resource` |
| `attachable_id` | integer | Required if attachable_type is set |

**Authorization:** Students can only attach to their own loans/reservations. Only staff/admin can attach to resources.

---

### `GET /files`
List files visible to the current user. Students see their own. Staff see files within scope.

---

### `GET /files/{file}/download`
Download a file. Verifies access authorization and logs the access.

---

## Error Responses

All errors follow the format:

```json
{
  "error": "Human-readable error message"
}
```

Or for validation errors (422):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error description"]
  }
}
```

| Code | Meaning |
|------|---------|
| `401` | Unauthenticated (missing/invalid token) |
| `403` | Unauthorized (insufficient role/scope) |
| `404` | Resource not found |
| `409` | Conflict (idempotency key replay) |
| `422` | Validation or business rule failure |
| `500` | Internal server error |

## Idempotency

All state-changing endpoints require an `X-Idempotency-Key` header. The key is scoped per user:

- **First request:** Processed normally, response cached
- **Replay (same key, same user):** Returns cached response (200) without re-executing
- **Conflict (same key, different payload hash):** Returns `409 Conflict`

Keys are stored in the `idempotency_keys` table with a composite unique constraint on `(user_id, key)`.
