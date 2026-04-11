<?php
namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\{Department, TransferRequest, CustodyRecord};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class TransferApiTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_initiate_transfer(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $dept1 = Department::create(['name' => 'Dept A', 'code' => 'DA', 'description' => 'A']);
        $dept2 = Department::create(['name' => 'Dept B', 'code' => 'DB', 'description' => 'B']);
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $dept1->id]);

        $response = $this->actingAs($teacher)->postJson('/api/transfers', [
            'inventory_lot_id' => $lot->id, 'from_department_id' => $dept1->id,
            'to_department_id' => $dept2->id, 'idempotency_key' => 'transfer-1',
        ], ['X-Idempotency-Key' => 'test-initiate-transfer-1']);
        $response->assertCreated();
        $this->assertDatabaseHas('custody_records', ['custody_type' => 'source_hold']);
    }

    public function test_full_lot_transfer_updates_department(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $dept1 = Department::create(['name' => 'From', 'code' => 'FR', 'description' => 'From']);
        $dept2 = Department::create(['name' => 'To', 'code' => 'TO', 'description' => 'To']);
        // Lot with quantity=5 and transfer for full 5 units
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $dept1->id], ['serviceable_quantity' => 5, 'total_quantity' => 5]);

        $transfer = TransferRequest::create([
            'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept1->id, 'to_department_id' => $dept2->id,
            'initiated_by' => $teacher->id, 'status' => 'in_transit',
            'quantity' => 5, 'idempotency_key' => uniqid(),
        ]);
        CustodyRecord::create([
            'transfer_request_id' => $transfer->id, 'inventory_lot_id' => $lot->id,
            'department_id' => $dept1->id, 'custody_type' => 'in_transit',
            'custodian_id' => $teacher->id, 'started_at' => now(),
        ]);

        $response = $this->actingAs($teacher)->postJson("/api/transfers/{$transfer->id}/complete", [], ['X-Idempotency-Key' => 'test-complete-transfer-1']);
        $response->assertOk();
        // Full lot transfer: lot department should change to destination
        $this->assertEquals($dept2->id, $lot->fresh()->department_id);
    }

    public function test_partial_transfer_splits_lot(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $dept1 = Department::create(['name' => 'Src', 'code' => 'SR', 'description' => 'Src']);
        $dept2 = Department::create(['name' => 'Dst', 'code' => 'DS', 'description' => 'Dst']);
        [$resource, $lot] = $this->createResourceWithLot(['department_id' => $dept1->id], ['serviceable_quantity' => 10, 'total_quantity' => 10]);

        $transfer = TransferRequest::create([
            'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'from_department_id' => $dept1->id, 'to_department_id' => $dept2->id,
            'initiated_by' => $teacher->id, 'status' => 'in_transit',
            'quantity' => 3, 'idempotency_key' => uniqid(),
        ]);
        CustodyRecord::create([
            'transfer_request_id' => $transfer->id, 'inventory_lot_id' => $lot->id,
            'department_id' => $dept1->id, 'custody_type' => 'in_transit',
            'custodian_id' => $teacher->id, 'started_at' => now(),
        ]);

        $response = $this->actingAs($teacher)->postJson("/api/transfers/{$transfer->id}/complete", [], ['X-Idempotency-Key' => 'test-partial-transfer-1']);
        $response->assertOk();

        // Source lot stays in source department, reduced quantity
        $this->assertEquals($dept1->id, $lot->fresh()->department_id);
        $this->assertEquals(7, $lot->fresh()->serviceable_quantity);
        // New lot created in destination department
        $this->assertDatabaseCount('inventory_lots', 2);
        $newLot = \App\Models\InventoryLot::where('id', '!=', $lot->id)->first();
        $this->assertEquals($dept2->id, $newLot->department_id);
        $this->assertEquals(3, $newLot->serviceable_quantity);
    }
}
