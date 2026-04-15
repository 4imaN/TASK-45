<?php
namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\CustodyRecord;
use App\Models\Department;
use App\Models\DuplicateCandidate;
use App\Models\EntitlementPackage;
use App\Models\ImportBatch;
use App\Models\ImportValidationResult;
use App\Models\InterventionLog;
use App\Models\ManufacturerAlias;
use App\Models\PermissionScope;
use App\Models\Resource;
use App\Models\TransferRequest;
use App\Models\VendorAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;
use Tests\TestCase;

/**
 * Covers secondary admin/data-quality/membership/transfer actions that are shipped
 * but historically had no direct feature test. Each test hits the route, checks the
 * response, and asserts the persisted side-effect.
 */
class SecondaryRoutesTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // ---------------------------------------------------------------
    // Transfers — mark-in-transit
    // ---------------------------------------------------------------

    public function test_transfer_mark_in_transit_moves_status(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $dept1 = Department::create(['name' => 'From', 'code' => 'FR', 'description' => 'From']);
        $dept2 = Department::create(['name' => 'To', 'code' => 'TO', 'description' => 'To']);
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $dept1->id]);

        $transfer = TransferRequest::create([
            'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept1->id, 'to_department_id' => $dept2->id,
            'initiated_by' => $teacher->id, 'status' => 'approved',
            'quantity' => 1, 'idempotency_key' => uniqid('tr-', true),
        ]);

        $response = $this->actingAs($teacher)->postJson(
            "/api/transfers/{$transfer->id}/in-transit",
            [],
            ['X-Idempotency-Key' => 'ship-' . uniqid()],
        );

        $response->assertOk();
        $this->assertSame('in_transit', $transfer->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Admin scopes — list-by-user and delete
    // ---------------------------------------------------------------

    public function test_admin_can_list_scopes_for_a_user(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $teacher = $this->createTeacher();
        $structure = $this->createCourseStructure();
        PermissionScope::create([
            'user_id' => $teacher->id, 'scope_type' => 'course',
            'course_id' => $structure['course']->id,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/scopes/user?user=' . $teacher->username);
        $response->assertOk()
            ->assertJsonPath('user.username', $teacher->username)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_delete_scope_removes_row_and_audits(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $teacher = $this->createTeacher();
        $scope = PermissionScope::create([
            'user_id' => $teacher->id, 'scope_type' => 'full',
        ]);

        $response = $this->actingAs($admin)->deleteJson(
            "/api/admin/scopes/{$scope->id}",
            [],
            ['X-Idempotency-Key' => 'del-scope-' . uniqid()],
        );

        $response->assertOk();
        $this->assertDatabaseMissing('permission_scopes', ['id' => $scope->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'scope_deleted',
            'auditable_id' => $scope->id,
        ]);
    }

    // ---------------------------------------------------------------
    // Admin — intervention logs + audit-log export
    // ---------------------------------------------------------------

    public function test_admin_intervention_logs_returns_paginated_list(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        InterventionLog::create([
            'user_id' => $admin->id,
            'action_type' => 'hold_released',
            'reason' => 'Manual review',
            'details' => ['note' => 'cleared'],
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/interventions');
        $response->assertOk()->assertJsonPath('data.0.action_type', 'hold_released');
    }

    public function test_admin_audit_log_export_returns_csv(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        AuditLog::create([
            'user_id' => $admin->id, 'action' => 'login',
            'auditable_type' => 'App\\Models\\User', 'auditable_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/audit-logs/export');
        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename=audit_logs.csv');
        $this->assertStringContainsString('id,user_id,action', $response->getContent());
    }

    // ---------------------------------------------------------------
    // Admin memberships — deposit stored value + grant entitlement
    // ---------------------------------------------------------------

    public function test_admin_deposit_stored_value_credits_user(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $response = $this->actingAs($admin)->postJson('/api/admin/memberships/deposit', [
            'user_id' => $student->id,
            'amount_cents' => 5000,
            'description' => 'Opening balance',
        ], ['X-Idempotency-Key' => 'deposit-' . uniqid()]);

        $response->assertOk()->assertJsonPath('balance_cents', 5000);
        $this->assertDatabaseHas('stored_value_ledger', [
            'user_id' => $student->id,
            'amount_cents' => 5000,
        ]);
    }

    public function test_admin_grant_entitlement_creates_grant(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();
        $package = EntitlementPackage::create([
            'name' => 'Print Credits',
            'resource_type' => 'equipment',
            'quantity' => 100,
            'unit' => 'units',
            'validity_days' => 30,
            'price_in_cents' => 1000,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/admin/memberships/grant-entitlement', [
            'user_id' => $student->id,
            'package_id' => $package->id,
        ], ['X-Idempotency-Key' => 'grant-' . uniqid()]);

        $response->assertOk();
        $this->assertDatabaseHas('entitlement_grants', [
            'user_id' => $student->id,
            'package_id' => $package->id,
            'remaining_quantity' => 100,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'entitlement_granted',
        ]);
    }

    // ---------------------------------------------------------------
    // Data quality — remediation, duplicates, vendor-alias PUT
    // ---------------------------------------------------------------

    public function test_remediate_item_updates_status_and_actor(): void
    {
        $admin = $this->createAdmin();
        $batch = ImportBatch::create([
            'imported_by' => $admin->id, 'filename' => 'x.csv',
            'total_rows' => 1, 'processed_rows' => 1, 'valid_rows' => 0, 'status' => 'completed',
        ]);
        $item = ImportValidationResult::create([
            'batch_id' => $batch->id, 'row_number' => 1,
            'original_data' => ['name' => 'Bad Row'], 'validation_errors' => ['name' => 'missing'],
            'status' => 'invalid',
        ]);

        $response = $this->actingAs($admin)->postJson(
            "/api/data-quality/remediation/{$item->id}",
            ['action' => 'remediate'],
            ['X-Idempotency-Key' => 'remediate-' . uniqid()],
        );

        $response->assertOk();
        $this->assertSame('remediated', $item->fresh()->status);
        $this->assertSame($admin->id, $item->fresh()->remediated_by);
    }

    public function test_resolve_duplicate_marks_reviewed(): void
    {
        $admin = $this->createAdmin();
        [$resourceA] = $this->createResourceWithLot();
        $candidate = DuplicateCandidate::create([
            'resource_a_id' => $resourceA->id,
            'match_type' => 'exact', 'match_score' => 95, 'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->postJson(
            "/api/data-quality/duplicates/{$candidate->id}",
            ['action' => 'dismissed'],
            ['X-Idempotency-Key' => 'dup-' . uniqid()],
        );

        $response->assertOk();
        $this->assertSame('dismissed', $candidate->fresh()->status);
        $this->assertSame($admin->id, $candidate->fresh()->reviewed_by);
    }

    public function test_update_vendor_alias_approval(): void
    {
        $admin = $this->createAdmin();
        $alias = VendorAlias::create([
            'alias' => 'IBM Corp.', 'canonical_name' => 'IBM', 'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->putJson(
            "/api/data-quality/vendor-aliases/{$alias->id}",
            ['status' => 'approved'],
            ['X-Idempotency-Key' => 'va-' . uniqid()],
        );

        $response->assertOk();
        $this->assertSame('approved', $alias->fresh()->status);
        $this->assertSame($admin->id, $alias->fresh()->reviewed_by);
    }

    public function test_update_manufacturer_alias_rejection(): void
    {
        $admin = $this->createAdmin();
        $alias = ManufacturerAlias::create([
            'alias' => 'SONY Japan', 'canonical_name' => 'Sony', 'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->putJson(
            "/api/data-quality/manufacturer-aliases/{$alias->id}",
            ['status' => 'rejected'],
            ['X-Idempotency-Key' => 'ma-' . uniqid()],
        );

        $response->assertOk();
        $this->assertSame('rejected', $alias->fresh()->status);
    }
}
