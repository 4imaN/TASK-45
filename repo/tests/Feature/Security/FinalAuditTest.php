<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\{User, LoanRequest, Checkout, Resource, InventoryLot, Department, Course, ClassModel, PermissionScope, TransferRequest, FileAsset, RecommendationBatch};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestHelpers;

class FinalAuditTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // =========================================================================
    // 1. Recommendation generation requires POST + idempotency
    // =========================================================================

    public function test_recommendation_generation_requires_post_with_idempotency(): void
    {
        $student = $this->createStudent();
        $this->createResourceWithLot();

        // POST without idempotency key is rejected
        $this->actingAs($student)->postJson('/api/recommendations/for-class', [])
            ->assertUnprocessable();

        // POST with idempotency key works
        $key = 'rec-gen-1';
        $response = $this->actingAs($student)->postJson('/api/recommendations/for-class', [
            'class_id' => null,
        ], ['X-Idempotency-Key' => $key]);
        $response->assertOk();
        $response->assertJsonStructure(['batch_id', 'recommendations']);

        // Same key replays without creating a duplicate batch
        $response2 = $this->actingAs($student)->postJson('/api/recommendations/for-class', [
            'class_id' => null,
        ], ['X-Idempotency-Key' => $key]);
        $response2->assertOk();
        $this->assertEquals($response->json('batch_id'), $response2->json('batch_id'));
    }

    // =========================================================================
    // 2. Transfer creation enforces from_department scope
    // =========================================================================

    public function test_teacher_cannot_create_transfer_from_unscoped_department(): void
    {
        $deptA = Department::create(['name' => 'ScopedA', 'code' => 'SA', 'description' => 'A']);
        $deptB = Department::create(['name' => 'UnscopedB', 'code' => 'UB', 'description' => 'B']);
        $deptC = Department::create(['name' => 'UnscopedC', 'code' => 'UC', 'description' => 'C']);

        $teacher = $this->createTeacher();
        // Scoped to dept A only
        PermissionScope::create(['user_id' => $teacher->id, 'department_id' => $deptA->id, 'scope_type' => 'department']);

        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $deptB->id]);

        $key = 'transfer-unscoped-dept-1';
        $response = $this->actingAs($teacher)->postJson('/api/transfers', [
            'inventory_lot_id' => $lot->id,
            'from_department_id' => $deptB->id,
            'to_department_id' => $deptC->id,
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertForbidden();
    }

    public function test_teacher_can_create_transfer_from_scoped_department(): void
    {
        $deptA = Department::create(['name' => 'MyScopedDept', 'code' => 'MD', 'description' => 'M']);
        $deptB = Department::create(['name' => 'TargetDept', 'code' => 'TD', 'description' => 'T']);

        $teacher = $this->createTeacher();
        PermissionScope::create(['user_id' => $teacher->id, 'department_id' => $deptA->id, 'scope_type' => 'department']);

        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $deptA->id]);

        $key = 'transfer-scoped-dept-1';
        $response = $this->actingAs($teacher)->postJson('/api/transfers', [
            'inventory_lot_id' => $lot->id,
            'from_department_id' => $deptA->id,
            'to_department_id' => $deptB->id,
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertCreated();
    }

    // =========================================================================
    // 3. File upload authorization for resource attachments
    // =========================================================================

    public function test_student_cannot_attach_file_to_resource(): void
    {
        Storage::fake('local');
        $student = $this->createStudent();
        [$resource, $lot] = $this->createResourceWithLot();

        $response = $this->actingAs($student)->postJson('/api/files/upload', [
            'file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            'attachable_type' => 'resource',
            'attachable_id' => $resource->id,
        ], ['X-Idempotency-Key' => 'file-resource-student-1']);

        $response->assertForbidden();
    }

    public function test_admin_can_attach_file_to_resource(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        [$resource, $lot] = $this->createResourceWithLot();

        $response = $this->actingAs($admin)->postJson('/api/files/upload', [
            'file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            'attachable_type' => 'resource',
            'attachable_id' => $resource->id,
        ], ['X-Idempotency-Key' => 'file-resource-admin-1']);

        $response->assertOk();
    }

    // =========================================================================
    // 5. CheckoutPolicy resolves course scopes
    // =========================================================================

    public function test_course_scoped_teacher_can_checkin_checkout(): void
    {
        $dept = Department::create(['name' => 'ChkDept', 'code' => 'CK', 'description' => 'T']);
        $course = Course::create(['code' => 'CK' . uniqid(), 'name' => 'Checkout Course', 'department_id' => $dept->id]);
        $class = ClassModel::create(['course_id' => $course->id, 'name' => 'CK Section', 'section' => 'A', 'semester' => 'Fall', 'year' => 2024]);

        $teacher = $this->createTeacher();
        // Course scope, not class scope
        PermissionScope::create(['user_id' => $teacher->id, 'course_id' => $course->id, 'scope_type' => 'course']);

        $student = $this->createStudent();
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(),
            'idempotency_key' => uniqid(), 'class_id' => $class->id,
        ]);

        $adminTeacher = $this->createTeacher();
        $this->grantScope($adminTeacher);

        $checkout = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $adminTeacher->id,
            'checked_out_to' => $student->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        // Course-scoped teacher can check in
        $response = $this->actingAs($teacher)->postJson("/api/checkouts/{$checkout->id}/checkin", [
            'condition' => 'good',
        ], ['X-Idempotency-Key' => 'course-checkin-1']);

        $response->assertOk();
    }

    // =========================================================================
    // 4. Import result uses correct labels
    // =========================================================================

    public function test_import_returns_validation_report_not_fake_import_counts(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);

        $key = 'import-label-test-1';
        $response = $this->actingAs($admin)->postJson('/api/data-quality/import', [
            'rows' => [
                ['name' => 'Valid Item'],
                ['name' => ''], // invalid: empty name
            ],
        ], ['X-Idempotency-Key' => $key]);

        $response->assertOk();

        // Response should have summary with total_rows, valid, invalid — NOT imported/updated/failed
        $data = $response->json();
        $this->assertArrayHasKey('summary', $data);
        $this->assertEquals(2, $data['summary']['total_rows']);
        $this->assertArrayNotHasKey('imported', $data);
        $this->assertArrayNotHasKey('updated', $data);
    }
}
