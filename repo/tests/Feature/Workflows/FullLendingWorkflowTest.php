<?php
namespace Tests\Feature\Workflows;

use Tests\TestCase;
use App\Models\{LoanRequest, Checkout, PointsLedger};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class FullLendingWorkflowTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_complete_lending_lifecycle(): void
    {
        // Setup
        $student = $this->createStudent();
        $this->assignMembership($student, 'Basic');
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 5]);

        // 1. Student creates loan request
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => 'lifecycle-1',
        ], ['X-Idempotency-Key' => 'workflow-create-loan-1']);
        $response->assertCreated();
        $loanId = $response->json('data.id');

        // 2. Teacher approves
        $response = $this->actingAs($teacher)->postJson("/api/loans/{$loanId}/approve", ['status' => 'approved'], ['X-Idempotency-Key' => 'workflow-approve-loan-1']);
        $response->assertOk();

        // 3. Teacher checks out
        $response = $this->actingAs($teacher)->postJson("/api/loans/{$loanId}/checkout", [], ['X-Idempotency-Key' => 'workflow-checkout-loan-1']);
        $response->assertCreated();
        $checkoutId = $response->json('data.id');

        // 4. Verify availability decreased
        $detailResponse = $this->actingAs($student)->getJson("/api/catalog/{$resource->id}");
        $this->assertEquals(4, $detailResponse->json('availability.available_quantity'));

        // 5. Student renews
        $response = $this->actingAs($student)->postJson("/api/checkouts/{$checkoutId}/renew", [], ['X-Idempotency-Key' => 'workflow-renew-checkout-1']);
        $response->assertOk();

        // 6. Teacher checks in
        $response = $this->actingAs($teacher)->postJson("/api/checkouts/{$checkoutId}/checkin", ['condition' => 'good'], ['X-Idempotency-Key' => 'workflow-checkin-checkout-1']);
        $response->assertOk();

        // 7. Verify points awarded for on-time return
        $this->assertTrue(PointsLedger::where('user_id', $student->id)->where('transaction_type', 'earned')->exists());

        // 8. Verify availability restored
        $detailResponse = $this->actingAs($student)->getJson("/api/catalog/{$resource->id}");
        $this->assertEquals(5, $detailResponse->json('availability.available_quantity'));
    }
}
