<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\{Resource, InventoryLot, Venue, VenueTimeSlot, Department, LoanRequest, Checkout};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class CoreIntegrityTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // =========================================================================
    // 1. Venue reservation: resource must own the venue
    // =========================================================================

    public function test_venue_reservation_rejects_mismatched_resource_and_venue(): void
    {
        $student = $this->createStudent();
        $dept = Department::create(['name' => 'VD', 'code' => 'VD', 'description' => 'T']);

        // Resource A with Venue A
        $resourceA = Resource::create([
            'name' => 'Studio A', 'resource_type' => 'venue', 'category' => 'Venue',
            'department_id' => $dept->id, 'status' => 'active',
        ]);
        $venueA = Venue::create([
            'resource_id' => $resourceA->id, 'capacity' => 10,
            'location' => 'Building A', 'building' => 'A', 'floor' => '1',
        ]);
        $slotA = VenueTimeSlot::create([
            'venue_id' => $venueA->id, 'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '10:00:00', 'end_time' => '12:00:00', 'is_available' => true,
        ]);

        // Resource B (different resource)
        $resourceB = Resource::create([
            'name' => 'Lab B', 'resource_type' => 'venue', 'category' => 'Venue',
            'department_id' => $dept->id, 'status' => 'active',
        ]);

        // Try to book: resource_id=B, venue_id=A (venue A belongs to resource A, not B)
        $key = 'cross-venue-1';
        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resourceB->id,
            'reservation_type' => 'venue',
            'venue_id' => $venueA->id,
            'venue_time_slot_id' => $slotA->id,
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('does not belong', strtolower($response->json('error')));
    }

    public function test_venue_reservation_succeeds_with_matching_resource(): void
    {
        $student = $this->createStudent();
        $dept = Department::create(['name' => 'VE', 'code' => 'VE', 'description' => 'T']);

        $resource = Resource::create([
            'name' => 'Studio Match', 'resource_type' => 'venue', 'category' => 'Venue',
            'department_id' => $dept->id, 'status' => 'active',
        ]);
        $venue = Venue::create([
            'resource_id' => $resource->id, 'capacity' => 10,
            'location' => 'Bldg C', 'building' => 'C', 'floor' => '1',
        ]);
        $slot = VenueTimeSlot::create([
            'venue_id' => $venue->id, 'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '14:00:00', 'end_time' => '16:00:00', 'is_available' => true,
        ]);

        $key = 'valid-venue-1';
        $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'venue',
            'venue_id' => $venue->id,
            'venue_time_slot_id' => $slot->id,
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key])->assertCreated();
    }

    // =========================================================================
    // 2. Multi-lot lending: request succeeds when any lot has availability
    // =========================================================================

    public function test_loan_succeeds_when_second_lot_has_availability(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $dept = Department::create(['name' => 'ML', 'code' => 'ML', 'description' => 'T']);

        $resource = Resource::create([
            'name' => 'Multi-Lot Resource', 'resource_type' => 'equipment',
            'category' => 'Computing', 'department_id' => $dept->id, 'status' => 'active',
        ]);

        // Lot 1: 2 units, both checked out (0 available)
        $lot1 = InventoryLot::create([
            'resource_id' => $resource->id, 'department_id' => $dept->id,
            'lot_number' => 'ML-LOT-1', 'total_quantity' => 2, 'serviceable_quantity' => 2, 'condition' => 'good',
        ]);
        $loan1 = LoanRequest::create([
            'user_id' => $teacher->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot1->id,
            'quantity' => 2, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        Checkout::create([
            'loan_request_id' => $loan1->id, 'checked_out_by' => $teacher->id,
            'checked_out_to' => $teacher->id, 'inventory_lot_id' => $lot1->id,
            'quantity' => 2, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        // Lot 2: 3 units, all available
        InventoryLot::create([
            'resource_id' => $resource->id, 'department_id' => $dept->id,
            'lot_number' => 'ML-LOT-2', 'total_quantity' => 3, 'serviceable_quantity' => 3, 'condition' => 'good',
        ]);

        // Student requests 1 unit — should succeed via lot 2
        $key = 'multi-lot-1';
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertCreated();
    }

    public function test_loan_fails_when_no_lot_has_sufficient_availability(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $teacher = $this->createTeacher();
        $dept = Department::create(['name' => 'MF', 'code' => 'MF', 'description' => 'T']);

        $resource = Resource::create([
            'name' => 'Fully Committed', 'resource_type' => 'equipment',
            'category' => 'Computing', 'department_id' => $dept->id, 'status' => 'active',
        ]);

        // Lot 1: 1 unit, checked out
        $lot1 = InventoryLot::create([
            'resource_id' => $resource->id, 'department_id' => $dept->id,
            'lot_number' => 'MF-LOT-1', 'total_quantity' => 1, 'serviceable_quantity' => 1, 'condition' => 'good',
        ]);
        $loan1 = LoanRequest::create([
            'user_id' => $teacher->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot1->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        Checkout::create([
            'loan_request_id' => $loan1->id, 'checked_out_by' => $teacher->id,
            'checked_out_to' => $teacher->id, 'inventory_lot_id' => $lot1->id,
            'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        $key = 'no-avail-1';
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('insufficient', strtolower($response->json('error')));
    }

    // =========================================================================
    // 3. Request frequency uses actual request count, not audit logs
    // =========================================================================

    public function test_frequency_hold_triggers_from_actual_requests(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);

        // Make 6 POST requests (> 5 threshold) — each creates a new resource to avoid idempotency replays
        for ($i = 0; $i < 6; $i++) {
            [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 10]);
            $key = "freq-test-{$i}";
            $response = $this->actingAs($student)->postJson('/api/loans', [
                'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => $key,
            ], ['X-Idempotency-Key' => $key]);

            if ($i < 5) {
                // First 5 should succeed (or at least not be 429)
                $this->assertNotEquals(429, $response->status(), "Request {$i} should not be rate-limited");
            }
        }

        // The 6th or 7th request should be rate-limited
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 10]);
        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => 'freq-final',
        ], ['X-Idempotency-Key' => 'freq-final']);

        $this->assertEquals(429, $response->status());
        $this->assertDatabaseHas('holds', [
            'user_id' => $student->id, 'hold_type' => 'frequency', 'status' => 'active',
        ]);
    }
}
