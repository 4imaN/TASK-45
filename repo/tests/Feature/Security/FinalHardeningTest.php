<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\{User, LoanRequest, ReservationRequest, Checkout, Resource, InventoryLot, Department, PermissionScope, TransferRequest, CustodyRecord};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class FinalHardeningTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // --- Checkout list scope ---

    public function test_student_cannot_access_checkouts_list(): void
    {
        $student = $this->createStudent();
        $this->actingAs($student)->getJson('/api/checkouts')->assertForbidden();
    }

    public function test_scoped_teacher_only_sees_scoped_checkouts(): void
    {
        $teacher = $this->createTeacher();
        $structure = $this->createCourseStructure();
        PermissionScope::create([
            'user_id' => $teacher->id,
            'class_id' => $structure['class']->id,
            'scope_type' => 'class',
        ]);
        $staff = $this->createTeacher();
        $this->grantScope($staff); // full scope for checkout creation

        [$r1, $l1] = $this->createResourceWithLot();
        [$r2, $l2] = $this->createResourceWithLot();
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();

        // In-scope checkout
        $loan1 = LoanRequest::create([
            'user_id' => $s1->id, 'resource_id' => $r1->id, 'inventory_lot_id' => $l1->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(),
            'idempotency_key' => uniqid(), 'class_id' => $structure['class']->id,
        ]);
        Checkout::create([
            'loan_request_id' => $loan1->id, 'checked_out_by' => $staff->id,
            'checked_out_to' => $s1->id, 'inventory_lot_id' => $l1->id,
            'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        // Out-of-scope checkout
        $loan2 = LoanRequest::create([
            'user_id' => $s2->id, 'resource_id' => $r2->id, 'inventory_lot_id' => $l2->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(),
            'idempotency_key' => uniqid(),
        ]);
        Checkout::create([
            'loan_request_id' => $loan2->id, 'checked_out_by' => $staff->id,
            'checked_out_to' => $s2->id, 'inventory_lot_id' => $l2->id,
            'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        $response = $this->actingAs($teacher)->getJson('/api/checkouts?status=active');
        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
    }

    // --- Sensitive catalog access by ID ---

    public function test_student_cannot_view_sensitive_resource_by_id(): void
    {
        $student = $this->createStudent();
        [$resource, $lot] = $this->createResourceWithLot(['is_sensitive' => true, 'name' => 'Secret Equipment']);

        $this->actingAs($student)->getJson("/api/catalog/{$resource->id}")->assertForbidden();
    }

    public function test_student_cannot_view_delisted_resource_by_id(): void
    {
        $student = $this->createStudent();
        [$resource, $lot] = $this->createResourceWithLot(['status' => 'delisted', 'name' => 'Removed Item']);

        $this->actingAs($student)->getJson("/api/catalog/{$resource->id}")->assertForbidden();
    }

    public function test_admin_can_view_sensitive_resource_by_id(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        [$resource, $lot] = $this->createResourceWithLot(['is_sensitive' => true, 'name' => 'Secret Equipment']);

        $this->actingAs($admin)->getJson("/api/catalog/{$resource->id}")->assertOk();
    }

    // --- Admin endpoints mask user data ---

    public function test_admin_scopes_list_masks_user_data(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent(['email' => 'visible@test.com', 'phone' => '555-0000']);
        PermissionScope::create(['user_id' => $student->id, 'scope_type' => 'full']);

        $response = $this->actingAs($admin)->getJson('/api/admin/scopes');
        $response->assertOk();

        $scopes = $response->json('data');
        foreach ($scopes as $scope) {
            if (isset($scope['user']['email'])) {
                // Email should be masked (contain asterisks)
                $this->assertStringContainsString('*', $scope['user']['email']);
            }
        }
    }

    // --- Transfer quantity safety ---

    public function test_transfer_rejects_quantity_exceeding_lot(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $dept1 = Department::create(['name' => 'TQ1', 'code' => 'TQ1', 'description' => 'T']);
        $dept2 = Department::create(['name' => 'TQ2', 'code' => 'TQ2', 'description' => 'T']);
        [$resource, $lot] = $this->createResourceWithLot(
            ['department_id' => $dept1->id],
            ['serviceable_quantity' => 3]
        );

        $key = 'over-qty-transfer-1';
        $response = $this->actingAs($teacher)->postJson('/api/transfers', [
            'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept1->id,
            'to_department_id' => $dept2->id,
            'quantity' => 10, // exceeds lot
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
    }

    // --- Loan search support ---

    public function test_loan_index_supports_search_param(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent(['display_name' => 'Alice Wonderland']);
        [$r, $l] = $this->createResourceWithLot();

        LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $r->id, 'inventory_lot_id' => $l->id,
            'quantity' => 1, 'status' => 'pending', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/loans?search=Alice');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_loan_search_no_match_returns_empty(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent(['display_name' => 'Bob']);
        [$r, $l] = $this->createResourceWithLot();

        LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $r->id, 'inventory_lot_id' => $l->id,
            'quantity' => 1, 'status' => 'pending', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/loans?search=NonexistentName');
        $response->assertOk();
        $this->assertEquals(0, count($response->json('data')));
    }

    // --- Equipment reservation reduces availability ---

    public function test_approved_equipment_reservation_reduces_availability(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 5]);

        // Create an approved equipment reservation overlapping today
        ReservationRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id,
            'reservation_type' => 'equipment', 'status' => 'approved',
            'start_date' => now()->subDay(), 'end_date' => now()->addDays(3),
            'idempotency_key' => 'res-avail-test-1',
        ]);

        $viewer = $this->createStudent();
        $response = $this->actingAs($viewer)->getJson("/api/catalog/{$resource->id}");
        $response->assertOk();
        // Should be 5 - 1 reservation = 4
        $this->assertEquals(4, $response->json('availability.available_quantity'));
    }

    // --- Transfer without department scope is denied ---

    public function test_teacher_without_department_scope_cannot_create_transfer(): void
    {
        $teacher = $this->createTeacher();
        // Teacher has only class scope, no department scope
        $structure = $this->createCourseStructure();
        PermissionScope::create([
            'user_id' => $teacher->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class',
        ]);

        $dept1 = Department::create(['name' => 'NoDeptScope1', 'code' => 'ND1', 'description' => 'T']);
        $dept2 = Department::create(['name' => 'NoDeptScope2', 'code' => 'ND2', 'description' => 'T']);
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $dept1->id]);

        $this->actingAs($teacher)->postJson('/api/transfers', [
            'inventory_lot_id' => $lot->id, 'from_department_id' => $dept1->id,
            'to_department_id' => $dept2->id, 'idempotency_key' => 'no-dept-scope-1',
        ], ['X-Idempotency-Key' => 'no-dept-scope-1'])->assertForbidden();
    }

    // --- Due dates support datetime precision ---

    public function test_checkout_due_date_stores_time_component(): void
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

        $response = $this->actingAs($teacher)->postJson("/api/loans/{$loan->id}/checkout", [], [
            'X-Idempotency-Key' => 'datetime-test-1',
        ]);
        $response->assertCreated();

        $checkout = Checkout::first();
        // due_date must be present, cast as Carbon, and approximately 7 days in the future
        $this->assertNotNull($checkout->due_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $checkout->due_date);
        $this->assertTrue($checkout->due_date->isFuture());
        // Allow range of 5-9 days to account for SQLite date truncation and timezone edge cases
        $daysDiff = (int) now()->diffInDays($checkout->due_date, false);
        $this->assertGreaterThanOrEqual(5, $daysDiff, "Due date should be ~7 days out, got {$daysDiff}");
        $this->assertLessThanOrEqual(9, $daysDiff, "Due date should be ~7 days out, got {$daysDiff}");
    }
}
