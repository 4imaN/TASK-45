<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\{LoanRequest, ReservationRequest, Resource, InventoryLot, Department, Allowlist, Blacklist, PermissionScope, TransferRequest, CustodyRecord, Hold, AuditLog, Checkout};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class PromptComplianceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // --- 1. Equipment reservation overlap detection ---

    public function test_overlapping_equipment_reservation_is_rejected(): void
    {
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();
        $this->assignMembership($s1);
        $this->assignMembership($s2);
        // Only 1 unit — second overlapping reservation should be rejected
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 1, 'total_quantity' => 1]);

        // First reservation: Jan 10 - Jan 17
        $key1 = 'overlap-test-1';
        $this->actingAs($s1)->postJson('/api/reservations', [
            'resource_id' => $resource->id, 'reservation_type' => 'equipment',
            'start_date' => '2025-01-10', 'end_date' => '2025-01-17',
            'idempotency_key' => $key1,
        ], ['X-Idempotency-Key' => $key1])->assertCreated();

        // Overlapping reservation: Jan 15 - Jan 20
        $key2 = 'overlap-test-2';
        $response = $this->actingAs($s2)->postJson('/api/reservations', [
            'resource_id' => $resource->id, 'reservation_type' => 'equipment',
            'start_date' => '2025-01-15', 'end_date' => '2025-01-20',
            'idempotency_key' => $key2,
        ], ['X-Idempotency-Key' => $key2]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('conflict', strtolower($response->json('error')));
    }

    public function test_non_overlapping_equipment_reservation_succeeds(): void
    {
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();
        $this->assignMembership($s1);
        $this->assignMembership($s2);
        [$resource, $lot] = $this->createResourceWithLot();

        $key1 = 'nonoverlap-1';
        $this->actingAs($s1)->postJson('/api/reservations', [
            'resource_id' => $resource->id, 'reservation_type' => 'equipment',
            'start_date' => '2025-01-10', 'end_date' => '2025-01-17',
            'idempotency_key' => $key1,
        ], ['X-Idempotency-Key' => $key1])->assertCreated();

        // Non-overlapping: Jan 20 - Jan 25
        $key2 = 'nonoverlap-2';
        $this->actingAs($s2)->postJson('/api/reservations', [
            'resource_id' => $resource->id, 'reservation_type' => 'equipment',
            'start_date' => '2025-01-20', 'end_date' => '2025-01-25',
            'idempotency_key' => $key2,
        ], ['X-Idempotency-Key' => $key2])->assertCreated();
    }

    // --- 2. Transfer list filtered by department scope ---

    public function test_scoped_teacher_only_sees_department_transfers(): void
    {
        $dept1 = Department::create(['name' => 'TfDept1', 'code' => 'TF1', 'description' => 'T']);
        $dept2 = Department::create(['name' => 'TfDept2', 'code' => 'TF2', 'description' => 'T']);
        $dept3 = Department::create(['name' => 'TfDept3', 'code' => 'TF3', 'description' => 'T']);

        $teacher = $this->createTeacher();
        // Scoped only to dept1
        PermissionScope::create(['user_id' => $teacher->id, 'department_id' => $dept1->id, 'scope_type' => 'department']);

        $admin = $this->createAdmin();
        $this->grantScope($admin);

        // Transfer in teacher's scope (dept1 -> dept2)
        [$r1, $l1] = $this->createResourceWithLot(['department_id' => $dept1->id]);
        TransferRequest::create([
            'resource_id' => $r1->id, 'inventory_lot_id' => $l1->id,
            'from_department_id' => $dept1->id, 'to_department_id' => $dept2->id,
            'initiated_by' => $admin->id, 'status' => 'pending', 'idempotency_key' => uniqid(),
        ]);

        // Transfer outside teacher's scope (dept2 -> dept3)
        [$r2, $l2] = $this->createResourceWithLot(['department_id' => $dept2->id]);
        TransferRequest::create([
            'resource_id' => $r2->id, 'inventory_lot_id' => $l2->id,
            'from_department_id' => $dept2->id, 'to_department_id' => $dept3->id,
            'initiated_by' => $admin->id, 'status' => 'pending', 'idempotency_key' => uniqid(),
        ]);

        $response = $this->actingAs($teacher)->getJson('/api/transfers');
        $response->assertOk();
        // Should see only the transfer involving dept1
        $this->assertEquals(1, count($response->json('data')));
    }

    // --- 3. Recommendation trace restricted to owner/admin ---

    public function test_teacher_cannot_view_student_recommendation_trace(): void
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $this->createResourceWithLot();

        $batchResp = $this->actingAs($student)->postJson('/api/recommendations/for-class', [], ['X-Idempotency-Key' => 'rec-' . uniqid()]);
        $batchId = $batchResp->json('batch_id');

        // Teacher should not see student's trace (no longer any teacher/TA except admin)
        $this->actingAs($teacher)->getJson("/api/recommendations/batches/{$batchId}")->assertForbidden();
    }

    // --- 4. Allowlist/blacklist enforcement ---

    public function test_blacklisted_user_cannot_create_loan(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot();
        $admin = $this->createAdmin();

        Blacklist::create([
            'user_id' => $student->id, 'scope_type' => 'department',
            'scope_id' => $resource->department_id, 'reason' => 'Policy violation',
            'added_by' => $admin->id,
        ]);

        $key = 'blacklist-loan-1';
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('restricted', strtolower($response->json('error')));
    }

    public function test_user_blocked_by_allowlist_when_not_listed(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot();
        $admin = $this->createAdmin();

        // Create an allowlist entry for a DIFFERENT user — student is not on it
        $otherUser = $this->createStudent();
        Allowlist::create([
            'user_id' => $otherUser->id, 'scope_type' => 'department',
            'scope_id' => $resource->department_id, 'reason' => 'Approved user',
            'added_by' => $admin->id,
        ]);

        $key = 'allowlist-block-1';
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('allowlist', strtolower($response->json('error')));
    }

    public function test_allowlisted_user_can_create_loan(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot();
        $admin = $this->createAdmin();

        // Put student on allowlist
        Allowlist::create([
            'user_id' => $student->id, 'scope_type' => 'department',
            'scope_id' => $resource->department_id, 'reason' => 'Approved',
            'added_by' => $admin->id,
        ]);

        $key = 'allowlist-pass-1';
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertCreated();
    }

    // --- 6. AuditLog context field ---

    public function test_audit_log_stores_context(): void
    {
        AuditLog::create([
            'user_id' => $this->createAdmin()->id,
            'action' => 'test_context',
            'context' => ['key' => 'value', 'nested' => ['a' => 1]],
        ]);

        $log = AuditLog::where('action', 'test_context')->first();
        $this->assertNotNull($log);
        $this->assertIsArray($log->context);
        $this->assertEquals('value', $log->context['key']);
    }

    // --- 7. Recommendation factors shape ---

    public function test_recommendation_factors_use_type_label_score(): void
    {
        $student = $this->createStudent();
        $this->createResourceWithLot();

        $response = $this->actingAs($student)->postJson('/api/recommendations/for-class', [], ['X-Idempotency-Key' => 'rec-' . uniqid()]);
        $response->assertOk();

        $recs = $response->json('recommendations');
        foreach ($recs as $rec) {
            foreach ($rec['factors'] as $factor) {
                $this->assertArrayHasKey('type', $factor);
                $this->assertArrayHasKey('label', $factor);
                $this->assertArrayHasKey('score', $factor);
            }
        }
    }
}
