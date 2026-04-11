# Previous Issues Revalidation

## Verdict

All previously reported issues reviewed in this pass appear **fixed in the current codebase**, based on static inspection only.

## Boundary

- Reviewed only the previously reported issues from `.tmp/static-audit-report.md`
- Performed static code and test inspection only
- Did not start the project
- Did not run Docker
- Did not run tests
- Runtime behavior remains **Manual Verification Required**

## Issue Revalidation

### 1. Auth idempotency storage persisted bearer tokens

- Status: **Fixed**
- Rationale: auth-route idempotency persistence now stores a sanitized placeholder body instead of the original login response payload.
- Evidence:
  - `app/Http/Middleware/IdempotencyMiddleware.php:53-67`
  - `app/Http/Controllers/Api/AuthController.php:21-25`

### 2. File APIs exposed internal metadata by default

- Status: **Fixed**
- Rationale: file list and upload responses now pass through a dedicated resource that omits internal fields such as `storage_path` and `checksum`.
- Evidence:
  - `app/Http/Controllers/Api/FileController.php:70-72`
  - `app/Http/Controllers/Api/FileController.php:127`
  - `app/Http/Resources/FileAssetResource.php:9-23`

### 3. Student loan and reservation requests could be submitted without class or assignment context

- Status: **Fixed**
- Rationale: backend enforcement now lives in the domain services. If a scoped student submits a request without `class_id` or `assignment_id`, the service raises a business-rule error requiring class context. The student UI also now requires class selection when class options exist, and dedicated tests were added for rejection and success cases.
- Evidence:
  - `app/Domain/Lending/LendingService.php:67-83`
  - `app/Domain/Reservations/ReservationService.php:121-131`
  - `app/Http/Controllers/Api/LoanController.php:52-58`
  - `app/Http/Controllers/Api/ReservationController.php:39-45`
  - `resources/js/app/views/student/ResourceDetailView.vue:128-137`
  - `resources/js/app/views/student/ResourceDetailView.vue:245-250`
  - `resources/js/app/views/student/ResourceDetailView.vue:268-273`
  - `tests/Feature/Api/UIContractTest.php:196-250`

Note:
- `app/Http/Requests/CreateLoanRequest.php:20-21` and `app/Http/Requests/CreateReservationRequestForm.php:17-18` still mark these fields as nullable, but the effective backend enforcement now happens in the domain layer used by the controller write paths.

### 4. Bootstrap credential file could drift from the actual database password state

- Status: **Fixed**
- Rationale: when bootstrap users already exist, the seeder now updates their stored password to match the generated credential file instead of only writing a new file-side password.
- Evidence:
  - `database/seeders/BootstrapAccountSeeder.php:24-27`
  - `database/seeders/BootstrapAccountSeeder.php:42-59`
  - `database/seeders/BootstrapAccountSeeder.php:76-84`
  - `tests/Feature/BootstrapAccountSeederTest.php:31-74`

### 5. Missing regression coverage for auth idempotency response persistence

- Status: **Fixed**
- Rationale: a dedicated API test now verifies the persisted idempotency response body does not contain the bearer token from login.
- Evidence:
  - `tests/Feature/Api/AuthApiTest.php:89-114`

### 6. Missing regression coverage for file API response shape

- Status: **Fixed**
- Rationale: dedicated API tests now assert that upload and list responses omit `storage_path` and `checksum`.
- Evidence:
  - `tests/Feature/Api/FileApiTest.php:96-132`

## Final Note

This revalidation only answers whether the previously reported issues are still present in the current code. It does not certify the entire project as defect-free, and it does not prove runtime behavior because no execution was performed.
