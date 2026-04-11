<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\{LoanRequest, Checkout, TransferRequest, ReservationRequest, Hold, PermissionScope, Department, EntitlementPackage, EntitlementGrant, RecommendationBatch, FileAsset};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestHelpers;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // ---- Transfer authorization ----

    public function test_student_cannot_list_transfers(): void
    {
        $student = $this->createStudent();
        $this->actingAs($student)->getJson('/api/transfers')->assertForbidden();
    }

    public function test_student_cannot_create_transfer(): void
    {
        $student = $this->createStudent();
        $dept1 = Department::create(['name' => 'D1', 'code' => 'D1', 'description' => 'T']);
        $dept2 = Department::create(['name' => 'D2', 'code' => 'D2', 'description' => 'T']);
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $dept1->id]);

        $this->actingAs($student)->postJson('/api/transfers', [
            'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept1->id,
            'to_department_id' => $dept2->id,
            'idempotency_key' => 'student-transfer-1',
        ], ['X-Idempotency-Key' => 'student-transfer-1'])->assertForbidden();
    }

    public function test_admin_can_create_transfer(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $dept1 = Department::create(['name' => 'DA', 'code' => 'DA', 'description' => 'A']);
        $dept2 = Department::create(['name' => 'DB', 'code' => 'DB', 'description' => 'B']);
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $dept1->id]);

        $this->actingAs($admin)->postJson('/api/transfers', [
            'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept1->id,
            'to_department_id' => $dept2->id,
            'idempotency_key' => 'admin-transfer-1',
        ], ['X-Idempotency-Key' => 'admin-transfer-1'])->assertCreated();
    }

    public function test_unauthenticated_cannot_access_transfers(): void
    {
        $this->getJson('/api/transfers')->assertUnauthorized();
    }

    // ---- Recommendation authorization ----

    public function test_student_cannot_submit_manual_override(): void
    {
        $student = $this->createStudent();
        $this->createResourceWithLot();
        $batchResp = $this->actingAs($student)->postJson('/api/recommendations/for-class', [], ['X-Idempotency-Key' => 'rec-' . uniqid()]);
        $batchId = $batchResp->json('batch_id');

        $this->actingAs($student)->postJson('/api/recommendations/override', [
            'batch_id' => $batchId,
            'resource_id' => 1,
            'override_type' => 'exclude',
            'reason' => 'This should be blocked for students',
        ], ['X-Idempotency-Key' => 'override-student-1'])->assertForbidden();
    }

    public function test_student_cannot_view_other_users_batch_trace(): void
    {
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();
        $this->createResourceWithLot();

        $batchResp = $this->actingAs($s1)->postJson('/api/recommendations/for-class', [], ['X-Idempotency-Key' => 'rec-' . uniqid()]);
        $batchId = $batchResp->json('batch_id');

        $this->actingAs($s2)->getJson("/api/recommendations/batches/{$batchId}")->assertForbidden();
    }

    public function test_student_can_view_own_batch_trace(): void
    {
        $student = $this->createStudent();
        $this->createResourceWithLot();
        $batchResp = $this->actingAs($student)->postJson('/api/recommendations/for-class', [], ['X-Idempotency-Key' => 'rec-' . uniqid()]);
        $batchId = $batchResp->json('batch_id');

        $this->actingAs($student)->getJson("/api/recommendations/batches/{$batchId}")->assertOk();
    }

    // ---- Data quality authorization ----

    public function test_student_cannot_access_data_quality(): void
    {
        $student = $this->createStudent();
        $this->actingAs($student)->getJson('/api/data-quality/batches')->assertForbidden();
    }

    public function test_admin_can_access_data_quality(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $this->actingAs($admin)->getJson('/api/data-quality/batches')->assertOk();
    }

    // ---- Entitlement grant authorization ----

    public function test_user_cannot_consume_another_users_grant(): void
    {
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();
        $this->assignMembership($s1);

        $package = EntitlementPackage::create([
            'name' => 'Test Pkg', 'description' => 'Test', 'quantity' => 10,
            'unit' => 'hours', 'validity_days' => 60, 'price_in_cents' => 0,
        ]);
        $grant = EntitlementGrant::create([
            'user_id' => $s1->id, 'package_id' => $package->id,
            'remaining_quantity' => 10, 'granted_at' => now(), 'expires_at' => now()->addDays(60),
        ]);

        $this->actingAs($s2)->postJson("/api/memberships/entitlements/{$grant->id}/consume", [
            'quantity' => 1,
        ], ['X-Idempotency-Key' => 'consume-cross-1'])->assertForbidden();
    }

    public function test_owner_can_consume_own_grant(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $package = EntitlementPackage::create([
            'name' => 'Test Pkg2', 'description' => 'Test', 'quantity' => 10,
            'unit' => 'hours', 'validity_days' => 60, 'price_in_cents' => 0,
        ]);
        $grant = EntitlementGrant::create([
            'user_id' => $student->id, 'package_id' => $package->id,
            'remaining_quantity' => 10, 'granted_at' => now(), 'expires_at' => now()->addDays(60),
        ]);

        $this->actingAs($student)->postJson("/api/memberships/entitlements/{$grant->id}/consume", [
            'quantity' => 1,
        ], ['X-Idempotency-Key' => 'consume-own-1'])->assertOk();
    }

    // ---- File authorization ----

    public function test_student_cannot_download_another_students_file(): void
    {
        Storage::fake('local');
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();

        $this->actingAs($s1)->postJson('/api/files/upload', [
            'file' => UploadedFile::fake()->create('private.pdf', 100, 'application/pdf'),
        ], ['X-Idempotency-Key' => 'file-cross-1']);

        $file = FileAsset::first();
        $this->actingAs($s2)->getJson("/api/files/{$file->id}/download")->assertForbidden();
    }

    // ---- Loan scope enforcement ----

    public function test_out_of_scope_teacher_cannot_approve_loan(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $teacher = $this->createTeacher();
        // Teacher has NO scopes assigned
        $structure = $this->createCourseStructure();

        [$resource, $lot] = $this->createResourceWithLot();
        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'pending', 'requested_at' => now(),
            'idempotency_key' => uniqid(), 'class_id' => $structure['class']->id,
        ]);

        $this->actingAs($teacher)->postJson("/api/loans/{$loan->id}/approve", [
            'status' => 'approved',
        ], ['X-Idempotency-Key' => 'approve-oos-1'])->assertForbidden();
    }

    // ---- Admin route protection ----

    public function test_student_cannot_access_admin_routes(): void
    {
        $student = $this->createStudent();
        $this->actingAs($student)->getJson('/api/admin/scopes')->assertForbidden();
        $this->actingAs($student)->getJson('/api/admin/holds')->assertForbidden();
        $this->actingAs($student)->getJson('/api/admin/audit-logs')->assertForbidden();
    }

    // ---- Encrypted field verification ----

    public function test_user_email_is_encrypted_at_rest(): void
    {
        $user = $this->createStudent(['email' => 'test@example.com', 'phone' => '555-1234']);

        // Read directly from DB to verify encryption
        $rawRow = \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->first();

        // Encrypted values should NOT be the plaintext
        $this->assertNotEquals('test@example.com', $rawRow->email);
        $this->assertNotEquals('555-1234', $rawRow->phone);

        // But the model should decrypt transparently
        $loaded = \App\Models\User::find($user->id);
        $this->assertEquals('test@example.com', $loaded->email);
        $this->assertEquals('555-1234', $loaded->phone);
    }

    // ---- Renew authorization ----

    public function test_unrelated_student_cannot_renew_another_students_checkout(): void
    {
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();
        $this->assignMembership($s1, 'Basic');
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $s1->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        $checkout = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher->id, 'checked_out_to' => $s1->id,
            'inventory_lot_id' => $lot->id, 'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        $this->actingAs($s2)->postJson("/api/checkouts/{$checkout->id}/renew", [], [
            'X-Idempotency-Key' => 'renew-cross-1',
        ])->assertForbidden();
    }

    public function test_borrower_can_renew_own_checkout(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student, 'Basic');
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        $checkout = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher->id, 'checked_out_to' => $student->id,
            'inventory_lot_id' => $lot->id, 'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        $this->actingAs($student)->postJson("/api/checkouts/{$checkout->id}/renew", [], [
            'X-Idempotency-Key' => 'renew-own-1',
        ])->assertOk();
    }

    public function test_out_of_scope_teacher_cannot_renew_checkout(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student, 'Basic');
        $teacher1 = $this->createTeacher();
        $this->grantScope($teacher1);
        $teacher2 = $this->createTeacher();
        // teacher2 has NO scopes
        $structure = $this->createCourseStructure();

        [$resource, $lot] = $this->createResourceWithLot();
        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(),
            'idempotency_key' => uniqid(), 'class_id' => $structure['class']->id,
        ]);
        $checkout = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher1->id, 'checked_out_to' => $student->id,
            'inventory_lot_id' => $lot->id, 'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        $this->actingAs($teacher2)->postJson("/api/checkouts/{$checkout->id}/renew", [], [
            'X-Idempotency-Key' => 'renew-oos-1',
        ])->assertForbidden();
    }

    // ---- Encrypted field verification ----

    public function test_user_resource_masks_sensitive_fields(): void
    {
        $user = $this->createStudent(['email' => 'john@example.com', 'phone' => '555-9876']);
        $response = $this->actingAs($user)->getJson('/api/auth/me');
        $response->assertOk();

        // Masked values should contain asterisks
        $email = $response->json('email');
        $phone = $response->json('phone');
        $this->assertStringContainsString('*', $email);
        $this->assertStringContainsString('*', $phone);
        $this->assertNotEquals('john@example.com', $email);
        $this->assertNotEquals('555-9876', $phone);
    }
}
