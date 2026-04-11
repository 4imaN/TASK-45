<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\{LoanRequest, Checkout, ReservationRequest, Resource, InventoryLot, Department, TransferRequest, CustodyRecord, PermissionScope, EntitlementPackage, EntitlementGrant};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class InventoryIntegrityTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // =========================================================================
    // BLOCKER: Transfer rejects when units are committed elsewhere
    // =========================================================================

    public function test_transfer_rejects_when_units_are_checked_out(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $student = $this->createStudent();
        $dept1 = Department::create(['name' => 'TI1', 'code' => 'TI1', 'description' => 'T']);
        $dept2 = Department::create(['name' => 'TI2', 'code' => 'TI2', 'description' => 'T']);
        [$resource, $lot] = $this->createResourceWithLot(
            ['department_id' => $dept1->id],
            ['serviceable_quantity' => 5, 'total_quantity' => 5]
        );

        // Check out 3 of the 5 units
        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 3, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher->id,
            'checked_out_to' => $student->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 3, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        // Try to transfer all 5 — should fail because 3 are checked out
        $key = 'transfer-committed-1';
        $response = $this->actingAs($teacher)->postJson('/api/transfers', [
            'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept1->id,
            'to_department_id' => $dept2->id,
            'quantity' => 5,
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('available quantity', strtolower($response->json('error')));
    }

    public function test_transfer_succeeds_for_uncommitted_units(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $student = $this->createStudent();
        $dept1 = Department::create(['name' => 'TIS1', 'code' => 'TIS1', 'description' => 'T']);
        $dept2 = Department::create(['name' => 'TIS2', 'code' => 'TIS2', 'description' => 'T']);
        [$resource, $lot] = $this->createResourceWithLot(
            ['department_id' => $dept1->id],
            ['serviceable_quantity' => 5, 'total_quantity' => 5]
        );

        // Check out 2 of the 5
        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 2, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher->id,
            'checked_out_to' => $student->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 2, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        // Transfer remaining 3 — should succeed (5 total - 2 checked out = 3 available)
        $key = 'transfer-uncommitted-1';
        $response = $this->actingAs($teacher)->postJson('/api/transfers', [
            'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept1->id,
            'to_department_id' => $dept2->id,
            'quantity' => 3,
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertCreated();
    }

    public function test_transfer_rejects_when_reservations_consume_availability(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $student = $this->createStudent();
        $dept1 = Department::create(['name' => 'TIR1', 'code' => 'TIR1', 'description' => 'T']);
        $dept2 = Department::create(['name' => 'TIR2', 'code' => 'TIR2', 'description' => 'T']);
        [$resource, $lot] = $this->createResourceWithLot(
            ['department_id' => $dept1->id],
            ['serviceable_quantity' => 3, 'total_quantity' => 3]
        );

        // Approved equipment reservation consumes availability (overlapping today)
        ReservationRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id,
            'reservation_type' => 'equipment', 'status' => 'approved',
            'start_date' => now()->subDay(), 'end_date' => now()->addDays(5),
            'idempotency_key' => uniqid(),
        ]);

        // Available = 3 - 1 reservation = 2. Transfer of 3 should fail.
        $key = 'transfer-reserved-1';
        $response = $this->actingAs($teacher)->postJson('/api/transfers', [
            'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept1->id,
            'to_department_id' => $dept2->id,
            'quantity' => 3,
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
    }

    // =========================================================================
    // HIGH: Row-level locking correctness (state transition tests)
    // =========================================================================

    public function test_duplicate_approval_is_rejected(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'pending', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        // First approval succeeds
        $this->actingAs($teacher)->postJson("/api/loans/{$loan->id}/approve", [
            'status' => 'approved',
        ], ['X-Idempotency-Key' => 'approve-dup-1'])->assertOk();

        // Second approval fails (no longer pending)
        $this->actingAs($teacher)->postJson("/api/loans/{$loan->id}/approve", [
            'status' => 'approved',
        ], ['X-Idempotency-Key' => 'approve-dup-2'])->assertUnprocessable();
    }

    public function test_duplicate_checkout_is_rejected(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'approved', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        // First checkout
        $this->actingAs($teacher)->postJson("/api/loans/{$loan->id}/checkout", [], [
            'X-Idempotency-Key' => 'checkout-dup-1',
        ])->assertCreated();

        // Second checkout fails (no longer approved)
        $this->actingAs($teacher)->postJson("/api/loans/{$loan->id}/checkout", [], [
            'X-Idempotency-Key' => 'checkout-dup-2',
        ])->assertUnprocessable();
    }

    public function test_duplicate_transfer_approval_is_rejected(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $dept1 = Department::create(['name' => 'DTA1', 'code' => 'DTA1', 'description' => 'T']);
        $dept2 = Department::create(['name' => 'DTA2', 'code' => 'DTA2', 'description' => 'T']);
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $dept1->id]);

        $transfer = TransferRequest::create([
            'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept1->id, 'to_department_id' => $dept2->id,
            'initiated_by' => $teacher->id, 'status' => 'pending', 'idempotency_key' => uniqid(),
        ]);

        $this->actingAs($teacher)->postJson("/api/transfers/{$transfer->id}/approve", [], [
            'X-Idempotency-Key' => 'tapprove-1',
        ])->assertOk();

        $this->actingAs($teacher)->postJson("/api/transfers/{$transfer->id}/approve", [], [
            'X-Idempotency-Key' => 'tapprove-2',
        ])->assertUnprocessable();
    }

    // =========================================================================
    // HIGH: User masking — raw serialization should not leak PII
    // =========================================================================

    public function test_allowlist_listing_masks_user_pii(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent(['email' => 'leaked@test.com', 'phone' => '555-SECRET']);

        \App\Models\Allowlist::create([
            'user_id' => $student->id, 'scope_type' => 'department', 'scope_id' => 1,
            'reason' => 'Test', 'added_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/allowlists');
        $response->assertOk();

        $responseText = $response->getContent();
        $this->assertStringNotContainsString('leaked@test.com', $responseText);
        $this->assertStringNotContainsString('555-SECRET', $responseText);
    }

    public function test_transfer_listing_masks_initiator_pii(): void
    {
        $teacher = $this->createTeacher(['email' => 'teacher-pii@test.com']);
        $this->grantScope($teacher);
        $dept1 = Department::create(['name' => 'MaskD1', 'code' => 'MK1', 'description' => 'T']);
        $dept2 = Department::create(['name' => 'MaskD2', 'code' => 'MK2', 'description' => 'T']);
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $dept1->id]);

        $key = 'mask-transfer-1';
        $this->actingAs($teacher)->postJson('/api/transfers', [
            'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept1->id,
            'to_department_id' => $dept2->id,
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key])->assertCreated();

        $response = $this->actingAs($teacher)->getJson('/api/transfers');
        $responseText = $response->getContent();
        $this->assertStringNotContainsString('teacher-pii@test.com', $responseText);
    }
}
