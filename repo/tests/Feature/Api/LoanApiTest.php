<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\{LoanRequest, Checkout};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class LoanApiTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_create_loan_request(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot();

        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => 'test-key-1',
        ], ['X-Idempotency-Key' => 'test-key-1']);
        $response->assertCreated();
        $this->assertDatabaseHas('loan_requests', ['user_id' => $student->id, 'status' => 'pending']);
    }

    public function test_loan_request_conflict_when_over_limit(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student, 'Basic');
        $staff = $this->createTeacher();

        for ($i = 0; $i < 2; $i++) {
            [$r, $l] = $this->createResourceWithLot();
            $loan = LoanRequest::create([
                'user_id' => $student->id, 'resource_id' => $r->id, 'inventory_lot_id' => $l->id,
                'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
            ]);
            Checkout::create([
                'loan_request_id' => $loan->id, 'checked_out_by' => $staff->id, 'checked_out_to' => $student->id,
                'inventory_lot_id' => $l->id, 'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
            ]);
        }

        [$resource, $lot] = $this->createResourceWithLot();
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => 'over-limit-key',
        ], ['X-Idempotency-Key' => 'over-limit-key']);
        $response->assertUnprocessable();
    }

    public function test_approve_loan(): void
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

        $response = $this->actingAs($teacher)->postJson("/api/loans/{$loan->id}/approve", [
            'status' => 'approved',
        ], ['X-Idempotency-Key' => 'approve-1']);
        $response->assertOk();
        $this->assertEquals('approved', $loan->fresh()->status);
    }

    public function test_checkout_flow(): void
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
            'X-Idempotency-Key' => 'checkout-1',
        ]);
        $response->assertCreated();
        $this->assertEquals('checked_out', $loan->fresh()->status);
    }

    public function test_checkin_flow(): void
    {
        $student = $this->createStudent();
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

        $response = $this->actingAs($teacher)->postJson("/api/checkouts/{$checkout->id}/checkin", [
            'condition' => 'good',
        ], ['X-Idempotency-Key' => 'checkin-1']);
        $response->assertOk();
        $this->assertNotNull($checkout->fresh()->returned_at);
    }

    public function test_renew_checkout(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student, 'Basic');
        $teacher = $this->createTeacher();
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        $checkout = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher->id, 'checked_out_to' => $student->id,
            'inventory_lot_id' => $lot->id, 'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        $response = $this->actingAs($student)->postJson("/api/checkouts/{$checkout->id}/renew", [], [
            'X-Idempotency-Key' => 'renew-1',
        ]);
        $response->assertOk()->assertJsonStructure(['new_due_date']);
    }

    public function test_loan_rejected_for_venue_resource(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $dept = \App\Models\Department::first() ?? \App\Models\Department::create(['name' => 'T', 'code' => 'T', 'description' => 'T']);
        $resource = \App\Models\Resource::create([
            'name' => 'Studio X', 'resource_type' => 'venue', 'category' => 'Venue',
            'department_id' => $dept->id, 'status' => 'active',
        ]);
        \App\Models\InventoryLot::create([
            'resource_id' => $resource->id, 'department_id' => $dept->id,
            'lot_number' => 'VEN-1', 'total_quantity' => 1, 'serviceable_quantity' => 1, 'condition' => 'good',
        ]);

        $key = 'venue-loan-1';
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('equipment', strtolower($response->json('error')));
    }

    public function test_student_sees_own_loans_only(): void
    {
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();
        $this->assignMembership($s1);
        $this->assignMembership($s2);
        [$r1, $l1] = $this->createResourceWithLot();
        [$r2, $l2] = $this->createResourceWithLot();

        LoanRequest::create(['user_id' => $s1->id, 'resource_id' => $r1->id, 'inventory_lot_id' => $l1->id, 'quantity' => 1, 'status' => 'pending', 'requested_at' => now(), 'idempotency_key' => uniqid()]);
        LoanRequest::create(['user_id' => $s2->id, 'resource_id' => $r2->id, 'inventory_lot_id' => $l2->id, 'quantity' => 1, 'status' => 'pending', 'requested_at' => now(), 'idempotency_key' => uniqid()]);

        $response = $this->actingAs($s1)->getJson('/api/loans');
        $response->assertOk();
        foreach ($response->json('data') as $loan) {
            $this->assertEquals($s1->id, $loan['user']['id'] ?? $loan['user_id'] ?? null);
        }
    }
}
