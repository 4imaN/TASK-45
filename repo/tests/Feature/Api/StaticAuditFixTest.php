<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\{
    User, Resource, Department, Assignment, ClassModel, Course,
    PermissionScope, ReservationRequest, InventoryLot, ImportBatch,
    ImportValidationResult, TaxonomyTerm, Membership, MembershipTier,
    AuditLog, Allowlist, Blacklist, StoredValueLedger, FileAsset,
    PointsLedger, Hold, Checkout, LoanRequest, Renewal
};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

/**
 * Tests covering all 10 static audit issues and their fixes.
 */
class StaticAuditFixTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // =========================================================================
    // Issue #1: Forged reservation assignment_id scope
    // =========================================================================

    public function test_reservation_rejects_assignment_from_unrelated_class(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);

        // Create two course structures
        $struct1 = $this->createCourseStructure();
        $struct2 = $this->createCourseStructure();

        // Student has scope for struct1's class only
        $this->grantScope($student, [
            'scope_type' => 'class',
            'class_id' => $struct1['class']->id,
        ]);

        [$resource, $lot] = $this->createResourceWithLot();

        // Try to use struct2's assignment with struct1's class — should be rejected
        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'equipment',
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'class_id' => $struct1['class']->id,
            'assignment_id' => $struct2['assignment']->id,
            'idempotency_key' => 'forged-assign-1',
        ], ['X-Idempotency-Key' => 'forged-assign-1']);

        $response->assertUnprocessable();
        $this->assertStringContainsString('does not belong', strtolower($response->json('error')));
    }

    public function test_reservation_rejects_assignment_student_has_no_scope_for(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);

        $struct = $this->createCourseStructure();
        // Student has NO scope at all

        [$resource, $lot] = $this->createResourceWithLot();

        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'equipment',
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'assignment_id' => $struct['assignment']->id,
            'idempotency_key' => 'no-scope-assign-1',
        ], ['X-Idempotency-Key' => 'no-scope-assign-1']);

        $response->assertUnprocessable();
    }

    public function test_reservation_accepts_valid_scoped_assignment(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);

        $struct = $this->createCourseStructure();
        // Student has class-level scope covering the assignment's class
        $this->grantScope($student, [
            'scope_type' => 'class',
            'class_id' => $struct['class']->id,
        ]);

        [$resource, $lot] = $this->createResourceWithLot();

        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'equipment',
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'class_id' => $struct['class']->id,
            'assignment_id' => $struct['assignment']->id,
            'idempotency_key' => 'valid-assign-1',
        ], ['X-Idempotency-Key' => 'valid-assign-1']);

        $response->assertCreated();
    }

    public function test_teacher_cannot_approve_forged_assignment_scoped_reservation(): void
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $this->assignMembership($student);

        // Two separate course structures
        $struct1 = $this->createCourseStructure();
        $struct2 = $this->createCourseStructure();

        // Teacher has assignment scope on struct1's assignment only
        $this->grantScope($teacher, [
            'scope_type' => 'assignment',
            'assignment_id' => $struct1['assignment']->id,
        ]);

        // Student has scope for struct2
        $this->grantScope($student, [
            'scope_type' => 'class',
            'class_id' => $struct2['class']->id,
        ]);

        [$resource, $lot] = $this->createResourceWithLot();

        // Create a legitimate reservation for struct2
        $reservation = ReservationRequest::create([
            'user_id' => $student->id,
            'resource_id' => $resource->id,
            'reservation_type' => 'equipment',
            'status' => 'pending',
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(3),
            'class_id' => $struct2['class']->id,
            'assignment_id' => $struct2['assignment']->id,
            'idempotency_key' => 'forged-approval-test-1',
        ]);

        // Teacher (scoped to struct1's assignment) should NOT be able to approve struct2's reservation
        $response = $this->actingAs($teacher)->postJson("/api/reservations/{$reservation->id}/approve", [
            'status' => 'approved',
        ], ['X-Idempotency-Key' => 'forged-approval-action-1']);

        $response->assertForbidden();
    }

    // =========================================================================
    // Issue #2: Data-quality validate_only and import type
    // =========================================================================

    public function test_validate_only_does_not_persist_resources(): void
    {
        $admin = $this->createAdmin();
        TaxonomyTerm::create(['type' => 'category', 'value' => 'Electronics']);

        $resourceCountBefore = Resource::count();

        $response = $this->actingAs($admin)->postJson('/api/data-quality/import', [
            'rows' => [
                ['name' => 'Validate Only Item', 'category' => 'Electronics'],
            ],
            'validate_only' => '1',
            'type' => 'resources',
        ], ['X-Idempotency-Key' => 'validate-only-test-1']);

        $response->assertOk();
        $response->assertJsonPath('validate_only', true);
        $this->assertEquals($resourceCountBefore, Resource::count(), 'validate_only should not persist resources');
    }

    public function test_import_persists_on_explicit_import_path(): void
    {
        $admin = $this->createAdmin();
        TaxonomyTerm::create(['type' => 'category', 'value' => 'Electronics']);

        $resourceCountBefore = Resource::count();

        $response = $this->actingAs($admin)->postJson('/api/data-quality/import', [
            'rows' => [
                ['name' => 'Actually Import Item', 'category' => 'Electronics'],
            ],
            'type' => 'resources',
        ], ['X-Idempotency-Key' => 'import-actual-test-1']);

        $response->assertOk();
        $this->assertGreaterThan($resourceCountBefore, Resource::count(), 'Import should persist resources');
    }

    public function test_unsupported_import_type_rejected(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->postJson('/api/data-quality/import', [
            'rows' => [['name' => 'Test']],
            'type' => 'users',
        ], ['X-Idempotency-Key' => 'unsupported-type-test-1']);

        $response->assertUnprocessable();
        $this->assertStringContainsString('not supported', strtolower($response->json('error')));
    }

    public function test_unsupported_import_type_loans_rejected(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->postJson('/api/data-quality/import', [
            'rows' => [['name' => 'Test']],
            'type' => 'loans',
        ], ['X-Idempotency-Key' => 'unsupported-type-loans-1']);

        $response->assertUnprocessable();
    }

    public function test_validate_only_batch_status_is_validated(): void
    {
        $admin = $this->createAdmin();
        TaxonomyTerm::create(['type' => 'category', 'value' => 'Electronics']);

        $response = $this->actingAs($admin)->postJson('/api/data-quality/import', [
            'rows' => [['name' => 'Status Check', 'category' => 'Electronics']],
            'validate_only' => '1',
            'type' => 'resources',
        ], ['X-Idempotency-Key' => 'validate-status-test-1']);

        $response->assertOk();
        $batch = ImportBatch::latest()->first();
        $this->assertNotNull($batch);
        $this->assertEquals('validated', $batch->status);
    }

    // =========================================================================
    // Issue #3: Encryption at rest
    // =========================================================================

    public function test_user_email_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create(['email' => 'secret@example.com']);
        // Reading the raw DB value should not match the plaintext
        $raw = \DB::table('users')->where('id', $user->id)->value('email');
        $this->assertNotEquals('secret@example.com', $raw);
        // But the model accessor should decrypt
        $this->assertEquals('secret@example.com', $user->fresh()->email);
    }

    public function test_user_phone_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create(['phone' => '555-9876']);
        $raw = \DB::table('users')->where('id', $user->id)->value('phone');
        $this->assertNotEquals('555-9876', $raw);
        $this->assertEquals('555-9876', $user->fresh()->phone);
    }

    public function test_stored_value_ledger_description_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $entry = StoredValueLedger::create([
            'user_id' => $user->id,
            'amount_cents' => 500,
            'balance_after_cents' => 500,
            'transaction_type' => 'deposit',
            'description' => 'Payment for order #123',
        ]);

        $raw = \DB::table('stored_value_ledger')->where('id', $entry->id)->value('description');
        $this->assertNotEquals('Payment for order #123', $raw);
        $this->assertEquals('Payment for order #123', $entry->fresh()->description);
    }

    public function test_file_asset_storage_path_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $asset = FileAsset::create([
            'filename' => 'abc123.pdf',
            'original_filename' => 'my_secret_doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum' => hash('sha256', 'test'),
            'storage_path' => '/uploads/private/abc123.pdf',
            'uploaded_by' => $user->id,
        ]);

        $raw = \DB::table('file_assets')->where('id', $asset->id)->value('storage_path');
        $this->assertNotEquals('/uploads/private/abc123.pdf', $raw);
        $this->assertEquals('/uploads/private/abc123.pdf', $asset->fresh()->storage_path);
    }

    public function test_file_asset_original_filename_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $asset = FileAsset::create([
            'filename' => 'def456.pdf',
            'original_filename' => 'confidential_report.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'checksum' => hash('sha256', 'test2'),
            'storage_path' => '/uploads/private/def456.pdf',
            'uploaded_by' => $user->id,
        ]);

        $raw = \DB::table('file_assets')->where('id', $asset->id)->value('original_filename');
        $this->assertNotEquals('confidential_report.pdf', $raw);
        $this->assertEquals('confidential_report.pdf', $asset->fresh()->original_filename);
    }

    // =========================================================================
    // Issue #4: Allowlist/blacklist scope type validation
    // =========================================================================

    public function test_allowlist_rejects_unsupported_scope_type(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $response = $this->actingAs($admin)->postJson('/api/admin/allowlists', [
            'scope_type' => 'course',
            'scope_id' => 1,
            'user_id' => $student->id,
            'reason' => 'test',
        ], ['X-Idempotency-Key' => 'allow-bad-scope-1']);

        $response->assertUnprocessable();
    }

    public function test_blacklist_rejects_unsupported_scope_type(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $response = $this->actingAs($admin)->postJson('/api/admin/blacklists', [
            'scope_type' => 'class',
            'scope_id' => 1,
            'user_id' => $student->id,
            'reason' => 'test',
        ], ['X-Idempotency-Key' => 'black-bad-scope-1']);

        $response->assertUnprocessable();
    }

    public function test_allowlist_accepts_department_scope_type(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();
        $dept = Department::first() ?? Department::create(['name' => 'Test Dept', 'code' => 'TST', 'description' => 'Test']);

        $response = $this->actingAs($admin)->postJson('/api/admin/allowlists', [
            'scope_type' => 'department',
            'scope_id' => $dept->id,
            'user_id' => $student->id,
            'reason' => 'approved',
        ], ['X-Idempotency-Key' => 'allow-dept-scope-1']);

        $response->assertOk();
    }

    public function test_blacklist_accepts_global_scope_type(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $response = $this->actingAs($admin)->postJson('/api/admin/blacklists', [
            'scope_type' => 'global',
            'scope_id' => 0,
            'user_id' => $student->id,
            'reason' => 'policy violation',
        ], ['X-Idempotency-Key' => 'black-global-scope-1']);

        $response->assertOk();
    }

    // =========================================================================
    // Issue #5: Admin dashboard stats contract
    // =========================================================================

    public function test_admin_stats_returns_total_resources_and_total_members(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        // Create some resources
        [$resource, $lot] = $this->createResourceWithLot();

        // Create a membership
        $student = $this->createStudent();
        $this->assignMembership($student);

        $response = $this->actingAs($admin)->getJson('/api/admin/stats');
        $response->assertOk();
        $response->assertJsonStructure([
            'total_users',
            'total_resources',
            'total_members',
            'active_loans',
            'pending_approvals',
            'active_holds',
            'overdue_items',
            'recent_audit',
        ]);
        $this->assertGreaterThanOrEqual(1, $response->json('total_resources'));
        $this->assertGreaterThanOrEqual(1, $response->json('total_members'));
    }

    // =========================================================================
    // Issue #6: Audit log API/UI contract
    // =========================================================================

    public function test_audit_log_returns_user_relation(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'test_action',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/audit-logs');
        $response->assertOk();

        $firstEntry = $response->json('data.0');
        $this->assertArrayHasKey('user', $firstEntry);
    }

    public function test_audit_log_supports_event_filter(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        AuditLog::create(['user_id' => $admin->id, 'action' => 'login']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'scope_assigned']);

        $response = $this->actingAs($admin)->getJson('/api/admin/audit-logs?event=login');
        $response->assertOk();

        foreach ($response->json('data') as $entry) {
            $this->assertStringContainsString('login', $entry['action']);
        }
    }

    public function test_audit_log_supports_search_filter(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        $student = $this->createStudent(['display_name' => 'UniqueTestStudent']);
        AuditLog::create(['user_id' => $student->id, 'action' => 'loan_created']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'scope_assigned']);

        $response = $this->actingAs($admin)->getJson('/api/admin/audit-logs?search=UniqueTestStudent');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_audit_log_supports_range_filter(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        AuditLog::create(['user_id' => $admin->id, 'action' => 'today_action', 'created_at' => now()]);

        $response = $this->actingAs($admin)->getJson('/api/admin/audit-logs?range=today');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_audit_log_supports_per_page(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        for ($i = 0; $i < 5; $i++) {
            AuditLog::create(['user_id' => $admin->id, 'action' => "action_{$i}"]);
        }

        $response = $this->actingAs($admin)->getJson('/api/admin/audit-logs?per_page=2');
        $response->assertOk();
        $this->assertLessThanOrEqual(2, count($response->json('data')));
    }

    // =========================================================================
    // Issue #7: Loan renewal payload contract
    // =========================================================================

    public function test_checkout_resource_includes_renewal_count(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();
        $this->assignMembership($student);

        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id,
            'resource_id' => $resource->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 1,
            'status' => 'checked_out',
            'requested_at' => now(),
            'idempotency_key' => 'renewal-count-test-' . uniqid(),
        ]);

        $checkout = Checkout::create([
            'loan_request_id' => $loan->id,
            'checked_out_by' => $admin->id,
            'checked_out_to' => $student->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 1,
            'checked_out_at' => now(),
            'due_date' => now()->addDays(7),
        ]);

        $checkoutResource = new \App\Http\Resources\CheckoutResource($checkout);
        $array = $checkoutResource->toArray(request());

        $this->assertArrayHasKey('renewal_count', $array);
        $this->assertEquals(0, $array['renewal_count']);
    }

    public function test_resource_resource_includes_loan_rules(): void
    {
        [$resource, $lot] = $this->createResourceWithLot();

        $resourceResource = new \App\Http\Resources\ResourceResource($resource);
        $array = $resourceResource->toArray(request());

        $this->assertArrayHasKey('loan_rules', $array);
        $this->assertArrayHasKey('max_renewals', $array['loan_rules']);
        $this->assertArrayHasKey('max_loan_days', $array['loan_rules']);
    }

    // =========================================================================
    // Issue #8: Validation report download exposed in SPA
    // =========================================================================

    public function test_batch_download_endpoint_returns_report(): void
    {
        $admin = $this->createAdmin();

        $batch = ImportBatch::create([
            'imported_by' => $admin->id,
            'filename' => 'test_download.csv',
            'total_rows' => 2,
            'processed_rows' => 2,
            'valid_rows' => 1,
            'invalid_rows' => 1,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/data-quality/batches/{$batch->id}/download");
        $response->assertOk();
        $response->assertHeader('Content-Disposition', "attachment; filename=validation_report_{$batch->id}.json");
        $response->assertHeader('Content-Type', 'application/json');
    }

    // =========================================================================
    // Issue #9: Broken points balance helper
    // =========================================================================

    public function test_get_points_balance_sums_correct_column(): void
    {
        $user = User::factory()->create();

        PointsLedger::create([
            'user_id' => $user->id,
            'points' => 100,
            'balance_after' => 100,
            'transaction_type' => 'earned',
            'description' => 'Welcome bonus',
        ]);

        PointsLedger::create([
            'user_id' => $user->id,
            'points' => -30,
            'balance_after' => 70,
            'transaction_type' => 'spent',
            'description' => 'Redemption',
        ]);

        $this->assertEquals(70, $user->getPointsBalance());
    }

    // =========================================================================
    // Issue #10: Invalid scopeAvailable() model scope
    // =========================================================================

    public function test_scope_available_returns_active_with_inventory(): void
    {
        $dept = Department::first() ?? Department::create(['name' => 'Test', 'code' => 'TST', 'description' => 'Test']);

        // Active resource with inventory
        $r1 = Resource::create([
            'name' => 'Available Item',
            'resource_type' => 'equipment',
            'category' => 'Computing',
            'department_id' => $dept->id,
            'status' => 'active',
        ]);
        InventoryLot::create([
            'resource_id' => $r1->id,
            'department_id' => $dept->id,
            'lot_number' => 'LOT-avail-' . uniqid(),
            'total_quantity' => 5,
            'serviceable_quantity' => 3,
            'condition' => 'good',
        ]);

        // Active resource with zero serviceable quantity
        $r2 = Resource::create([
            'name' => 'Empty Inventory Item',
            'resource_type' => 'equipment',
            'category' => 'Computing',
            'department_id' => $dept->id,
            'status' => 'active',
        ]);
        InventoryLot::create([
            'resource_id' => $r2->id,
            'department_id' => $dept->id,
            'lot_number' => 'LOT-empty-' . uniqid(),
            'total_quantity' => 5,
            'serviceable_quantity' => 0,
            'condition' => 'poor',
        ]);

        // Delisted resource with inventory
        $r3 = Resource::create([
            'name' => 'Delisted Item',
            'resource_type' => 'equipment',
            'category' => 'Computing',
            'department_id' => $dept->id,
            'status' => 'delisted',
        ]);
        InventoryLot::create([
            'resource_id' => $r3->id,
            'department_id' => $dept->id,
            'lot_number' => 'LOT-delist-' . uniqid(),
            'total_quantity' => 5,
            'serviceable_quantity' => 5,
            'condition' => 'good',
        ]);

        $available = Resource::available()->get();

        $this->assertTrue($available->contains('id', $r1->id), 'Active resource with inventory should be available');
        $this->assertFalse($available->contains('id', $r2->id), 'Resource with zero serviceable quantity should not be available');
        $this->assertFalse($available->contains('id', $r3->id), 'Delisted resource should not be available');
    }

    public function test_scope_available_excludes_resource_with_no_lots(): void
    {
        $dept = Department::first() ?? Department::create(['name' => 'Test', 'code' => 'TST', 'description' => 'Test']);

        $resource = Resource::create([
            'name' => 'No Lots Item',
            'resource_type' => 'equipment',
            'category' => 'Computing',
            'department_id' => $dept->id,
            'status' => 'active',
        ]);

        $available = Resource::available()->get();
        $this->assertFalse($available->contains('id', $resource->id));
    }
}
