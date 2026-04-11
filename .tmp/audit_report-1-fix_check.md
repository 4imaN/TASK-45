# Verification of Prior Static Audit Findings

## Verdict
- Overall result: `10/10 previously listed issues appear fixed by current static evidence`
- Boundary: This is a static verification only. I did not run the project, tests, Docker, or queue workers.

## Issue-by-Issue Verification

### 1. Forged reservation `assignment_id` scope
- Prior finding: student could submit a reservation with an unrelated `assignment_id`, and downstream policy logic trusted that assignment for staff visibility/approval.
- Current status: `Fixed`
- Evidence:
  - `app/Domain/Reservations/ReservationService.php:86-119`
  - `app/Policies/ReservationRequestPolicy.php:38-52`
  - `tests/Feature/Api/StaticAuditFixTest.php:27-151`
- Why:
  - reservation creation now resolves the assignment, enforces assignment/class consistency, auto-resolves `class_id` when needed, and rejects users without scope to the assignment’s class/course
  - reservation policy now rejects mismatched assignment/class combinations and only honors assignment scope after consistency checks
- Note:
  - the form request still only validates existence at the request layer (`app/Http/Requests/CreateReservationRequestForm.php:17-18`), but the service-layer enforcement closes the original hole

### 2. Data-quality `validate_only` mutated real data and ignored import type
- Prior finding: “Validate” and “Import” were separate in UI, but backend always wrote resources and ignored selected type.
- Current status: `Fixed`
- Evidence:
  - `app/Http/Controllers/Api/DataQualityController.php:18-85`
  - `app/Domain/DataQuality/DataQualityService.php:10-125`
  - `resources/js/app/views/admin/ImportView.vue:57-60`
  - `tests/Feature/Api/StaticAuditFixTest.php:157-235`
- Why:
  - backend now parses `validate_only`, passes it through, marks validated batches separately, and only persists resources when `validate_only` is false
  - unsupported import types are explicitly rejected
  - UI no longer exposes unsupported types and only shows `resources`

### 3. Encryption-at-rest gap
- Prior finding: only `users.email` and `users.phone` were encrypted; ledger/file fields identified in the report were not.
- Current status: `Fixed` for the specific prior finding
- Evidence:
  - `README.md:155-163`
  - `app/Models/StoredValueLedger.php:25-31`
  - `app/Models/FileAsset.php:24-30`
  - `tests/Feature/Api/StaticAuditFixTest.php:241-309`
- Why:
  - docs now state additional encrypted fields
  - `stored_value_ledger.description` is encrypted
  - `file_assets.storage_path` and `file_assets.original_filename` are encrypted
  - dedicated tests verify raw DB values differ from plaintext
- Note:
  - this verifies the exact earlier finding was addressed; broader “critical data” interpretation still depends on acceptance expectations

### 4. Allowlist/blacklist accepted unsupported scope types
- Prior finding: admin endpoints and UI allowed arbitrary `scope_type`, but enforcement only honored a subset.
- Current status: `Fixed`
- Evidence:
  - `app/Http/Controllers/Api/AdminController.php:72-89`
  - `resources/js/app/views/admin/AllowlistView.vue:7-12`
  - `resources/js/app/views/admin/BlacklistView.vue:7-13`
  - `tests/Feature/Api/StaticAuditFixTest.php:315-378`
- Why:
  - controller validation now restricts `scope_type` to `department,global`
  - frontend changed from free-text inputs to constrained selects
  - regression tests cover both accepted and rejected scope types

### 5. Admin dashboard stats contract mismatch
- Prior finding: frontend expected `total_resources` and `total_members`, but API did not return them.
- Current status: `Fixed`
- Evidence:
  - `app/Http/Controllers/Api/AdminController.php:284-296`
  - `resources/js/app/views/admin/AdminDashboard.vue:19-30`
  - `tests/Feature/Api/StaticAuditFixTest.php:384-410`
- Why:
  - API now returns both `total_resources` and `total_members`
  - frontend still consumes those exact fields
  - test asserts the contract

### 6. Audit log UI/API mismatch
- Prior finding: UI sent `search`, `event`, `range`, `page`, `per_page` and expected `entry.user`, but API only partially supported that contract.
- Current status: `Fixed`
- Evidence:
  - `app/Http/Controllers/Api/AdminController.php:167-205`
  - `resources/js/app/views/admin/AuditLogView.vue:188-206`
  - `tests/Feature/Api/StaticAuditFixTest.php:416-487`
- Why:
  - API now eager-loads `user`
  - API supports `event`, `search`, `range`, and `per_page`
  - tests cover these filters and the user relation

### 7. Loan renewal UI depended on missing payload fields
- Prior finding: UI read `loan.checkout.renewal_count` and `loan.resource.loan_rules.max_renewals`, but API did not return those fields.
- Current status: `Fixed`
- Evidence:
  - `app/Http/Resources/CheckoutResource.php:15-31`
  - `app/Http/Resources/ResourceResource.php:21-42`
  - `resources/js/app/views/student/LoansView.vue:138-142`
  - `tests/Feature/Api/StaticAuditFixTest.php:493-539`
- Why:
  - checkout resource now includes `renewal_count`
  - resource payload now includes `loan_rules.max_renewals` and `loan_rules.max_loan_days`
  - regression tests cover both payload additions

### 8. Validation report download existed in API but not in SPA
- Prior finding: report download endpoint existed, but UI did not expose it.
- Current status: `Fixed`
- Evidence:
  - `resources/js/app/views/admin/ImportView.vue:170-179`
  - `resources/js/app/views/admin/ImportView.vue:270-282`
  - `tests/Feature/Api/StaticAuditFixTest.php:545-563`
- Why:
  - import history now includes a download button
  - client implements download behavior
  - endpoint contract is covered by test

### 9. `User::getPointsBalance()` summed the wrong column
- Prior finding: helper summed non-existent `amount`.
- Current status: `Fixed`
- Evidence:
  - `app/Models/User.php:159-162`
  - `tests/Feature/Api/StaticAuditFixTest.php:569-590`
- Why:
  - helper now sums `points`
  - test verifies the expected balance result

### 10. `Resource::scopeAvailable()` used invalid status `available`
- Prior finding: scope filtered on a non-existent enum value.
- Current status: `Fixed`
- Evidence:
  - `app/Models/Resource.php:84-93`
  - `tests/Feature/Api/StaticAuditFixTest.php:596-672`
- Why:
  - scope now returns active resources with at least one lot having `serviceable_quantity > 0`
  - tests cover positive and negative cases

## Summary
- Fixed with code and regression tests: `1, 2, 4, 5, 6, 7, 8, 9, 10`
- Fixed with code, docs, and regression tests for the specific prior finding: `3`

## Remaining Boundary Notes
- This verification only confirms that the previously reported issues now have static evidence of remediation.
- It does not prove broader runtime correctness, queue execution, HTTPS deployment, or end-to-end manual UX behavior.
