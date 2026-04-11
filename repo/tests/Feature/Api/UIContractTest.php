<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\{LoanRequest, ReservationRequest, Hold, PermissionScope, Department};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class UIContractTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // =========================================================================
    // 1. Loan with class_id is visible to scoped teacher
    // =========================================================================

    public function test_loan_with_class_id_visible_to_scoped_teacher(): void
    {
        $structure = $this->createCourseStructure();
        $teacher = $this->createTeacher();
        PermissionScope::create(['user_id' => $teacher->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class']);

        $student = $this->createStudent();
        $this->assignMembership($student);
        PermissionScope::create(['user_id' => $student->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class']);
        [$resource, $lot] = $this->createResourceWithLot();

        // Student creates loan WITH class_id
        $key = 'class-loan-1';
        $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id,
            'quantity' => 1,
            'idempotency_key' => $key,
            'class_id' => $structure['class']->id,
        ], ['X-Idempotency-Key' => $key])->assertCreated();

        // Teacher sees it in their scoped list
        $response = $this->actingAs($teacher)->getJson('/api/loans?status=pending');
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_loan_without_class_id_not_visible_to_class_scoped_teacher(): void
    {
        $structure = $this->createCourseStructure();
        $teacher = $this->createTeacher();
        PermissionScope::create(['user_id' => $teacher->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class']);

        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot();

        // Loan without class_id
        LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'pending', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        // Teacher should NOT see it (no class_id match)
        $response = $this->actingAs($teacher)->getJson('/api/loans');
        $this->assertEquals(0, count($response->json('data')));
    }

    // =========================================================================
    // 2. Reservation approval via API
    // =========================================================================

    public function test_staff_can_approve_reservation_via_api(): void
    {
        $structure = $this->createCourseStructure();
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);

        $student = $this->createStudent();
        [$resource, $lot] = $this->createResourceWithLot();

        $reservation = ReservationRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id,
            'reservation_type' => 'equipment', 'status' => 'pending',
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(5),
            'idempotency_key' => uniqid(),
        ]);

        $key = 'approve-res-ui-1';
        $response = $this->actingAs($teacher)->postJson("/api/reservations/{$reservation->id}/approve", [
            'status' => 'approved',
        ], ['X-Idempotency-Key' => $key]);

        $response->assertOk();
        $this->assertEquals('approved', $reservation->fresh()->status);
    }

    // =========================================================================
    // 3. Hold creation with valid hold_type
    // =========================================================================

    public function test_admin_can_create_manual_hold(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $key = 'hold-manual-1';
        $response = $this->actingAs($admin)->postJson('/api/admin/holds', [
            'user_id' => $student->id,
            'hold_type' => 'manual',
            'reason' => 'Administrative review required',
        ], ['X-Idempotency-Key' => $key]);

        $response->assertOk();
        $this->assertDatabaseHas('holds', [
            'user_id' => $student->id,
            'hold_type' => 'manual',
            'status' => 'active',
        ]);
    }

    public function test_hold_creation_rejects_invalid_type(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $key = 'hold-invalid-1';
        $response = $this->actingAs($admin)->postJson('/api/admin/holds', [
            'user_id' => $student->id,
            'hold_type' => 'general', // not in manual,system
            'reason' => 'Test',
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
    }

    public function test_hold_list_returns_correct_fields(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        Hold::create([
            'user_id' => $student->id,
            'hold_type' => 'manual',
            'reason' => 'Test hold',
            'status' => 'active',
            'triggered_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/holds');
        $response->assertOk();

        $holds = $response->json('data');
        $this->assertNotEmpty($holds);
        $hold = $holds[0];
        // Verify the response includes the correct field names
        $this->assertArrayHasKey('hold_type', $hold);
        $this->assertArrayHasKey('status', $hold);
        $this->assertArrayHasKey('triggered_at', $hold);
        $this->assertEquals('manual', $hold['hold_type']);
        $this->assertEquals('active', $hold['status']);
    }

    // =========================================================================
    // 7. /my-classes endpoint
    // =========================================================================

    public function test_my_classes_returns_enrolled_classes(): void
    {
        $student = $this->createStudent();
        $structure = $this->createCourseStructure();
        PermissionScope::create(['user_id' => $student->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class']);

        $response = $this->actingAs($student)->getJson('/api/my-classes');
        $response->assertOk();
        $classes = $response->json();
        $this->assertNotEmpty($classes);
        $this->assertEquals($structure['class']->id, $classes[0]['id']);
    }

    public function test_my_classes_resolves_course_scopes(): void
    {
        $student = $this->createStudent();
        $structure = $this->createCourseStructure();
        // Scope to course, not class
        PermissionScope::create(['user_id' => $student->id, 'course_id' => $structure['course']->id, 'scope_type' => 'course']);

        $response = $this->actingAs($student)->getJson('/api/my-classes');
        $response->assertOk();
        $classes = $response->json();
        $this->assertNotEmpty($classes);
    }

    // =========================================================================
    // 8. Classless request rejection for scoped students
    // =========================================================================

    public function test_classless_loan_rejected_for_scoped_student(): void
    {
        $structure = $this->createCourseStructure();
        $student = $this->createStudent();
        $this->assignMembership($student);
        PermissionScope::create(['user_id' => $student->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class']);
        [$resource, $lot] = $this->createResourceWithLot();

        $key = 'classless-loan-reject-1';
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id,
            'quantity' => 1,
            'idempotency_key' => $key,
            // no class_id
        ], ['X-Idempotency-Key' => $key]);

        $response->assertStatus(422);
        $this->assertStringContainsString('class', strtolower($response->json('message') ?? $response->json('error') ?? ''));
    }

    public function test_classless_reservation_rejected_for_scoped_student(): void
    {
        $structure = $this->createCourseStructure();
        $student = $this->createStudent();
        $this->assignMembership($student);
        PermissionScope::create(['user_id' => $student->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class']);
        [$resource, $lot] = $this->createResourceWithLot();

        $key = 'classless-res-reject-1';
        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'equipment',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'idempotency_key' => $key,
            // no class_id
        ], ['X-Idempotency-Key' => $key]);

        $response->assertStatus(422);
        $this->assertStringContainsString('class', strtolower($response->json('message') ?? $response->json('error') ?? ''));
    }

    public function test_scoped_loan_accepted_with_class_id(): void
    {
        $structure = $this->createCourseStructure();
        $student = $this->createStudent();
        $this->assignMembership($student);
        PermissionScope::create(['user_id' => $student->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class']);
        [$resource, $lot] = $this->createResourceWithLot();

        $key = 'scoped-loan-ok-1';
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id,
            'quantity' => 1,
            'idempotency_key' => $key,
            'class_id' => $structure['class']->id,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertCreated();
    }

    public function test_scoped_reservation_accepted_with_class_id(): void
    {
        $structure = $this->createCourseStructure();
        $student = $this->createStudent();
        $this->assignMembership($student);
        PermissionScope::create(['user_id' => $student->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class']);
        [$resource, $lot] = $this->createResourceWithLot();

        $key = 'scoped-res-ok-1';
        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'equipment',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'idempotency_key' => $key,
            'class_id' => $structure['class']->id,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertCreated();
    }

    public function test_scoped_teacher_can_see_and_approve_class_loan(): void
    {
        $structure = $this->createCourseStructure();
        $teacher = $this->createTeacher();
        PermissionScope::create(['user_id' => $teacher->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class']);

        $student = $this->createStudent();
        $this->assignMembership($student);
        PermissionScope::create(['user_id' => $student->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class']);
        [$resource, $lot] = $this->createResourceWithLot();

        // Student creates loan with class context
        $key = 'scope-approve-loan-1';
        $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id,
            'quantity' => 1,
            'idempotency_key' => $key,
            'class_id' => $structure['class']->id,
        ], ['X-Idempotency-Key' => $key])->assertCreated();

        // Teacher can see the loan
        $list = $this->actingAs($teacher)->getJson('/api/loans');
        $list->assertOk();
        $this->assertGreaterThanOrEqual(1, count($list->json('data')));

        // Teacher can approve it
        $loanId = $list->json('data.0.id');
        $approveKey = 'scope-approve-loan-a1';
        $approve = $this->actingAs($teacher)->postJson("/api/loans/{$loanId}/approve", [
            'status' => 'approved',
        ], ['X-Idempotency-Key' => $approveKey]);
        $approve->assertOk();
        $this->assertEquals('approved', LoanRequest::find($loanId)->status);
    }

    public function test_scoped_teacher_can_see_and_approve_class_reservation(): void
    {
        $structure = $this->createCourseStructure();
        $teacher = $this->createTeacher();
        PermissionScope::create(['user_id' => $teacher->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class']);

        $student = $this->createStudent();
        $this->assignMembership($student);
        PermissionScope::create(['user_id' => $student->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class']);
        [$resource, $lot] = $this->createResourceWithLot();

        // Student creates reservation with class context
        $key = 'scope-approve-res-1';
        $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'equipment',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'idempotency_key' => $key,
            'class_id' => $structure['class']->id,
        ], ['X-Idempotency-Key' => $key])->assertCreated();

        // Teacher can see the reservation
        $list = $this->actingAs($teacher)->getJson('/api/reservations');
        $list->assertOk();
        $this->assertGreaterThanOrEqual(1, count($list->json('data')));

        // Teacher can approve it
        $resId = $list->json('data.0.id');
        $approveKey = 'scope-approve-res-a1';
        $approve = $this->actingAs($teacher)->postJson("/api/reservations/{$resId}/approve", [
            'status' => 'approved',
        ], ['X-Idempotency-Key' => $approveKey]);
        $approve->assertOk();
        $this->assertEquals('approved', ReservationRequest::find($resId)->status);
    }
}
