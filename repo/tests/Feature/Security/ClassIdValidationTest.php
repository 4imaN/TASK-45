<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\{LoanRequest, ReservationRequest, Course, ClassModel, PermissionScope, Allowlist, Blacklist};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class ClassIdValidationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // =========================================================================
    // Blocker: Student cannot forge class_id to manipulate approval scope
    // =========================================================================

    public function test_student_cannot_create_loan_with_unscoped_class_id(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);

        // Student is scoped to class A
        $structureA = $this->createCourseStructure();
        PermissionScope::create(['user_id' => $student->id, 'class_id' => $structureA['class']->id, 'scope_type' => 'class']);

        // Create a different class the student is NOT enrolled in
        $otherCourse = Course::create(['code' => 'FAKE' . uniqid(), 'name' => 'Fake', 'department_id' => $structureA['dept']->id]);
        $fakeClass = ClassModel::create(['course_id' => $otherCourse->id, 'name' => 'Fake Class', 'section' => 'X', 'semester' => 'Fall', 'year' => 2024]);

        [$resource, $lot] = $this->createResourceWithLot();

        $key = 'forge-class-1';
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id,
            'quantity' => 1,
            'idempotency_key' => $key,
            'class_id' => $fakeClass->id, // forged class_id
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('not enrolled', strtolower($response->json('error')));
    }

    public function test_student_can_create_loan_with_valid_class_id(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $structure = $this->createCourseStructure();
        PermissionScope::create(['user_id' => $student->id, 'class_id' => $structure['class']->id, 'scope_type' => 'class']);

        [$resource, $lot] = $this->createResourceWithLot();

        $key = 'valid-class-1';
        $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id,
            'quantity' => 1,
            'idempotency_key' => $key,
            'class_id' => $structure['class']->id,
        ], ['X-Idempotency-Key' => $key])->assertCreated();
    }

    public function test_student_can_create_loan_without_class_id(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot();

        $key = 'no-class-1';
        $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id,
            'quantity' => 1,
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key])->assertCreated();
    }

    public function test_student_cannot_create_reservation_with_unscoped_class_id(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $structure = $this->createCourseStructure();
        // Student has NO scopes

        $otherCourse = Course::create(['code' => 'RES' . uniqid(), 'name' => 'Other', 'department_id' => $structure['dept']->id]);
        $otherClass = ClassModel::create(['course_id' => $otherCourse->id, 'name' => 'Other', 'section' => 'Z', 'semester' => 'Fall', 'year' => 2024]);

        [$resource, $lot] = $this->createResourceWithLot();

        $key = 'forge-res-class-1';
        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'equipment',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'idempotency_key' => $key,
            'class_id' => $otherClass->id,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
    }

    // =========================================================================
    // High: Login includes roles
    // =========================================================================

    public function test_login_response_includes_roles(): void
    {
        $student = $this->createStudent(['password' => bcrypt('TestPass1!')]);

        $response = $this->postJson('/api/auth/login', [
            'username' => $student->username,
            'password' => 'TestPass1!',
        ], ['X-Idempotency-Key' => 'login-' . uniqid()]);

        $response->assertOk();
        $user = $response->json('user');
        $this->assertArrayHasKey('roles', $user);
        $this->assertContains('student', $user['roles']);
    }

    public function test_admin_login_response_includes_admin_role(): void
    {
        $admin = $this->createAdmin(['password' => bcrypt('AdminPass1!')]);

        $response = $this->postJson('/api/auth/login', [
            'username' => $admin->username,
            'password' => 'AdminPass1!',
        ], ['X-Idempotency-Key' => 'login-' . uniqid()]);

        $response->assertOk();
        $this->assertContains('admin', $response->json('user.roles'));
    }

    // =========================================================================
    // High: Allowlist/blacklist management is end-to-end
    // =========================================================================

    public function test_admin_can_list_allowlist_entries(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $this->actingAs($admin)->getJson('/api/admin/allowlists')->assertOk();
    }

    public function test_admin_can_delete_allowlist_entry(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $entry = Allowlist::create([
            'user_id' => $student->id, 'scope_type' => 'department', 'scope_id' => 1,
            'reason' => 'Test', 'added_by' => $admin->id,
        ]);

        $this->actingAs($admin)->delete("/api/admin/allowlists/{$entry->id}", [], [
            'X-Idempotency-Key' => 'del-allow-test-1',
        ])->assertOk();

        $this->assertDatabaseMissing('allowlists', ['id' => $entry->id]);
    }

    public function test_admin_can_list_and_delete_blacklist_entries(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $entry = Blacklist::create([
            'user_id' => $student->id, 'scope_type' => 'global', 'scope_id' => 0,
            'reason' => 'Test ban', 'added_by' => $admin->id,
        ]);

        $this->actingAs($admin)->getJson('/api/admin/blacklists')->assertOk();

        $this->actingAs($admin)->delete("/api/admin/blacklists/{$entry->id}", [], [
            'X-Idempotency-Key' => 'del-black-test-1',
        ])->assertOk();

        $this->assertDatabaseMissing('blacklists', ['id' => $entry->id]);
    }
}
