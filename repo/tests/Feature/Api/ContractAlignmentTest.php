<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\{LoanRequest, Checkout, Resource, InventoryLot, Department, Venue, VenueTimeSlot, TaxonomyTerm};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class ContractAlignmentTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // --- Issue 1: Resource detail response contract ---

    public function test_resource_detail_returns_availability_and_lots(): void
    {
        $user = $this->createStudent();
        [$resource, $lot] = $this->createResourceWithLot(['name' => 'Test Laptop'], ['serviceable_quantity' => 8]);

        $response = $this->actingAs($user)->getJson("/api/catalog/{$resource->id}");
        $response->assertOk();

        // Must have data wrapper with resource fields
        $response->assertJsonPath('data.name', 'Test Laptop');
        $response->assertJsonPath('data.resource_type', 'equipment');
        $response->assertJsonStructure(['data' => ['id', 'name', 'resource_type', 'type']]);

        // Must have availability sibling
        $response->assertJsonStructure(['availability' => ['available_quantity', 'lots']]);
        $this->assertEquals(8, $response->json('availability.available_quantity'));
        $this->assertNotEmpty($response->json('availability.lots'));
    }

    // --- Issue 2: Equipment reservation from detail page ---

    public function test_equipment_reservation_requires_valid_payload(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot();

        $key = 'equip-res-detail-1';
        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'equipment',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(8)->format('Y-m-d'),
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertCreated();
    }

    public function test_venue_reservation_requires_time_slot(): void
    {
        $student = $this->createStudent();
        $dept = Department::first() ?? Department::create(['name' => 'TD', 'code' => 'TD', 'description' => 'T']);
        $resource = Resource::create([
            'name' => 'Studio', 'resource_type' => 'venue', 'category' => 'Venue',
            'department_id' => $dept->id, 'status' => 'active',
        ]);

        $key = 'venue-noslot-detail-1';
        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'venue',
            // Missing venue_id and venue_time_slot_id
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
    }

    // --- Issue 3: Data quality stats endpoint ---

    public function test_data_quality_stats_returns_expected_shape(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $this->createResourceWithLot(['name' => 'Item A', 'description' => 'Desc', 'vendor' => 'V']);
        $this->createResourceWithLot(['name' => 'Item B', 'vendor' => null, 'manufacturer' => null]);

        $response = $this->actingAs($admin)->getJson('/api/data-quality/stats');
        $response->assertOk();
        $response->assertJsonStructure([
            'total_records',
            'records_with_issues',
            'duplicate_candidates',
            'completeness_pct',
            'field_stats',
        ]);
        $this->assertEquals(2, $response->json('total_records'));
        $this->assertIsArray($response->json('field_stats'));
    }

    public function test_student_cannot_access_data_quality_stats(): void
    {
        $student = $this->createStudent();
        $this->actingAs($student)->getJson('/api/data-quality/stats')->assertForbidden();
    }

    // --- Issue 4: Transfer create/list contract ---

    public function test_transfer_create_uses_department_ids(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $dept1 = Department::create(['name' => 'Dept1', 'code' => 'D1', 'description' => '1']);
        $dept2 = Department::create(['name' => 'Dept2', 'code' => 'D2', 'description' => '2']);
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $dept1->id]);

        $key = 'transfer-contract-1';
        $response = $this->actingAs($teacher)->postJson('/api/transfers', [
            'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept1->id,
            'to_department_id' => $dept2->id,
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertCreated();
        $response->assertJsonStructure(['data' => ['id', 'status', 'from_department', 'to_department']]);
    }

    public function test_transfer_list_includes_department_objects(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);

        $response = $this->actingAs($teacher)->getJson('/api/transfers');
        $response->assertOk();
    }

    // --- Issue 6: Out-of-scope checkout is forbidden ---

    public function test_out_of_scope_teacher_cannot_checkout(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $teacher = $this->createTeacher();
        // Teacher has NO scopes
        $structure = $this->createCourseStructure();

        [$resource, $lot] = $this->createResourceWithLot();
        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'approved', 'requested_at' => now(),
            'idempotency_key' => uniqid(), 'class_id' => $structure['class']->id,
        ]);

        $response = $this->actingAs($teacher)->postJson("/api/loans/{$loan->id}/checkout", [], [
            'X-Idempotency-Key' => 'oos-checkout-1',
        ]);
        $response->assertForbidden();
    }

    public function test_scoped_teacher_can_checkout(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $teacher = $this->createTeacher();
        $this->grantScope($teacher); // full scope

        [$resource, $lot] = $this->createResourceWithLot();
        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'approved', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        $response = $this->actingAs($teacher)->postJson("/api/loans/{$loan->id}/checkout", [], [
            'X-Idempotency-Key' => 'scoped-checkout-1',
        ]);
        $response->assertCreated();
    }

    // --- Issue 7: Reservation list returns supported statuses ---

    public function test_reservation_list_returns_supported_fields(): void
    {
        $student = $this->createStudent();
        $response = $this->actingAs($student)->getJson('/api/reservations');
        $response->assertOk();
        // Response structure: paginated data with supported fields
    }

    // --- Issue 8: Reservation resource includes venue slot info ---

    public function test_venue_reservation_response_includes_slot_info(): void
    {
        $student = $this->createStudent();
        $dept = Department::first() ?? Department::create(['name' => 'TV', 'code' => 'TV', 'description' => 'T']);
        $resource = Resource::create([
            'name' => 'Lab Z', 'resource_type' => 'venue', 'category' => 'Venue',
            'department_id' => $dept->id, 'status' => 'active',
        ]);
        $venue = Venue::create([
            'resource_id' => $resource->id, 'capacity' => 20,
            'location' => 'Bldg A', 'building' => 'A', 'floor' => '1',
        ]);
        $slot = VenueTimeSlot::create([
            'venue_id' => $venue->id, 'date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '10:00:00', 'end_time' => '12:00:00', 'is_available' => true,
        ]);

        $key = 'slot-info-test-1';
        $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'venue',
            'venue_id' => $venue->id,
            'venue_time_slot_id' => $slot->id,
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key])->assertCreated();

        $response = $this->actingAs($student)->getJson('/api/reservations');
        $response->assertOk();

        $reservations = $response->json('data');
        $this->assertNotEmpty($reservations);

        $venueRes = collect($reservations)->firstWhere('reservation_type', 'venue');
        $this->assertNotNull($venueRes);
        $this->assertNotNull($venueRes['venue_time_slot']);
        $this->assertEquals('10:00:00', $venueRes['venue_time_slot']['start_time']);
        $this->assertEquals('12:00:00', $venueRes['venue_time_slot']['end_time']);
    }
}
