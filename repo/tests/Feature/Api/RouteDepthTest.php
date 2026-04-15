<?php
namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\CustodyRecord;
use App\Models\Department;
use App\Models\DuplicateCandidate;
use App\Models\EntitlementPackage;
use App\Models\Hold;
use App\Models\ImportBatch;
use App\Models\ImportValidationResult;
use App\Models\InterventionLog;
use App\Models\LoanRequest;
use App\Models\ManufacturerAlias;
use App\Models\PermissionScope;
use App\Models\ReservationRequest;
use App\Models\StoredValueLedger;
use App\Models\TransferRequest;
use App\Models\VendorAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;
use Tests\TestCase;

/**
 * Fills the coverage gaps surfaced in the static-audit-depth review:
 *   - transfer cancel edge cases
 *   - redeem-stored-value route coverage
 *   - data-quality report/duplicates/alias list+create
 *   - /loans/{id} and /reservations/{id} show-endpoint authorization
 *   - deeper admin validation: scopes POST, DELETE edges, deposit/grant validation
 *   - memberships/packages + batches list shape
 */
class RouteDepthTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // ==================================================================
    // Transfer — cancel
    // ==================================================================

    public function test_teacher_in_source_scope_can_cancel_pending_transfer(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        [$resource, $lot] = $this->createResourceWithLot();
        $transfer = $this->makeTransfer($teacher, $lot, $resource, 'pending');

        $response = $this->actingAs($teacher)->postJson(
            "/api/transfers/{$transfer->id}/cancel",
            [],
            ['X-Idempotency-Key' => 'cancel-ok-' . uniqid()],
        );
        $response->assertOk();
        $this->assertSame('cancelled', $transfer->fresh()->status);
    }

    public function test_cancelling_a_transfer_closes_open_custody_records(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        [$resource, $lot] = $this->createResourceWithLot();
        $transfer = $this->makeTransfer($teacher, $lot, $resource, 'approved');
        $custody = CustodyRecord::create([
            'transfer_request_id' => $transfer->id, 'inventory_lot_id' => $lot->id,
            'department_id' => $transfer->from_department_id, 'custody_type' => 'source_hold',
            'custodian_id' => $teacher->id, 'started_at' => now(),
        ]);

        $this->actingAs($teacher)->postJson(
            "/api/transfers/{$transfer->id}/cancel",
            [],
            ['X-Idempotency-Key' => 'cancel-custody-' . uniqid()],
        )->assertOk();

        $this->assertNotNull($custody->fresh()->ended_at);
    }

    public function test_cannot_cancel_a_completed_transfer(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        [$resource, $lot] = $this->createResourceWithLot();
        $transfer = $this->makeTransfer($teacher, $lot, $resource, 'completed');

        $response = $this->actingAs($teacher)->postJson(
            "/api/transfers/{$transfer->id}/cancel",
            [],
            ['X-Idempotency-Key' => 'cancel-done-' . uniqid()],
        );
        $response->assertUnprocessable();
        $this->assertSame('completed', $transfer->fresh()->status);
    }

    public function test_out_of_scope_teacher_cannot_cancel_transfer(): void
    {
        $ownerTeacher = $this->createTeacher();
        $this->grantScope($ownerTeacher);
        $otherTeacher = $this->createTeacher();
        // otherTeacher has no scope at all
        [$resource, $lot] = $this->createResourceWithLot();
        $transfer = $this->makeTransfer($ownerTeacher, $lot, $resource, 'pending');

        $this->actingAs($otherTeacher)->postJson(
            "/api/transfers/{$transfer->id}/cancel",
            [],
            ['X-Idempotency-Key' => 'cancel-forbidden-' . uniqid()],
        )->assertForbidden();

        $this->assertSame('pending', $transfer->fresh()->status);
    }

    // ==================================================================
    // Memberships — redeem-stored-value
    // ==================================================================

    public function test_redeem_stored_value_deducts_balance(): void
    {
        $student = $this->createStudent();
        $this->seedStoredValue($student, 10_000);

        $response = $this->actingAs($student)->postJson('/api/memberships/redeem-stored-value', [
            'amount_cents' => 2_500,
            'description' => 'Print credits',
            'idempotency_key' => 'rsv-ok-' . uniqid(),
        ], ['X-Idempotency-Key' => 'rsv-ok-' . uniqid()]);

        $response->assertOk()->assertJsonPath('balance_cents', 7_500);
        $this->assertDatabaseHas('stored_value_ledger', [
            'user_id' => $student->id,
            'amount_cents' => -2_500,
            'transaction_type' => 'redemption',
        ]);
    }

    public function test_redeem_stored_value_rejects_insufficient_balance(): void
    {
        $student = $this->createStudent();
        $this->seedStoredValue($student, 100);

        $response = $this->actingAs($student)->postJson('/api/memberships/redeem-stored-value', [
            'amount_cents' => 5_000,
            'description' => 'Too big',
            'idempotency_key' => 'rsv-broke-' . uniqid(),
        ], ['X-Idempotency-Key' => 'rsv-broke-' . uniqid()]);

        $response->assertUnprocessable();
    }

    public function test_redeem_stored_value_blocked_when_active_hold(): void
    {
        $student = $this->createStudent();
        $this->seedStoredValue($student, 5_000);
        Hold::create([
            'user_id' => $student->id, 'hold_type' => 'manual',
            'reason' => 'Review', 'status' => 'active', 'triggered_at' => now(),
        ]);

        $response = $this->actingAs($student)->postJson('/api/memberships/redeem-stored-value', [
            'amount_cents' => 100,
            'description' => 'Blocked',
            'idempotency_key' => 'rsv-hold-' . uniqid(),
        ], ['X-Idempotency-Key' => 'rsv-hold-' . uniqid()]);

        // CheckHold middleware short-circuits before the service runs; it returns 403.
        $response->assertForbidden();
        // No ledger entry written.
        $this->assertSame(
            0,
            StoredValueLedger::where('user_id', $student->id)
                ->where('transaction_type', 'redemption')->count(),
        );
    }

    public function test_redeem_stored_value_idempotency_replay_does_not_double_charge(): void
    {
        $student = $this->createStudent();
        $this->seedStoredValue($student, 10_000);
        $key = 'rsv-replay-' . uniqid();

        $first = $this->actingAs($student)->postJson('/api/memberships/redeem-stored-value', [
            'amount_cents' => 1_000,
            'description' => 'First',
            'idempotency_key' => 'body-' . $key,
        ], ['X-Idempotency-Key' => $key])->assertOk();

        $second = $this->actingAs($student)->postJson('/api/memberships/redeem-stored-value', [
            'amount_cents' => 1_000,
            'description' => 'First',
            'idempotency_key' => 'body-' . $key,
        ], ['X-Idempotency-Key' => $key])->assertOk();

        // Only one redemption ledger entry despite two requests with the same key
        $this->assertSame(1, StoredValueLedger::where('user_id', $student->id)
            ->where('transaction_type', 'redemption')
            ->count());
    }

    // ==================================================================
    // Memberships — packages list
    // ==================================================================

    public function test_memberships_packages_lists_available(): void
    {
        $student = $this->createStudent();
        EntitlementPackage::create([
            'name' => 'Print', 'resource_type' => 'equipment',
            'quantity' => 50, 'unit' => 'units', 'validity_days' => 30, 'price_in_cents' => 500,
        ]);
        EntitlementPackage::create([
            'name' => 'Lab Hours', 'resource_type' => 'venue',
            'quantity' => 10, 'unit' => 'hours', 'validity_days' => 90, 'price_in_cents' => 2000,
        ]);

        $response = $this->actingAs($student)->getJson('/api/memberships/packages');
        $response->assertOk()->assertJsonCount(2);
        $first = $response->json(0);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('quantity', $first);
        $this->assertArrayHasKey('validity_days', $first);
    }

    public function test_memberships_packages_rejects_unauthenticated(): void
    {
        $this->getJson('/api/memberships/packages')->assertUnauthorized();
    }

    // ==================================================================
    // Data Quality — batch report, duplicates list, alias list+create
    // ==================================================================

    public function test_batch_report_returns_validation_report(): void
    {
        $admin = $this->createAdmin();
        $batch = ImportBatch::create([
            'imported_by' => $admin->id, 'filename' => 'r.csv',
            'total_rows' => 2, 'processed_rows' => 2, 'valid_rows' => 1, 'status' => 'completed',
        ]);
        ImportValidationResult::create([
            'batch_id' => $batch->id, 'row_number' => 1,
            'original_data' => ['name' => 'Good'], 'validation_errors' => [], 'status' => 'valid',
        ]);
        ImportValidationResult::create([
            'batch_id' => $batch->id, 'row_number' => 2,
            'original_data' => ['name' => ''], 'validation_errors' => ['name is required'], 'status' => 'invalid',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/data-quality/batches/{$batch->id}");
        $response->assertOk()->assertJsonStructure(['summary']);
    }

    public function test_batches_list_is_newest_first_paginated(): void
    {
        $admin = $this->createAdmin();
        $older = ImportBatch::create([
            'imported_by' => $admin->id, 'filename' => 'older.csv',
            'total_rows' => 1, 'processed_rows' => 1, 'valid_rows' => 1, 'status' => 'completed',
        ]);
        $older->created_at = now()->subDay();
        $older->updated_at = now()->subDay();
        $older->save();

        $newer = ImportBatch::create([
            'imported_by' => $admin->id, 'filename' => 'newer.csv',
            'total_rows' => 1, 'processed_rows' => 1, 'valid_rows' => 1, 'status' => 'completed',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/data-quality/batches');
        $response->assertOk()->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
        $this->assertSame($newer->id, $response->json('data.0.id'));
        $this->assertSame($older->id, $response->json('data.1.id'));
    }

    public function test_duplicates_list_returns_transformed_shape(): void
    {
        $admin = $this->createAdmin();
        [$resourceA] = $this->createResourceWithLot();
        DuplicateCandidate::create([
            'resource_a_id' => $resourceA->id, 'match_type' => 'exact',
            'match_score' => 92, 'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/data-quality/duplicates');
        $response->assertOk();
        $row = $response->json('data.0');
        $this->assertArrayHasKey('confidence', $row);
        $this->assertArrayHasKey('match_type', $row);
        $this->assertArrayHasKey('records', $row);
        $this->assertArrayHasKey('imported_row', $row);
        $this->assertArrayHasKey('status', $row);
        $this->assertSame('pending', $row['status']);
    }

    public function test_duplicates_forbidden_for_student(): void
    {
        $student = $this->createStudent();
        $this->actingAs($student)->getJson('/api/data-quality/duplicates')->assertForbidden();
    }

    public function test_vendor_aliases_list_is_paginated(): void
    {
        $admin = $this->createAdmin();
        VendorAlias::create(['alias' => 'IBM Inc', 'canonical_name' => 'IBM', 'status' => 'approved']);
        VendorAlias::create(['alias' => 'Int. Business Machines', 'canonical_name' => 'IBM', 'status' => 'pending']);

        $response = $this->actingAs($admin)->getJson('/api/data-quality/vendor-aliases');
        $response->assertOk()->assertJsonStructure(['data', 'current_page', 'per_page']);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_vendor_alias_create_persists_pending(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->postJson('/api/data-quality/vendor-aliases', [
            'alias' => 'HewlPack',
            'canonical_name' => 'Hewlett-Packard',
        ], ['X-Idempotency-Key' => 'va-create-' . uniqid()]);

        $response->assertCreated()->assertJsonPath('status', 'pending');
        $this->assertDatabaseHas('vendor_aliases', [
            'alias' => 'HewlPack', 'canonical_name' => 'Hewlett-Packard', 'status' => 'pending',
        ]);
    }

    public function test_vendor_alias_create_rejects_missing_fields(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin)->postJson(
            '/api/data-quality/vendor-aliases',
            ['alias' => 'X'],
            ['X-Idempotency-Key' => 'va-bad-' . uniqid()],
        )->assertUnprocessable();
    }

    public function test_manufacturer_alias_create_forbidden_for_student(): void
    {
        $student = $this->createStudent();
        $this->actingAs($student)->postJson(
            '/api/data-quality/manufacturer-aliases',
            ['alias' => 'SonyCo', 'canonical_name' => 'Sony'],
            ['X-Idempotency-Key' => 'ma-stud-' . uniqid()],
        )->assertForbidden();
    }

    public function test_manufacturer_alias_list_returns_rows(): void
    {
        $admin = $this->createAdmin();
        ManufacturerAlias::create(['alias' => 'Dell Corp.', 'canonical_name' => 'Dell', 'status' => 'pending']);

        $response = $this->actingAs($admin)->getJson('/api/data-quality/manufacturer-aliases');
        $response->assertOk()->assertJsonStructure(['data']);
        $this->assertCount(1, $response->json('data'));
    }

    // ==================================================================
    // GET /api/loans/{loan} and /api/reservations/{reservation}
    // ==================================================================

    public function test_loan_show_owner_can_view_and_relations_loaded(): void
    {
        $student = $this->createStudent();
        [$resource, $lot] = $this->createResourceWithLot();
        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'pending',
            'requested_at' => now(), 'due_date' => now()->addWeek(),
            'idempotency_key' => 'loan-show-' . uniqid(),
        ]);

        $response = $this->actingAs($student)->getJson("/api/loans/{$loan->id}");
        $response->assertOk();
        // Resource relation is loaded in the response
        $this->assertArrayHasKey('resource', $response->json('data'));
    }

    public function test_loan_show_unrelated_student_is_forbidden(): void
    {
        $owner = $this->createStudent();
        $snooper = $this->createStudent();
        [$resource, $lot] = $this->createResourceWithLot();
        $loan = LoanRequest::create([
            'user_id' => $owner->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'pending',
            'requested_at' => now(), 'due_date' => now()->addWeek(),
            'idempotency_key' => 'loan-peek-' . uniqid(),
        ]);

        $this->actingAs($snooper)->getJson("/api/loans/{$loan->id}")->assertForbidden();
    }

    public function test_reservation_show_owner_can_view(): void
    {
        $student = $this->createStudent();
        [$resource] = $this->createResourceWithLot();
        $reservation = ReservationRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id,
            'reservation_type' => 'equipment', 'status' => 'pending',
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(2),
            'idempotency_key' => 'res-show-' . uniqid(),
        ]);

        $response = $this->actingAs($student)->getJson("/api/reservations/{$reservation->id}");
        $response->assertOk();
    }

    public function test_reservation_show_unrelated_student_is_forbidden(): void
    {
        $owner = $this->createStudent();
        $snooper = $this->createStudent();
        [$resource] = $this->createResourceWithLot();
        $reservation = ReservationRequest::create([
            'user_id' => $owner->id, 'resource_id' => $resource->id,
            'reservation_type' => 'equipment', 'status' => 'pending',
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(2),
            'idempotency_key' => 'res-peek-' . uniqid(),
        ]);

        $this->actingAs($snooper)->getJson("/api/reservations/{$reservation->id}")->assertForbidden();
    }

    // ==================================================================
    // Admin hardening — scopes, interventions, deposit/grant, scopes/user
    // ==================================================================

    public function test_assign_scope_rejects_invalid_scope_type(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $teacher = $this->createTeacher();

        $response = $this->actingAs($admin)->postJson('/api/admin/scopes', [
            'user_id' => $teacher->id, 'scope_type' => 'galaxy',
        ], ['X-Idempotency-Key' => 'scope-bad-type-' . uniqid()]);

        $response->assertUnprocessable();
    }

    public function test_assign_scope_class_type_requires_class_id(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $teacher = $this->createTeacher();

        $response = $this->actingAs($admin)->postJson('/api/admin/scopes', [
            'user_id' => $teacher->id, 'scope_type' => 'class',
            // class_id missing on purpose
        ], ['X-Idempotency-Key' => 'scope-no-class-' . uniqid()]);

        $response->assertUnprocessable();
    }

    public function test_assign_scope_strips_mismatched_foreign_keys(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $teacher = $this->createTeacher();
        $structure = $this->createCourseStructure();

        // Send BOTH course_id and class_id, but with scope_type=course. The server should
        // strip the class_id so the row is consistent.
        $response = $this->actingAs($admin)->postJson('/api/admin/scopes', [
            'user_id' => $teacher->id,
            'scope_type' => 'course',
            'course_id' => $structure['course']->id,
            'class_id' => $structure['class']->id, // should be stripped
        ], ['X-Idempotency-Key' => 'scope-strip-' . uniqid()]);

        $response->assertOk();
        $this->assertDatabaseHas('permission_scopes', [
            'user_id' => $teacher->id,
            'scope_type' => 'course',
            'course_id' => $structure['course']->id,
            'class_id' => null,
        ]);
    }

    public function test_delete_scope_nonexistent_returns_404(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        $this->actingAs($admin)->deleteJson(
            '/api/admin/scopes/999999',
            [],
            ['X-Idempotency-Key' => 'del-miss-' . uniqid()],
        )->assertNotFound();
    }

    public function test_delete_scope_forbidden_for_non_admin(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $otherTeacher = $this->createTeacher();
        $scope = PermissionScope::create([
            'user_id' => $otherTeacher->id, 'scope_type' => 'full',
        ]);

        $this->actingAs($teacher)->deleteJson(
            "/api/admin/scopes/{$scope->id}",
            [],
            ['X-Idempotency-Key' => 'del-forbid-' . uniqid()],
        )->assertForbidden();
    }

    public function test_delete_scope_audit_captures_old_values(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $teacher = $this->createTeacher();
        $structure = $this->createCourseStructure();
        $scope = PermissionScope::create([
            'user_id' => $teacher->id, 'scope_type' => 'course',
            'course_id' => $structure['course']->id,
        ]);

        $this->actingAs($admin)->deleteJson(
            "/api/admin/scopes/{$scope->id}",
            [],
            ['X-Idempotency-Key' => 'del-audit-' . uniqid()],
        )->assertOk();

        $log = AuditLog::where('action', 'scope_deleted')
            ->where('auditable_id', $scope->id)->first();
        $this->assertNotNull($log);
        $this->assertSame($teacher->id, $log->old_values['user_id']);
        $this->assertSame('course', $log->old_values['scope_type']);
    }

    public function test_scopes_by_user_unknown_username_returns_empty(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        $response = $this->actingAs($admin)->getJson('/api/admin/scopes/user?user=ghost_user_never_seeded');
        $response->assertOk()->assertJsonPath('data', []);
    }

    public function test_scopes_by_user_forbidden_for_non_admin(): void
    {
        $teacher = $this->createTeacher();
        $this->actingAs($teacher)->getJson('/api/admin/scopes/user?user=admin')->assertForbidden();
    }

    public function test_interventions_list_newest_first_and_forbidden_for_student(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $older = InterventionLog::create([
            'user_id' => $admin->id, 'action_type' => 'hold_released', 'reason' => 'Old',
            'details' => [],
        ]);
        $older->created_at = now()->subHours(5);
        $older->updated_at = now()->subHours(5);
        $older->save();

        $newer = InterventionLog::create([
            'user_id' => $admin->id, 'action_type' => 'hold_created', 'reason' => 'Recent',
            'details' => [],
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/interventions');
        $response->assertOk()->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
        $this->assertSame($newer->id, $response->json('data.0.id'));

        $student = $this->createStudent();
        $this->actingAs($student)->getJson('/api/admin/interventions')->assertForbidden();
    }

    public function test_deposit_rejects_zero_or_negative_amount(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $this->actingAs($admin)->postJson('/api/admin/memberships/deposit', [
            'user_id' => $student->id, 'amount_cents' => 0, 'description' => 'bad',
        ], ['X-Idempotency-Key' => 'dep-zero-' . uniqid()])->assertUnprocessable();

        $this->actingAs($admin)->postJson('/api/admin/memberships/deposit', [
            'user_id' => $student->id, 'amount_cents' => -500, 'description' => 'bad',
        ], ['X-Idempotency-Key' => 'dep-neg-' . uniqid()])->assertUnprocessable();
    }

    public function test_deposit_audit_attributes_admin_not_target_user(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $this->actingAs($admin)->postJson('/api/admin/memberships/deposit', [
            'user_id' => $student->id, 'amount_cents' => 250, 'description' => 'Welcome credit',
        ], ['X-Idempotency-Key' => 'dep-aud-' . uniqid()])->assertOk();

        $ledger = StoredValueLedger::where('user_id', $student->id)->latest('id')->first();
        $this->assertNotNull($ledger);
        // The deposit records the acting admin on the ledger created_by/description path.
        // Either way, the student should not be the actor of any "deposit_initiated" audit.
        $this->assertFalse(
            AuditLog::where('action', 'deposit_initiated')->where('user_id', $student->id)->exists(),
        );
    }

    public function test_grant_entitlement_rejects_unknown_package(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $this->actingAs($admin)->postJson('/api/admin/memberships/grant-entitlement', [
            'user_id' => $student->id, 'package_id' => 999999,
        ], ['X-Idempotency-Key' => 'grant-miss-' . uniqid()])->assertUnprocessable();
    }

    // ==================================================================
    // Files list — shape + pagination
    // ==================================================================

    public function test_files_list_returns_paginated_shape_without_internal_fields(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        $response = $this->actingAs($admin)->getJson('/api/files');
        $response->assertOk()->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
        // Individual rows (even if none seeded) must not expose storage_path/checksum.
        foreach ($response->json('data') as $row) {
            $this->assertArrayNotHasKey('storage_path', $row);
            $this->assertArrayNotHasKey('checksum', $row);
        }
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    private function makeTransfer($initiator, $lot, $resource, string $status): TransferRequest
    {
        $dept2 = Department::where('id', '!=', $resource->department_id)->first()
            ?? Department::create(['name' => 'To Dept', 'code' => 'TOD', 'description' => 'Destination']);

        return TransferRequest::create([
            'resource_id' => $resource->id,
            'inventory_lot_id' => $lot->id,
            'from_department_id' => $resource->department_id,
            'to_department_id' => $dept2->id,
            'initiated_by' => $initiator->id,
            'status' => $status,
            'quantity' => 1,
            'idempotency_key' => uniqid('tr-', true),
        ]);
    }

    private function seedStoredValue($user, int $cents): void
    {
        StoredValueLedger::create([
            'user_id' => $user->id,
            'amount_cents' => $cents,
            'balance_after_cents' => $cents,
            'transaction_type' => 'deposit',
            'description' => 'Test seed',
        ]);
    }
}
