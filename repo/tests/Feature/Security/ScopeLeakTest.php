<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\{User, LoanRequest, ReservationRequest, Checkout, PermissionScope, Blacklist};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class ScopeLeakTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // --- List endpoint scope filtering ---

    public function test_teacher_only_sees_loans_in_their_scope(): void
    {
        $teacher = $this->createTeacher();
        $structure = $this->createCourseStructure();

        // Scope teacher to a specific class
        PermissionScope::create([
            'user_id' => $teacher->id,
            'class_id' => $structure['class']->id,
            'scope_type' => 'class',
        ]);

        [$r1, $l1] = $this->createResourceWithLot();
        [$r2, $l2] = $this->createResourceWithLot();
        $student = $this->createStudent();

        // In-scope loan
        LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $r1->id, 'inventory_lot_id' => $l1->id,
            'quantity' => 1, 'status' => 'pending', 'requested_at' => now(),
            'idempotency_key' => 'scoped-loan-1', 'class_id' => $structure['class']->id,
        ]);

        // Out-of-scope loan (no class_id, not in teacher's scope)
        LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $r2->id, 'inventory_lot_id' => $l2->id,
            'quantity' => 1, 'status' => 'pending', 'requested_at' => now(),
            'idempotency_key' => 'unscoped-loan-1',
        ]);

        $response = $this->actingAs($teacher)->getJson('/api/loans');
        $response->assertOk();

        $loans = collect($response->json('data'));
        // Teacher should only see the in-scope loan
        $this->assertEquals(1, $loans->count());
    }

    public function test_teacher_only_sees_reservations_in_their_scope(): void
    {
        $teacher = $this->createTeacher();
        $structure = $this->createCourseStructure();

        PermissionScope::create([
            'user_id' => $teacher->id,
            'class_id' => $structure['class']->id,
            'scope_type' => 'class',
        ]);

        [$r1, $l1] = $this->createResourceWithLot();
        $student = $this->createStudent();

        // In-scope reservation
        ReservationRequest::create([
            'user_id' => $student->id, 'resource_id' => $r1->id,
            'reservation_type' => 'equipment', 'status' => 'pending',
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(3),
            'idempotency_key' => 'scoped-res-1', 'class_id' => $structure['class']->id,
        ]);

        // Out-of-scope reservation
        ReservationRequest::create([
            'user_id' => $student->id, 'resource_id' => $r1->id,
            'reservation_type' => 'equipment', 'status' => 'pending',
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(3),
            'idempotency_key' => 'unscoped-res-1',
        ]);

        $response = $this->actingAs($teacher)->getJson('/api/reservations');
        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_admin_sees_all_loans(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();
        [$r1, $l1] = $this->createResourceWithLot();
        [$r2, $l2] = $this->createResourceWithLot();

        LoanRequest::create(['user_id' => $student->id, 'resource_id' => $r1->id, 'inventory_lot_id' => $l1->id, 'quantity' => 1, 'status' => 'pending', 'requested_at' => now(), 'idempotency_key' => 'a1']);
        LoanRequest::create(['user_id' => $student->id, 'resource_id' => $r2->id, 'inventory_lot_id' => $l2->id, 'quantity' => 1, 'status' => 'pending', 'requested_at' => now(), 'idempotency_key' => 'a2']);

        $response = $this->actingAs($admin)->getJson('/api/loans');
        $this->assertEquals(2, count($response->json('data')));
    }

    // --- Blacklist query correctness ---

    public function test_suspended_user_cannot_login(): void
    {
        $user = $this->createStudent(['password' => bcrypt('Test123!'), 'account_status' => 'suspended']);

        $this->postJson('/api/auth/login', [
            'username' => $user->username, 'password' => 'Test123!',
        ], ['X-Idempotency-Key' => 'login-' . uniqid()])->assertUnprocessable();
    }

    public function test_blacklisted_user_can_login_but_blocked_from_resources(): void
    {
        $user = $this->createStudent(['password' => bcrypt('Test123!')]);
        Blacklist::create([
            'user_id' => $user->id, 'scope_type' => 'department', 'scope_id' => 1,
            'reason' => 'Policy violation', 'added_by' => $this->createAdmin()->id,
        ]);

        // Login succeeds — blacklists are enforced at resource level
        $this->postJson('/api/auth/login', [
            'username' => $user->username, 'password' => 'Test123!',
        ], ['X-Idempotency-Key' => 'login-' . uniqid()])->assertOk();
    }

    public function test_expired_blacklist_allows_login(): void
    {
        $user = $this->createStudent(['password' => bcrypt('Test123!')]);
        Blacklist::create([
            'user_id' => $user->id, 'scope_type' => 'global', 'scope_id' => 0,
            'reason' => 'Temp ban', 'added_by' => $this->createAdmin()->id,
            'expires_at' => now()->subHour(), // expired
        ]);

        $this->postJson('/api/auth/login', [
            'username' => $user->username, 'password' => 'Test123!',
        ], ['X-Idempotency-Key' => 'login-' . uniqid()])->assertOk();
    }

    public function test_other_users_blacklist_does_not_block_login(): void
    {
        $user1 = $this->createStudent(['password' => bcrypt('Test123!')]);
        $user2 = $this->createStudent();
        Blacklist::create([
            'user_id' => $user2->id, 'scope_type' => 'global', 'scope_id' => 0,
            'reason' => 'Other user banned', 'added_by' => $this->createAdmin()->id,
        ]);

        // user1 should NOT be blocked by user2's blacklist
        $this->postJson('/api/auth/login', [
            'username' => $user1->username, 'password' => 'Test123!',
        ], ['X-Idempotency-Key' => 'login-' . uniqid()])->assertOk();
    }

    // --- revealField safety ---

    public function test_reveal_field_rejects_arbitrary_model_type(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        $this->actingAs($admin)->postJson('/api/admin/reveal-field', [
            'model_type' => 'App\\Models\\AuditLog',
            'model_id' => 1,
            'fields' => ['context'],
            'reason' => 'Testing arbitrary model access',
        ], ['X-Idempotency-Key' => 'reveal-arb-1'])->assertUnprocessable();
    }

    public function test_reveal_field_allows_user_email_phone(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent(['email' => 'reveal@test.com', 'phone' => '555-0001']);

        $response = $this->actingAs($admin)->postJson('/api/admin/reveal-field', [
            'model_type' => 'User',
            'model_id' => $student->id,
            'fields' => ['email', 'phone'],
            'reason' => 'Identity verification for lost item',
        ], ['X-Idempotency-Key' => 'reveal-ok-1']);
        $response->assertOk();
        $this->assertEquals('reveal@test.com', $response->json('revealed.email'));
        $this->assertEquals('555-0001', $response->json('revealed.phone'));
    }

    public function test_reveal_field_denied_for_out_of_scope_admin(): void
    {
        $admin = $this->createAdmin();
        // Admin with only a specific class scope, not full
        $structure = $this->createCourseStructure();
        \App\Models\PermissionScope::create([
            'user_id' => $admin->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class',
        ]);

        // Student with NO overlapping scope
        $student = $this->createStudent(['email' => 'noscope@test.com', 'phone' => '555-0002']);

        $this->actingAs($admin)->postJson('/api/admin/reveal-field', [
            'model_type' => 'User', 'model_id' => $student->id,
            'fields' => ['email'], 'reason' => 'Testing scoped reveal enforcement',
        ], ['X-Idempotency-Key' => 'reveal-oos-1'])->assertForbidden();
    }

    // --- Transfer quantity correctness ---

    public function test_pending_transfer_reduces_available_quantity(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $dept1 = \App\Models\Department::create(['name' => 'QD1', 'code' => 'QD1', 'description' => 'T']);
        $dept2 = \App\Models\Department::create(['name' => 'QD2', 'code' => 'QD2', 'description' => 'T']);
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $dept1->id], ['serviceable_quantity' => 10]);

        // Initiate transfer of quantity 3
        $key = 'qty-transfer-1';
        $this->actingAs($teacher)->postJson('/api/transfers', [
            'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept1->id,
            'to_department_id' => $dept2->id,
            'quantity' => 3,
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key])->assertCreated();

        // Available should be 10 - 3 = 7
        $student = $this->createStudent();
        $response = $this->actingAs($student)->getJson("/api/catalog/{$resource->id}");
        $this->assertEquals(7, $response->json('availability.available_quantity'));
    }

    // --- Intervention log creation on holds ---

    public function test_high_value_hold_creates_intervention_log(): void
    {
        $student = $this->createStudent();
        $storedValueService = app(\App\Domain\Membership\StoredValueService::class);
        $storedValueService->deposit($student, 50000, 'Test deposit');

        try {
            $storedValueService->redeem($student, 25000, 'Large test', uniqid());
        } catch (\App\Common\Exceptions\BusinessRuleException $e) {
            // Expected
        }

        $this->assertDatabaseHas('intervention_logs', [
            'user_id' => $student->id,
            'action_type' => 'hold_high_value',
        ]);
    }
}
