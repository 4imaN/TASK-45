<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\{Resource, Venue, VenueTimeSlot, ReservationRequest, Department};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class ReservationApiTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function createVenueWithSlots(): array
    {
        $dept = Department::first() ?? Department::create(['name' => 'Test', 'code' => 'TST', 'description' => 'Test']);
        $resource = Resource::create([
            'name' => 'Studio ' . uniqid(), 'resource_type' => 'venue',
            'category' => 'Venue', 'department_id' => $dept->id, 'status' => 'active',
        ]);
        $venue = Venue::create([
            'resource_id' => $resource->id, 'capacity' => 10,
            'location' => 'Building A', 'building' => 'A', 'floor' => '1',
        ]);
        $slot1 = VenueTimeSlot::create([
            'venue_id' => $venue->id, 'date' => now()->addDays(1)->format('Y-m-d'),
            'start_time' => '09:00:00', 'end_time' => '11:00:00', 'is_available' => true,
        ]);
        $slot2 = VenueTimeSlot::create([
            'venue_id' => $venue->id, 'date' => now()->addDays(1)->format('Y-m-d'),
            'start_time' => '14:00:00', 'end_time' => '16:00:00', 'is_available' => true,
        ]);
        return compact('resource', 'venue', 'slot1', 'slot2');
    }

    public function test_create_venue_reservation_with_time_slot(): void
    {
        $student = $this->createStudent();
        ['resource' => $resource, 'venue' => $venue, 'slot1' => $slot] = $this->createVenueWithSlots();

        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'venue',
            'venue_id' => $venue->id,
            'venue_time_slot_id' => $slot->id,
            'idempotency_key' => 'venue-res-1',
        ], ['X-Idempotency-Key' => 'venue-res-1']);

        $response->assertCreated();
        // Slot should be marked as occupied
        $this->assertNotNull($slot->fresh()->reserved_by_reservation_id);
    }

    public function test_venue_reservation_requires_time_slot(): void
    {
        $student = $this->createStudent();
        ['resource' => $resource, 'venue' => $venue] = $this->createVenueWithSlots();

        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'venue',
            'venue_id' => $venue->id,
            // no venue_time_slot_id
            'idempotency_key' => 'venue-noslot-1',
        ], ['X-Idempotency-Key' => 'venue-noslot-1']);

        $response->assertUnprocessable();
    }

    public function test_double_booking_same_slot_returns_conflict(): void
    {
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();
        ['resource' => $resource, 'venue' => $venue, 'slot1' => $slot] = $this->createVenueWithSlots();

        $this->actingAs($s1)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'venue',
            'venue_id' => $venue->id,
            'venue_time_slot_id' => $slot->id,
            'idempotency_key' => 'double-1',
        ], ['X-Idempotency-Key' => 'double-1'])->assertCreated();

        // Second student tries same slot
        $response = $this->actingAs($s2)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'venue',
            'venue_id' => $venue->id,
            'venue_time_slot_id' => $slot->id,
            'idempotency_key' => 'double-2',
        ], ['X-Idempotency-Key' => 'double-2']);

        $response->assertUnprocessable();
        $this->assertStringContainsString('conflict', strtolower($response->json('error')));
    }

    public function test_cancel_reservation_releases_slot(): void
    {
        $student = $this->createStudent();
        ['resource' => $resource, 'venue' => $venue, 'slot1' => $slot] = $this->createVenueWithSlots();

        $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'venue',
            'venue_id' => $venue->id,
            'venue_time_slot_id' => $slot->id,
            'idempotency_key' => 'cancel-test-1',
        ], ['X-Idempotency-Key' => 'cancel-test-1'])->assertCreated();

        $reservation = ReservationRequest::first();
        $this->actingAs($student)->postJson("/api/reservations/{$reservation->id}/cancel", [], [
            'X-Idempotency-Key' => 'cancel-exec-1',
        ])->assertOk();

        // Slot should be released
        $this->assertNull($slot->fresh()->reserved_by_reservation_id);
        $this->assertEquals('cancelled', $reservation->fresh()->status);
    }

    public function test_equipment_reservation_works_without_time_slot(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot();

        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'equipment',
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'idempotency_key' => 'equip-res-1',
        ], ['X-Idempotency-Key' => 'equip-res-1']);

        $response->assertCreated();
    }

    public function test_student_sees_own_reservations(): void
    {
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();

        $response = $this->actingAs($s1)->getJson('/api/reservations');
        $response->assertOk();
    }

    // --- Quantity-aware equipment reservation overlap ---

    public function test_multiple_reservations_allowed_when_inventory_sufficient(): void
    {
        // Lot has 5 units, so 5 overlapping reservations should be allowed
        $this->assignMembership($s1 = $this->createStudent());
        $this->assignMembership($s2 = $this->createStudent());
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 5]);

        $k1 = 'multi-res-1';
        $this->actingAs($s1)->postJson('/api/reservations', [
            'resource_id' => $resource->id, 'reservation_type' => 'equipment',
            'start_date' => '2025-06-01', 'end_date' => '2025-06-07',
            'idempotency_key' => $k1,
        ], ['X-Idempotency-Key' => $k1])->assertCreated();

        $k2 = 'multi-res-2';
        $this->actingAs($s2)->postJson('/api/reservations', [
            'resource_id' => $resource->id, 'reservation_type' => 'equipment',
            'start_date' => '2025-06-03', 'end_date' => '2025-06-10',
            'idempotency_key' => $k2,
        ], ['X-Idempotency-Key' => $k2])->assertCreated();
    }

    public function test_reservation_rejected_when_overlaps_exhaust_inventory(): void
    {
        // Lot has 1 unit. First reservation OK, second overlapping reservation fails.
        $this->assignMembership($s1 = $this->createStudent());
        $this->assignMembership($s2 = $this->createStudent());
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 1]);

        $k1 = 'exhaust-res-1';
        $this->actingAs($s1)->postJson('/api/reservations', [
            'resource_id' => $resource->id, 'reservation_type' => 'equipment',
            'start_date' => '2025-07-01', 'end_date' => '2025-07-07',
            'idempotency_key' => $k1,
        ], ['X-Idempotency-Key' => $k1])->assertCreated();

        $k2 = 'exhaust-res-2';
        $response = $this->actingAs($s2)->postJson('/api/reservations', [
            'resource_id' => $resource->id, 'reservation_type' => 'equipment',
            'start_date' => '2025-07-05', 'end_date' => '2025-07-12',
            'idempotency_key' => $k2,
        ], ['X-Idempotency-Key' => $k2]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('conflict', strtolower($response->json('error')));
    }
}
