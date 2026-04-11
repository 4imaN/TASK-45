<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\{User, LoanRequest, ReservationRequest, TransferRequest, CustodyRecord, Department, Course, ClassModel, PermissionScope, RecommendationBatch, RuleTrace, Blacklist, Allowlist};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class PolicyBoundaryTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // =========================================================================
    // Transfer: destination-only scope is NOT enough to approve
    // =========================================================================

    public function test_teacher_with_destination_scope_cannot_approve_transfer(): void
    {
        $deptSrc = Department::create(['name' => 'Source', 'code' => 'SRC', 'description' => 'S']);
        $deptDst = Department::create(['name' => 'Dest', 'code' => 'DST', 'description' => 'D']);

        $teacher = $this->createTeacher();
        // Scoped to destination department only
        PermissionScope::create(['user_id' => $teacher->id, 'department_id' => $deptDst->id, 'scope_type' => 'department']);

        $admin = $this->createAdmin();
        $this->grantScope($admin);
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $deptSrc->id]);

        $transfer = TransferRequest::create([
            'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'from_department_id' => $deptSrc->id, 'to_department_id' => $deptDst->id,
            'initiated_by' => $admin->id, 'status' => 'pending', 'idempotency_key' => uniqid(),
        ]);

        $this->actingAs($teacher)->postJson("/api/transfers/{$transfer->id}/approve", [], [
            'X-Idempotency-Key' => 'dst-only-approve-1',
        ])->assertForbidden();
    }

    public function test_teacher_with_source_scope_can_approve_transfer(): void
    {
        $deptSrc = Department::create(['name' => 'SrcOK', 'code' => 'SOK', 'description' => 'S']);
        $deptDst = Department::create(['name' => 'DstOK', 'code' => 'DOK', 'description' => 'D']);

        $teacher = $this->createTeacher();
        PermissionScope::create(['user_id' => $teacher->id, 'department_id' => $deptSrc->id, 'scope_type' => 'department']);

        $admin = $this->createAdmin();
        $this->grantScope($admin);
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $deptSrc->id]);

        $transfer = TransferRequest::create([
            'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'from_department_id' => $deptSrc->id, 'to_department_id' => $deptDst->id,
            'initiated_by' => $admin->id, 'status' => 'pending', 'idempotency_key' => uniqid(),
        ]);

        $this->actingAs($teacher)->postJson("/api/transfers/{$transfer->id}/approve", [], [
            'X-Idempotency-Key' => 'src-approve-1',
        ])->assertOk();
    }

    // =========================================================================
    // Transfer: course scope derives department access
    // =========================================================================

    public function test_course_scoped_teacher_can_view_transfers_for_course_department(): void
    {
        $dept = Department::create(['name' => 'CourseDept', 'code' => 'CDT', 'description' => 'T']);
        $course = Course::create(['code' => 'TRF' . uniqid(), 'name' => 'Transfer Course', 'department_id' => $dept->id]);

        $teacher = $this->createTeacher();
        PermissionScope::create(['user_id' => $teacher->id, 'course_id' => $course->id, 'scope_type' => 'course']);

        // Transfer from this department should be visible
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $deptOther = Department::create(['name' => 'Other', 'code' => 'OTH', 'description' => 'O']);
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $dept->id]);

        TransferRequest::create([
            'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept->id, 'to_department_id' => $deptOther->id,
            'initiated_by' => $admin->id, 'status' => 'pending', 'idempotency_key' => uniqid(),
        ]);

        $response = $this->actingAs($teacher)->getJson('/api/transfers');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    // =========================================================================
    // Recommendation: staff cannot view other user's batch trace
    // =========================================================================

    public function test_teacher_cannot_view_student_recommendation_trace(): void
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $this->createResourceWithLot();

        $batchResp = $this->actingAs($student)->postJson('/api/recommendations/for-class', [], [
            'X-Idempotency-Key' => 'rec-priv-' . uniqid(),
        ]);
        $batchId = $batchResp->json('batch_id');

        $this->actingAs($teacher)->getJson("/api/recommendations/batches/{$batchId}")
            ->assertForbidden();
    }

    public function test_student_can_view_own_trace(): void
    {
        $student = $this->createStudent();
        $this->createResourceWithLot();

        $batchResp = $this->actingAs($student)->postJson('/api/recommendations/for-class', [], [
            'X-Idempotency-Key' => 'rec-own-' . uniqid(),
        ]);
        $batchId = $batchResp->json('batch_id');

        $this->actingAs($student)->getJson("/api/recommendations/batches/{$batchId}")
            ->assertOk();
    }

    // =========================================================================
    // Blacklist: department blacklist does NOT block login
    // =========================================================================

    public function test_department_blacklisted_user_can_still_login(): void
    {
        $user = $this->createStudent(['password' => bcrypt('TestPass1!')]);
        $admin = $this->createAdmin();
        $dept = Department::first() ?? Department::create(['name' => 'BL', 'code' => 'BL', 'description' => 'T']);

        Blacklist::create([
            'user_id' => $user->id, 'scope_type' => 'department',
            'scope_id' => $dept->id, 'reason' => 'Dept ban',
            'added_by' => $admin->id,
        ]);

        // Login should succeed — department blacklist doesn't block auth
        $this->postJson('/api/auth/login', [
            'username' => $user->username, 'password' => 'TestPass1!',
        ], ['X-Idempotency-Key' => 'login-' . uniqid()])->assertOk();
    }

    public function test_department_blacklisted_user_blocked_from_resource_access(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $admin = $this->createAdmin();
        [$resource, $lot] = $this->createResourceWithLot();

        Blacklist::create([
            'user_id' => $student->id, 'scope_type' => 'department',
            'scope_id' => $resource->department_id, 'reason' => 'Dept restriction',
            'added_by' => $admin->id,
        ]);

        $key = 'bl-resource-1';
        $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key])->assertUnprocessable();
    }

    // =========================================================================
    // Idempotency: logout requires key
    // =========================================================================

    public function test_logout_requires_idempotency_key(): void
    {
        $user = $this->createStudent();
        // POST without key should be rejected (logout is no longer exempt)
        $this->actingAs($user)->postJson('/api/auth/logout')
            ->assertUnprocessable();
    }

    // =========================================================================
    // Membership audit: admin attribution
    // =========================================================================

    public function test_tier_assignment_logs_admin_not_target_user(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();
        $tier = \App\Models\MembershipTier::firstOrCreate(['name' => 'Basic'], [
            'description' => 'B', 'max_active_loans' => 2, 'max_loan_days' => 7,
            'max_renewals' => 1, 'points_multiplier' => 1.00,
        ]);

        $key = 'tier-assign-1';
        $this->actingAs($admin)->postJson('/api/admin/memberships/assign-tier', [
            'user_id' => $student->id, 'tier_id' => $tier->id,
        ], ['X-Idempotency-Key' => $key])->assertOk();

        // Audit log should show admin as the actor, not the student
        $log = \App\Models\AuditLog::where('action', 'membership_tier_assigned')->first();
        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->user_id);
    }
}
