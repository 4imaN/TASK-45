# Combined Audit Report

## 1. Test Coverage Audit

### Backend Endpoint Inventory
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `POST /api/auth/change-password`
- `GET /api/my-classes`
- `GET /api/catalog`
- `GET /api/catalog/{resource}`
- `GET /api/loans`
- `POST /api/loans`
- `GET /api/loans/{loan}`
- `POST /api/loans/{loan}/approve`
- `POST /api/loans/{loan}/checkout`
- `POST /api/checkouts/{checkout}/checkin`
- `POST /api/checkouts/{checkout}/renew`
- `GET /api/reminders`
- `POST /api/reminders/{reminder}/acknowledge`
- `GET /api/checkouts`
- `GET /api/reservations`
- `POST /api/reservations`
- `GET /api/reservations/{reservation}`
- `POST /api/reservations/{reservation}/approve`
- `POST /api/reservations/{reservation}/cancel`
- `GET /api/transfers`
- `POST /api/transfers`
- `POST /api/transfers/{transfer}/approve`
- `POST /api/transfers/{transfer}/in-transit`
- `POST /api/transfers/{transfer}/complete`
- `POST /api/transfers/{transfer}/cancel`
- `GET /api/memberships/tiers`
- `GET /api/memberships/me`
- `GET /api/memberships/packages`
- `POST /api/memberships/redeem-points`
- `POST /api/memberships/redeem-stored-value`
- `POST /api/memberships/entitlements/{grant}/consume`
- `POST /api/recommendations/for-class`
- `GET /api/recommendations/batches/{batch}`
- `POST /api/recommendations/override`
- `GET /api/data-quality/stats`
- `POST /api/data-quality/import`
- `GET /api/data-quality/batches`
- `GET /api/data-quality/batches/{batch}`
- `GET /api/data-quality/batches/{batch}/download`
- `GET /api/data-quality/remediation`
- `POST /api/data-quality/remediation/{item}`
- `GET /api/data-quality/duplicates`
- `POST /api/data-quality/duplicates/{candidate}`
- `GET /api/data-quality/vendor-aliases`
- `POST /api/data-quality/vendor-aliases`
- `PUT /api/data-quality/vendor-aliases/{alias}`
- `GET /api/data-quality/manufacturer-aliases`
- `POST /api/data-quality/manufacturer-aliases`
- `PUT /api/data-quality/manufacturer-aliases/{alias}`
- `GET /api/admin/stats`
- `POST /api/admin/memberships/assign-tier`
- `POST /api/admin/memberships/deposit`
- `POST /api/admin/memberships/grant-entitlement`
- `POST /api/admin/scopes`
- `GET /api/admin/scopes`
- `GET /api/admin/scopes/user`
- `DELETE /api/admin/scopes/{scope}`
- `GET /api/admin/allowlists`
- `POST /api/admin/allowlists`
- `DELETE /api/admin/allowlists/{allowlist}`
- `GET /api/admin/blacklists`
- `POST /api/admin/blacklists`
- `DELETE /api/admin/blacklists/{blacklist}`
- `GET /api/admin/holds`
- `POST /api/admin/holds`
- `POST /api/admin/holds/{hold}/release`
- `GET /api/admin/interventions`
- `GET /api/admin/audit-logs`
- `GET /api/admin/audit-logs/export`
- `POST /api/admin/reveal-field`
- `POST /api/files/upload`
- `GET /api/files`
- `GET /api/files/{file}/download`

Source: `routes/api.php:15-122`.

### API Test Mapping Table
| Endpoint | Covered | Test type | Test files | Evidence |
|---|---|---|---|---|
| `POST /api/auth/login` | yes | true no-mock HTTP | `AuthApiTest`, `IdempotencyTest` | `tests/Feature/Api/AuthApiTest.php:17`, `tests/Feature/Security/IdempotencyTest.php:105` |
| `POST /api/auth/logout` | yes | true no-mock HTTP | `AuthApiTest`, `MiddlewareTest`, Playwright E2E | `tests/Feature/Api/AuthApiTest.php:48`, `tests/Feature/Security/MiddlewareTest.php:59`, `tests/e2e/student-flow.spec.js:48` |
| `GET /api/auth/me` | yes | true no-mock HTTP | `AuthApiTest`, `AuthorizationTest` | `tests/Feature/Api/AuthApiTest.php:54`, `tests/Feature/Security/AuthorizationTest.php:299` |
| `POST /api/auth/change-password` | yes | true no-mock HTTP | `AuthApiTest`, `MiddlewareTest` | `tests/Feature/Api/AuthApiTest.php:66`, `tests/Feature/Security/MiddlewareTest.php:41` |
| `GET /api/my-classes` | yes | true no-mock HTTP | `UIContractTest` | `tests/Feature/Api/UIContractTest.php:172`, `tests/Feature/Api/UIContractTest.php:186` |
| `GET /api/catalog` | yes | true no-mock HTTP | `CatalogApiTest`, `MiddlewareTest` | `tests/Feature/Api/CatalogApiTest.php:18`, `tests/Feature/Security/MiddlewareTest.php:29` |
| `GET /api/catalog/{resource}` | yes | true no-mock HTTP | `CatalogApiTest`, `ContractAlignmentTest`, `FinalHardeningTest` | `tests/Feature/Api/CatalogApiTest.php:63`, `tests/Feature/Api/ContractAlignmentTest.php:21`, `tests/Feature/Security/FinalHardeningTest.php:75` |
| `GET /api/loans` | yes | true no-mock HTTP | `LoanApiTest`, `ScopeLeakTest`, `FinalHardeningTest` | `tests/Feature/Api/LoanApiTest.php:172`, `tests/Feature/Security/ScopeLeakTest.php:46`, `tests/Feature/Security/FinalHardeningTest.php:155` |
| `POST /api/loans` | yes | true no-mock HTTP | `LoanApiTest`, `IdempotencyTest`, `FullLendingWorkflowTest` | `tests/Feature/Api/LoanApiTest.php:20`, `tests/Feature/Security/IdempotencyTest.php:20`, `tests/Feature/Workflows/FullLendingWorkflowTest.php:23` |
| `GET /api/loans/{loan}` | yes | true no-mock HTTP | `RouteDepthTest` | `tests/Feature/Api/RouteDepthTest.php:370`, `tests/Feature/Api/RouteDepthTest.php:388` |
| `POST /api/loans/{loan}/approve` | yes | true no-mock HTTP | `LoanApiTest`, `InventoryIntegrityTest`, `FullLendingWorkflowTest` | `tests/Feature/Api/LoanApiTest.php:65`, `tests/Feature/Security/InventoryIntegrityTest.php:142`, `tests/Feature/Workflows/FullLendingWorkflowTest.php:30` |
| `POST /api/loans/{loan}/checkout` | yes | true no-mock HTTP | `LoanApiTest`, `InventoryIntegrityTest`, `FullLendingWorkflowTest` | `tests/Feature/Api/LoanApiTest.php:85`, `tests/Feature/Security/InventoryIntegrityTest.php:166`, `tests/Feature/Workflows/FullLendingWorkflowTest.php:34` |
| `POST /api/checkouts/{checkout}/checkin` | yes | true no-mock HTTP | `LoanApiTest`, `FinalAuditTest`, `FullLendingWorkflowTest` | `tests/Feature/Api/LoanApiTest.php:108`, `tests/Feature/Security/FinalAuditTest.php:161`, `tests/Feature/Workflows/FullLendingWorkflowTest.php:47` |
| `POST /api/checkouts/{checkout}/renew` | yes | true no-mock HTTP | `LoanApiTest`, `AuthorizationTest`, `FullLendingWorkflowTest` | `tests/Feature/Api/LoanApiTest.php:131`, `tests/Feature/Security/AuthorizationTest.php:241`, `tests/Feature/Workflows/FullLendingWorkflowTest.php:43` |
| `GET /api/reminders` | yes | true no-mock HTTP | `ReminderApiTest` | `tests/Feature/Api/ReminderApiTest.php:40` |
| `POST /api/reminders/{reminder}/acknowledge` | yes | true no-mock HTTP | `ReminderApiTest` | `tests/Feature/Api/ReminderApiTest.php:56`, `tests/Feature/Api/ReminderApiTest.php:74` |
| `GET /api/checkouts` | yes | true no-mock HTTP | `FinalHardeningTest` | `tests/Feature/Security/FinalHardeningTest.php:19`, `tests/Feature/Security/FinalHardeningTest.php:63` |
| `GET /api/reservations` | yes | true no-mock HTTP | `ReservationApiTest`, `ScopeLeakTest`, `ContractAlignmentTest` | `tests/Feature/Api/ReservationApiTest.php:142`, `tests/Feature/Security/ScopeLeakTest.php:84`, `tests/Feature/Api/ContractAlignmentTest.php:181` |
| `POST /api/reservations` | yes | true no-mock HTTP | `ReservationApiTest`, `StaticAuditFixTest`, `PromptComplianceTest` | `tests/Feature/Api/ReservationApiTest.php:41`, `tests/Feature/Api/StaticAuditFixTest.php:45`, `tests/Feature/Api/PromptComplianceTest.php:27` |
| `GET /api/reservations/{reservation}` | yes | true no-mock HTTP | `RouteDepthTest` | `tests/Feature/Api/RouteDepthTest.php:402`, `tests/Feature/Api/RouteDepthTest.php:418` |
| `POST /api/reservations/{reservation}/approve` | yes | true no-mock HTTP | `UIContractTest`, `StaticAuditFixTest`, `ScopeBypassTest` | `tests/Feature/Api/UIContractTest.php:85`, `tests/Feature/Api/StaticAuditFixTest.php:146`, `tests/Feature/Security/ScopeBypassTest.php:68` |
| `POST /api/reservations/{reservation}/cancel` | yes | true no-mock HTTP | `ReservationApiTest` | `tests/Feature/Api/ReservationApiTest.php:111` |
| `GET /api/transfers` | yes | true no-mock HTTP | `ContractAlignmentTest`, `AuthorizationTest`, `PolicyBoundaryTest` | `tests/Feature/Api/ContractAlignmentTest.php:130`, `tests/Feature/Security/AuthorizationTest.php:21`, `tests/Feature/Security/PolicyBoundaryTest.php:89` |
| `POST /api/transfers` | yes | true no-mock HTTP | `TransferApiTest`, `AuthorizationTest`, `InventoryIntegrityTest` | `tests/Feature/Api/TransferApiTest.php:21`, `tests/Feature/Security/AuthorizationTest.php:31`, `tests/Feature/Security/InventoryIntegrityTest.php:43` |
| `POST /api/transfers/{transfer}/approve` | yes | true no-mock HTTP | `PolicyBoundaryTest`, `InventoryIntegrityTest`, `ScopeBypassTest` | `tests/Feature/Security/PolicyBoundaryTest.php:37`, `tests/Feature/Security/InventoryIntegrityTest.php:190`, `tests/Feature/Security/ScopeBypassTest.php:136` |
| `POST /api/transfers/{transfer}/in-transit` | yes | true no-mock HTTP | `SecondaryRoutesTest` | `tests/Feature/Api/SecondaryRoutesTest.php:49` |
| `POST /api/transfers/{transfer}/complete` | yes | true no-mock HTTP | `TransferApiTest` | `tests/Feature/Api/TransferApiTest.php:50`, `tests/Feature/Api/TransferApiTest.php:76` |
| `POST /api/transfers/{transfer}/cancel` | yes | true no-mock HTTP | `RouteDepthTest` | `tests/Feature/Api/RouteDepthTest.php:48`, `tests/Feature/Api/RouteDepthTest.php:85` |
| `GET /api/memberships/tiers` | yes | true no-mock HTTP | `MembershipApiTest` | `tests/Feature/Api/MembershipApiTest.php:25` |
| `GET /api/memberships/me` | yes | true no-mock HTTP | `MembershipApiTest` | `tests/Feature/Api/MembershipApiTest.php:17` |
| `GET /api/memberships/packages` | yes | true no-mock HTTP | `RouteDepthTest` | `tests/Feature/Api/RouteDepthTest.php:214`, `tests/Feature/Api/RouteDepthTest.php:224` |
| `POST /api/memberships/redeem-points` | yes | true no-mock HTTP | `MembershipApiTest` | `tests/Feature/Api/MembershipApiTest.php:39` |
| `POST /api/memberships/redeem-stored-value` | yes | true no-mock HTTP | `RouteDepthTest` | `tests/Feature/Api/RouteDepthTest.php:121`, `tests/Feature/Api/RouteDepthTest.php:180` |
| `POST /api/memberships/entitlements/{grant}/consume` | yes | true no-mock HTTP | `MembershipApiTest`, `AuthorizationTest` | `tests/Feature/Api/MembershipApiTest.php:58`, `tests/Feature/Security/AuthorizationTest.php:131` |
| `POST /api/recommendations/for-class` | yes | true no-mock HTTP | `RecommendationApiTest`, `FinalAuditTest`, `PolicyBoundaryTest` | `tests/Feature/Api/RecommendationApiTest.php:19`, `tests/Feature/Security/FinalAuditTest.php:26`, `tests/Feature/Security/PolicyBoundaryTest.php:105` |
| `GET /api/recommendations/batches/{batch}` | yes | true no-mock HTTP | `AuthorizationTest`, `PolicyBoundaryTest`, `PromptComplianceTest` | `tests/Feature/Security/AuthorizationTest.php:86`, `tests/Feature/Security/PolicyBoundaryTest.php:110`, `tests/Feature/Api/PromptComplianceTest.php:118` |
| `POST /api/recommendations/override` | yes | true no-mock HTTP | `RecommendationApiTest`, `AuthorizationTest`, `ScopeBypassTest` | `tests/Feature/Api/RecommendationApiTest.php:101`, `tests/Feature/Security/AuthorizationTest.php:69`, `tests/Feature/Security/ScopeBypassTest.php:280` |
| `GET /api/data-quality/stats` | yes | true no-mock HTTP | `ContractAlignmentTest` | `tests/Feature/Api/ContractAlignmentTest.php:84`, `tests/Feature/Api/ContractAlignmentTest.php:100` |
| `POST /api/data-quality/import` | yes | true no-mock HTTP | `DataQualityApiTest`, `StaticAuditFixTest`, `FinalAuditTest` | `tests/Feature/Api/DataQualityApiTest.php:18`, `tests/Feature/Api/StaticAuditFixTest.php:164`, `tests/Feature/Security/FinalAuditTest.php:178` |
| `GET /api/data-quality/batches` | yes | true no-mock HTTP | `RouteDepthTest`, `AuthorizationTest` | `tests/Feature/Api/RouteDepthTest.php:267`, `tests/Feature/Security/AuthorizationTest.php:104` |
| `GET /api/data-quality/batches/{batch}` | yes | true no-mock HTTP | `RouteDepthTest` | `tests/Feature/Api/RouteDepthTest.php:247` |
| `GET /api/data-quality/batches/{batch}/download` | yes | true no-mock HTTP | `DataQualityApiTest`, `StaticAuditFixTest` | `tests/Feature/Api/DataQualityApiTest.php:42`, `tests/Feature/Api/StaticAuditFixTest.php:559` |
| `GET /api/data-quality/remediation` | yes | true no-mock HTTP | `DataQualityApiTest` | `tests/Feature/Api/DataQualityApiTest.php:30` |
| `POST /api/data-quality/remediation/{item}` | yes | true no-mock HTTP | `SecondaryRoutesTest` | `tests/Feature/Api/SecondaryRoutesTest.php:210` |
| `GET /api/data-quality/duplicates` | yes | true no-mock HTTP | `RouteDepthTest` | `tests/Feature/Api/RouteDepthTest.php:282`, `tests/Feature/Api/RouteDepthTest.php:296` |
| `POST /api/data-quality/duplicates/{candidate}` | yes | true no-mock HTTP | `SecondaryRoutesTest` | `tests/Feature/Api/SecondaryRoutesTest.php:230` |
| `GET /api/data-quality/vendor-aliases` | yes | true no-mock HTTP | `RouteDepthTest` | `tests/Feature/Api/RouteDepthTest.php:305` |
| `POST /api/data-quality/vendor-aliases` | yes | true no-mock HTTP | `RouteDepthTest` | `tests/Feature/Api/RouteDepthTest.php:314`, `tests/Feature/Api/RouteDepthTest.php:328` |
| `PUT /api/data-quality/vendor-aliases/{alias}` | yes | true no-mock HTTP | `SecondaryRoutesTest` | `tests/Feature/Api/SecondaryRoutesTest.php:248` |
| `GET /api/data-quality/manufacturer-aliases` | yes | true no-mock HTTP | `RouteDepthTest` | `tests/Feature/Api/RouteDepthTest.php:350` |
| `POST /api/data-quality/manufacturer-aliases` | yes | true no-mock HTTP | `RouteDepthAdditionalTest` | `tests/Feature/Api/RouteDepthAdditionalTest.php:35`, `tests/Feature/Api/RouteDepthAdditionalTest.php:53` |
| `PUT /api/data-quality/manufacturer-aliases/{alias}` | yes | true no-mock HTTP | `SecondaryRoutesTest` | `tests/Feature/Api/SecondaryRoutesTest.php:266` |
| `GET /api/admin/stats` | yes | true no-mock HTTP | `StaticAuditFixTest` | `tests/Feature/Api/StaticAuditFixTest.php:396` |
| `POST /api/admin/memberships/assign-tier` | yes | true no-mock HTTP | `PolicyBoundaryTest` | `tests/Feature/Security/PolicyBoundaryTest.php:196` |
| `POST /api/admin/memberships/deposit` | yes | true no-mock HTTP | `SecondaryRoutesTest`, `RouteDepthTest` | `tests/Feature/Api/SecondaryRoutesTest.php:149`, `tests/Feature/Api/RouteDepthTest.php:575` |
| `POST /api/admin/memberships/grant-entitlement` | yes | true no-mock HTTP | `SecondaryRoutesTest`, `RouteDepthTest` | `tests/Feature/Api/SecondaryRoutesTest.php:176`, `tests/Feature/Api/RouteDepthTest.php:609` |
| `POST /api/admin/scopes` | yes | true no-mock HTTP | `AdminApiTest`, `RouteDepthTest` | `tests/Feature/Api/AdminApiTest.php:20`, `tests/Feature/Api/RouteDepthTest.php:431` |
| `GET /api/admin/scopes` | yes | true no-mock HTTP | `FinalHardeningTest`, `AdminApiTest` | `tests/Feature/Security/FinalHardeningTest.php:104`, `tests/Feature/Api/AdminApiTest.php:72` |
| `GET /api/admin/scopes/user` | yes | true no-mock HTTP | `SecondaryRoutesTest`, `RouteDepthTest` | `tests/Feature/Api/SecondaryRoutesTest.php:74`, `tests/Feature/Api/RouteDepthTest.php:534` |
| `DELETE /api/admin/scopes/{scope}` | yes | true no-mock HTTP | `SecondaryRoutesTest`, `RouteDepthTest` | `tests/Feature/Api/SecondaryRoutesTest.php:89`, `tests/Feature/Api/RouteDepthTest.php:482` |
| `GET /api/admin/allowlists` | yes | true no-mock HTTP | `ClassIdValidationTest`, `InventoryIntegrityTest` | `tests/Feature/Security/ClassIdValidationTest.php:142`, `tests/Feature/Security/InventoryIntegrityTest.php:214` |
| `POST /api/admin/allowlists` | yes | true no-mock HTTP | `StaticAuditFixTest`, `RouteDepthAdditionalTest` | `tests/Feature/Api/StaticAuditFixTest.php:321`, `tests/Feature/Api/RouteDepthAdditionalTest.php:188` |
| `DELETE /api/admin/allowlists/{allowlist}` | yes | true no-mock HTTP | `RouteDepthAdditionalTest`, `ClassIdValidationTest` | `tests/Feature/Api/RouteDepthAdditionalTest.php:222`, `tests/Feature/Security/ClassIdValidationTest.php:156` |
| `GET /api/admin/blacklists` | yes | true no-mock HTTP | `ClassIdValidationTest` | `tests/Feature/Security/ClassIdValidationTest.php:174` |
| `POST /api/admin/blacklists` | yes | true no-mock HTTP | `AdminApiTest`, `StaticAuditFixTest` | `tests/Feature/Api/AdminApiTest.php:48`, `tests/Feature/Api/StaticAuditFixTest.php:337` |
| `DELETE /api/admin/blacklists/{blacklist}` | yes | true no-mock HTTP | `ClassIdValidationTest`, `RouteDepthAdditionalTest` | `tests/Feature/Security/ClassIdValidationTest.php:176`, `tests/Feature/Api/RouteDepthAdditionalTest.php:264` |
| `GET /api/admin/holds` | yes | true no-mock HTTP | `UIContractTest`, `AuthorizationTest` | `tests/Feature/Api/UIContractTest.php:148`, `tests/Feature/Security/AuthorizationTest.php:198` |
| `POST /api/admin/holds` | yes | true no-mock HTTP | `UIContractTest` | `tests/Feature/Api/UIContractTest.php:104`, `tests/Feature/Api/UIContractTest.php:125` |
| `POST /api/admin/holds/{hold}/release` | yes | true no-mock HTTP | `AdminApiTest`, `RouteDepthAdditionalTest` | `tests/Feature/Api/AdminApiTest.php:37`, `tests/Feature/Api/RouteDepthAdditionalTest.php:292` |
| `GET /api/admin/interventions` | yes | true no-mock HTTP | `SecondaryRoutesTest`, `RouteDepthTest` | `tests/Feature/Api/SecondaryRoutesTest.php:119`, `tests/Feature/Api/RouteDepthTest.php:561` |
| `GET /api/admin/audit-logs` | yes | true no-mock HTTP | `AdminApiTest`, `StaticAuditFixTest` | `tests/Feature/Api/AdminApiTest.php:79`, `tests/Feature/Api/StaticAuditFixTest.php:426` |
| `GET /api/admin/audit-logs/export` | yes | true no-mock HTTP | `SecondaryRoutesTest`, `RouteDepthAdditionalTest` | `tests/Feature/Api/SecondaryRoutesTest.php:132`, `tests/Feature/Api/RouteDepthAdditionalTest.php:72` |
| `POST /api/admin/reveal-field` | yes | true no-mock HTTP | `AdminApiTest`, `ScopeLeakTest`, `RouteDepthAdditionalTest` | `tests/Feature/Api/AdminApiTest.php:61`, `tests/Feature/Security/ScopeLeakTest.php:165`, `tests/Feature/Api/RouteDepthAdditionalTest.php:124` |
| `POST /api/files/upload` | yes | true no-mock HTTP plus mocked HTTP variants | `FileApiRealDiskTest`, `FileApiTest`, `FinalAuditTest` | `tests/Feature/Api/FileApiRealDiskTest.php:52`, `tests/Feature/Api/FileApiTest.php:25`, `tests/Feature/Security/FinalAuditTest.php:103` |
| `GET /api/files` | yes | true no-mock HTTP | `FileApiTest`, `RouteDepthTest` | `tests/Feature/Api/FileApiTest.php:62`, `tests/Feature/Api/RouteDepthTest.php:623` |
| `GET /api/files/{file}/download` | yes | true no-mock HTTP plus mocked HTTP variants | `FileApiRealDiskTest`, `FileApiTest`, `AuthorizationTest` | `tests/Feature/Api/FileApiRealDiskTest.php:95`, `tests/Feature/Api/FileApiTest.php:78`, `tests/Feature/Security/AuthorizationTest.php:167` |

### API Test Classification
1. True no-mock HTTP
- Most PHPUnit feature tests under `tests/Feature/Api/*.php`, `tests/Feature/Security/*.php`, and `tests/Feature/Workflows/FullLendingWorkflowTest.php` use Laravel's HTTP layer without controller/service/provider overrides.
- `tests/Feature/Api/FileApiRealDiskTest.php:15-132` is explicit true no-mock coverage for file upload/download against real disk I/O.
- Playwright E2E exists for FE↔BE paths in `tests/e2e/auth.spec.js` and `tests/e2e/student-flow.spec.js`.

2. HTTP with mocking
- `tests/Feature/Api/FileApiTest.php:15` uses `Storage::fake('local')`; the upload/download execution path is not true no-mock there.
- `tests/Feature/Security/AuthorizationTest.php:158`, `tests/Feature/Security/FinalAuditTest.php:99,114`, and `tests/Feature/Security/ScopeBypassTest.php:200,222` also exercise file endpoints with `Storage::fake('local')`.

3. Non-HTTP (unit/integration without HTTP)
- Domain/service unit tests in `tests/Unit/Domain/*.php`.
- Scheduler/seed tests in `tests/Feature/Console/SchedulerWiringTest.php` and `tests/Feature/BootstrapAccountSeederTest.php`.
- Integration service tests in `tests/Integration/ProductionGuaranteesTest.php` include both direct service calls and some HTTP calls; the direct service-call sections are non-HTTP.
- Frontend unit tests under `resources/js/tests/unit/**` are non-HTTP and mock-heavy.

### Mock Detection
| What is mocked/faked | Where | Impact |
|---|---|---|
| `Storage::fake('local')` | `tests/Feature/Api/FileApiTest.php:15` | Upload/download path becomes HTTP with mocking for those tests. |
| `Storage::fake('local')` | `tests/Feature/Security/AuthorizationTest.php:158` | File auth coverage is not true no-mock in that test. |
| `Storage::fake('local')` | `tests/Feature/Security/FinalAuditTest.php:99,114` | Resource-attachment file coverage is mocked at storage layer. |
| `Storage::fake('local')` | `tests/Feature/Security/ScopeBypassTest.php:200,222` | File attachment authorization paths are mocked at storage layer. |
| `Bus::fake()` | `tests/Feature/Api/ReminderApiTest.php:85` | Console dispatch test is non-HTTP and mocked at bus layer. |
| `vi.mock(...)` | `resources/js/tests/unit/router/guards.test.js:23` and multiple frontend unit tests | Frontend unit tests are not API coverage evidence. |

### Coverage Summary
- Total endpoints: `76`
- Endpoints with HTTP tests: `76`
- Endpoints with true no-mock HTTP tests: `76`
- HTTP coverage: `100.0%`
- True API coverage: `100.0%`

### Unit Test Summary
- Unit/non-HTTP test files inspected:
  - `tests/Unit/Domain/AvailabilityServiceTest.php`
  - `tests/Unit/Domain/DataQualityServiceTest.php`
  - `tests/Unit/Domain/FileServiceTest.php`
  - `tests/Unit/Domain/PointsServiceTest.php`
  - `tests/Unit/Domain/ReminderCadenceTest.php`
  - `tests/Unit/Domain/StoredValueServiceTest.php`
  - `tests/Integration/ProductionGuaranteesTest.php`
  - Frontend unit files under `resources/js/tests/unit/**`
- Modules covered:
  - Services: `AvailabilityService`, `DataQualityService`, `FileService`, `PointsService`, `StoredValueService`
  - Job logic: `ProcessReminders`
  - Frontend router/store/view/composable logic with mocks
- Important modules not unit tested directly:
  - Controllers in `app/Http/Controllers/Api/*`
  - Services: `AuthService`, `LendingService`, `ReservationService`, `TransferService`, `RecommendationService`, `MembershipService`, `AuditService`
  - Policies in `app/Policies/*`
  - Middleware classes in `app/Http/Middleware/*` have feature coverage but no direct unit tests
  - Request validation/resource transformers in `app/Http/Requests/*` and `app/Http/Resources/*`
  - No repository layer exists as a distinct codebase module to unit test

### Tests Check
- Success paths: strong coverage across loans, reservations, transfers, memberships, admin, files.
- Failure/edge paths: strong coverage for auth failures, idempotency conflicts, scope violations, validation errors, duplicate state transitions, availability exhaustion, sensitive-resource access, and encryption-at-rest checks.
- Auth/permissions: strong; security suites are extensive (`AuthorizationTest`, `PolicyBoundaryTest`, `ScopeLeakTest`, `ScopeBypassTest`, `FinalHardeningTest`).
- Integration boundaries: strong for DB/idempotency/locking through `tests/Integration/ProductionGuaranteesTest.php`; file I/O boundary is only truly no-mock in `tests/Feature/Api/FileApiRealDiskTest.php`.
- Weak observability examples:
  - `tests/Feature/Api/AdminApiTest.php:79` only asserts `200` for `GET /api/admin/audit-logs`.
  - `tests/Feature/Api/DataQualityApiTest.php:30` only asserts `200` for `GET /api/data-quality/remediation`.
  - `tests/Feature/Api/ContractAlignmentTest.php:130` only asserts `200` for `GET /api/transfers`.
  - `tests/Feature/Api/ReservationApiTest.php:142` names ownership intent for `GET /api/reservations` but does not assert ownership filtering.
- `run_tests.sh` check:
  - Core suites are Docker-based: `run_tests.sh:9-80` runs backend, MySQL integration, and frontend Vitest inside `docker compose exec`.
  - No host-side Playwright or runtime-install branch remains in the script as currently checked (`run_tests.sh:1-98`).

### End-to-End Expectations
- Project type is fullstack; real FE↔BE E2E exists in `tests/e2e/auth.spec.js` and `tests/e2e/student-flow.spec.js`.
- Gap: those E2E tests are not part of the documented gated path and are not wired into `run_tests.sh`.
- Partial compensation: the API suite plus service/unit/integration tests are strong enough to offset the lack of a gated FE↔BE path for backend confidence, but not for strict release-path fullstack confidence.

### Test Coverage Score (0–100)
- `91/100`

### Score Rationale
- `+` Endpoint coverage is complete: every declared backend endpoint has direct HTTP coverage.
- `+` True no-mock HTTP coverage exists for every endpoint, including the file endpoints via `FileApiRealDiskTest`.
- `+` Security, authorization, idempotency, and concurrency/integrity checks are materially stronger than average.
- `-` A non-trivial subset of list/admin endpoints has shallow response assertions.
- `-` Unit coverage is concentrated in services; many core services/controllers/policies lack direct unit tests.
- `-` Fullstack E2E exists but is outside the gated path and depends on host-side setup.

### Key Gaps
- Response-content observability is weak on several list/admin endpoints; passing `200` often stands in for contract verification.
- Direct unit tests are missing for several core business services and all controllers/policies.
- Fullstack E2E remains outside the gated path; container-native Playwright is still a deferred improvement rather than enforced coverage.

### Test Verdict
- `PASS`

### Confidence & Assumptions
- Confidence: high.
- Assumptions:
  - `routes/api.php` resolves under Laravel's standard `/api` prefix; this is consistent with every test path using `/api/...`.
  - Query-string variants were normalized to the same endpoint (`GET /api/loans?search=...` counted as `GET /api/loans`).
  - Laravel feature tests using the real kernel and without fakes/overrides on the execution path were classified as true no-mock HTTP.

## 2. README Audit

### Project Type Detection
- Declared type: fullstack.
- Evidence: `README.md:3` describes an "offline-first, full-stack Laravel + Vue.js application".

### README Location
- Present at required path: `README.md`.

### Hard Gate Failures
- None found in the current README.

### High Priority Issues
- None.

### Medium Priority Issues
- Verification instructions are UI-centric only (`README.md:13-18`); there is no concrete API-level verification example such as a `curl` or Postman request despite the project exposing a substantial REST API.

### Low Priority Issues
- The README is strong on architecture, services, and security, but it does not summarize which roles can access which major workflows beyond seeded credentials.
- Playwright E2E still exists in the repo under `tests/e2e/`, but it is not documented as part of the strict/gated path. That is acceptable for compliance, but still leaves end-to-end coverage outside the default release gate.

### Formatting
- PASS. Markdown is structured, readable, and internally consistent.

### Startup Instructions
- PASS. `README.md:8-11` includes both `docker compose up --build` and `docker-compose up --build`.

### Access Method
- PASS. `README.md:12` gives the access URL `https://localhost`; `docker-compose.yml:41-44` exposes ports `80` and `443`.

### Verification Method
- PASS, but narrow. `README.md:13-18` provides a browser-based smoke flow using seeded credentials.

### Environment Rules
- PASS. The documented and scripted test flow is Docker-contained, and the current README no longer permits host-side runtime installs.

### Demo Credentials
- PASS. Auth is explicitly required except `POST /api/auth/login` (`README.md:22`) and credentials are provided for Admin, Teacher, TA, and Student (`README.md:26-31`).

### Engineering Quality
- Tech stack clarity: strong (`README.md:33-39`).
- Architecture explanation: strong (`README.md:41-56`, `README.md:112-121`).
- Testing instructions: strong and internally consistent with the current Docker-contained `run_tests.sh` flow (`README.md:79-123`, `run_tests.sh:1-98`).
- Security/roles: good seeded-account disclosure and security notes (`README.md:20-31`, `README.md:126-138`).
- Presentation quality: good.

### README Verdict
- `PASS`

### README Rationale
- The README now satisfies the strict startup, access, verification, Docker-contained environment, and credential requirements.
- Remaining concerns are quality-related rather than hard-gate failures.

## Final Verdicts
- Test Coverage Audit: `PASS`
- README Audit: `PASS`
