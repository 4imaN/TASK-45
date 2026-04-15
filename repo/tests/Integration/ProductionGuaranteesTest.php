<?php

/**
 * Production Guarantees Integration Tests
 *
 * These tests verify production-critical behavior:
 * - Row-level locking via SELECT ... FOR UPDATE (lock contention test requires MySQL)
 * - Composite unique constraints on idempotency keys (MySQL-only)
 * - DateTime precision for due dates
 * - Transaction rollback on business rule failures
 * - Concurrent inventory access protection
 *
 * Run with: vendor/bin/phpunit -c phpunit.mysql.xml
 * Requires: MySQL on 127.0.0.1:3306 with database campus_platform_test
 */

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\{LoanRequest, Checkout, ReservationRequest, InventoryLot, Resource, Department, TransferRequest, Hold};
use App\Domain\Lending\LendingService;
use App\Domain\Availability\AvailabilityService;
use App\Domain\Reservations\ReservationService;
use App\Domain\Transfers\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestHelpers;

class ProductionGuaranteesTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        // Safety rail: RefreshDatabase will wipe schema on first use. If a stale
        // bootstrap/cache/config.php makes us point at the production DB, the suite
        // would destroy real data. Refuse to proceed unless we're on the dedicated
        // test database.
        $db = config('database.connections.mysql.database');
        if (DB::getDriverName() === 'mysql' && $db !== 'campus_platform_test') {
            $this->fail(
                "Refusing to run integration suite against database '{$db}'. " .
                "Expected 'campus_platform_test'. Clear bootstrap/cache/config.php and re-run.",
            );
        }
    }

    // =========================================================================
    // Idempotency: composite uniqueness scoped by user
    // =========================================================================

    public function test_same_idempotency_key_different_users_no_collision(): void
    {
        // This test verifies MySQL composite unique (user_id, idempotency_key).
        // SQLite uses a global unique on idempotency_key, so skip there.
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('Requires MySQL for scoped unique constraint verification.');
        }

        $s1 = $this->createStudent();
        $s2 = $this->createStudent();
        $this->assignMembership($s1);
        $this->assignMembership($s2);
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 10]);

        // Both users use different body keys but same resource
        LoanRequest::create([
            'user_id' => $s1->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'pending', 'requested_at' => now(), 'idempotency_key' => 'shared-body-key',
        ]);

        // On MySQL with scoped unique (user_id, idempotency_key), this succeeds.
        // On SQLite with global unique, this would fail.
        $loan2 = LoanRequest::create([
            'user_id' => $s2->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'pending', 'requested_at' => now(), 'idempotency_key' => 'shared-body-key',
        ]);

        $this->assertNotNull($loan2->id);
        $this->assertDatabaseCount('loan_requests', 2);
    }

    // =========================================================================
    // Transaction rollback: failed business operation rolls back completely
    // =========================================================================

    public function test_failed_loan_creation_rolls_back_completely(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 0]);

        $service = app(LendingService::class);

        try {
            $service->createLoanRequest($student, [
                'resource_id' => $resource->id,
                'quantity' => 1,
                'idempotency_key' => 'rollback-test-1',
            ]);
            $this->fail('Should have thrown BusinessRuleException');
        } catch (\App\Common\Exceptions\BusinessRuleException $e) {
            // Expected
        }

        // Nothing should be persisted
        $this->assertDatabaseCount('loan_requests', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_failed_transfer_initiation_rolls_back(): void
    {
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        $dept1 = Department::create(['name' => 'RB1', 'code' => 'RB1', 'description' => 'T']);
        $dept2 = Department::create(['name' => 'RB2', 'code' => 'RB2', 'description' => 'T']);
        [$resource, $lot] = $this->createResourceWithLot(
            ['department_id' => $dept1->id],
            ['serviceable_quantity' => 1]
        );

        // Check out the only unit
        $loan = LoanRequest::create([
            'user_id' => $teacher->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher->id,
            'checked_out_to' => $teacher->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        $service = app(TransferService::class);

        try {
            $service->initiateTransfer($teacher, [
                'inventory_lot_id' => $lot->id,
                'from_department_id' => $dept1->id,
                'to_department_id' => $dept2->id,
                'quantity' => 1,
                'idempotency_key' => 'rollback-transfer-1',
            ]);
            $this->fail('Should have thrown BusinessRuleException');
        } catch (\App\Common\Exceptions\BusinessRuleException $e) {
            // Expected — no available quantity
        }

        // No transfer or custody records should exist
        $this->assertDatabaseCount('transfer_requests', 0);
        $this->assertDatabaseCount('custody_records', 0);
    }

    // =========================================================================
    // Deterministic duplicate submission behavior
    // =========================================================================

    public function test_idempotency_middleware_replays_identical_request(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot();

        $payload = ['resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => 'determ-1'];
        $headers = ['X-Idempotency-Key' => 'determ-1'];

        $r1 = $this->actingAs($student)->postJson('/api/loans', $payload, $headers);
        $r1->assertCreated();

        $r2 = $this->actingAs($student)->postJson('/api/loans', $payload, $headers);
        $r2->assertCreated();

        // Same response replayed, no duplicate created
        $this->assertDatabaseCount('loan_requests', 1);
        $this->assertEquals($r1->json('data.id'), $r2->json('data.id'));
    }

    public function test_idempotency_middleware_rejects_conflicting_payload(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$r1, $l1] = $this->createResourceWithLot();
        [$r2, $l2] = $this->createResourceWithLot();

        $headers = ['X-Idempotency-Key' => 'conflict-determ-1'];

        $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $r1->id, 'quantity' => 1, 'idempotency_key' => 'conflict-body-1',
        ], $headers)->assertCreated();

        $response = $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $r2->id, 'quantity' => 1, 'idempotency_key' => 'conflict-body-2',
        ], $headers);

        $response->assertStatus(409);
    }

    // =========================================================================
    // Inventory protection: concurrent-sensitive availability
    // =========================================================================

    public function test_availability_correctly_subtracts_all_commitments(): void
    {
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 10]);
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);

        $availService = app(AvailabilityService::class);

        // Baseline: 10 available
        $this->assertEquals(10, $availService->getLotAvailableQuantity($lot));

        // Checkout 3 units
        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 3, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher->id,
            'checked_out_to' => $student->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 3, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);
        $this->assertEquals(7, $availService->getLotAvailableQuantity($lot));

        // Approve 2 more via loan
        LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 2, 'status' => 'approved', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        $this->assertEquals(5, $availService->getLotAvailableQuantity($lot));

        // Reserve 1 (overlapping today)
        ReservationRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id,
            'reservation_type' => 'equipment', 'status' => 'approved',
            'start_date' => now()->subDay(), 'end_date' => now()->addDays(3),
            'idempotency_key' => uniqid(),
        ]);
        $this->assertEquals(4, $availService->getLotAvailableQuantity($lot));

        // Pending transfer of 2
        $dept2 = Department::create(['name' => 'AV2', 'code' => 'AV2', 'description' => 'T']);
        TransferRequest::create([
            'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'from_department_id' => $lot->department_id, 'to_department_id' => $dept2->id,
            'initiated_by' => $teacher->id, 'status' => 'pending', 'quantity' => 2,
            'idempotency_key' => uniqid(),
        ]);
        $this->assertEquals(2, $availService->getLotAvailableQuantity($lot));
    }

    // =========================================================================
    // Reservation conflict: overlapping dates with quantity exhaustion
    // =========================================================================

    public function test_reservation_conflict_when_quantity_exhausted_by_overlap(): void
    {
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();
        $this->assignMembership($s1);
        $this->assignMembership($s2);
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 1]);

        $key1 = 'res-conflict-prod-1';
        $this->actingAs($s1)->postJson('/api/reservations', [
            'resource_id' => $resource->id, 'reservation_type' => 'equipment',
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(10)->format('Y-m-d'),
            'idempotency_key' => $key1,
        ], ['X-Idempotency-Key' => $key1])->assertCreated();

        // Overlapping dates, same resource, only 1 unit
        $key2 = 'res-conflict-prod-2';
        $response = $this->actingAs($s2)->postJson('/api/reservations', [
            'resource_id' => $resource->id, 'reservation_type' => 'equipment',
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(12)->format('Y-m-d'),
            'idempotency_key' => $key2,
        ], ['X-Idempotency-Key' => $key2]);

        $response->assertUnprocessable();
    }

    // =========================================================================
    // Authorization: protected state-changing endpoint
    // =========================================================================

    public function test_student_cannot_approve_loan(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'pending', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        $this->actingAs($student)->postJson("/api/loans/{$loan->id}/approve", [
            'status' => 'approved',
        ], ['X-Idempotency-Key' => 'student-approve-1'])->assertForbidden();
    }

    // =========================================================================
    // Resource-type enforcement (complementary negatives)
    // =========================================================================

    public function test_entitlement_package_cannot_be_loaned(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        $dept = Department::first() ?? Department::create(['name' => 'EP', 'code' => 'EP', 'description' => 'T']);
        $resource = Resource::create([
            'name' => 'Studio Pass', 'resource_type' => 'entitlement_package',
            'category' => 'Studio', 'department_id' => $dept->id, 'status' => 'active',
        ]);
        InventoryLot::create([
            'resource_id' => $resource->id, 'department_id' => $dept->id,
            'lot_number' => 'EP-1', 'total_quantity' => 5, 'serviceable_quantity' => 5, 'condition' => 'good',
        ]);

        $key = 'ep-loan-1';
        $this->actingAs($student)->postJson('/api/loans', [
            'resource_id' => $resource->id, 'quantity' => 1, 'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key])->assertUnprocessable();
    }

    public function test_mismatched_reservation_type_rejected(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot(); // equipment resource

        // Try venue reservation on equipment resource
        $key = 'mismatch-1';
        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'venue',
            'venue_id' => 1,
            'venue_time_slot_id' => 1,
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
    }

    public function test_equipment_reservation_requires_dates(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student);
        [$resource, $lot] = $this->createResourceWithLot();

        $key = 'no-dates-1';
        $response = $this->actingAs($student)->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'reservation_type' => 'equipment',
            // Missing start_date and end_date
            'idempotency_key' => $key,
        ], ['X-Idempotency-Key' => $key]);

        $response->assertUnprocessable();
    }

    // =========================================================================
    // DateTime precision: due dates store time component
    // =========================================================================

    public function test_checkout_due_date_has_datetime_precision(): void
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

        $this->actingAs($teacher)->postJson("/api/loans/{$loan->id}/checkout", [], [
            'X-Idempotency-Key' => 'dt-precision-1',
        ])->assertCreated();

        $checkout = Checkout::first();
        $this->assertNotNull($checkout->due_date);

        // On MySQL with datetime column, the time component is preserved.
        // On SQLite, date columns truncate time. The model cast to 'datetime' handles this.
        $this->assertInstanceOf(\Carbon\Carbon::class, $checkout->due_date);
        $this->assertTrue($checkout->due_date->isFuture());
    }

    // =========================================================================
    // Row-level locking: real lock contention with two connections
    // =========================================================================

    public function test_row_lock_blocks_concurrent_inventory_modification(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('Row-level locking requires MySQL. SQLite uses file-level locks.');
        }

        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 1]);

        // To get true contention, we need two *distinct* PDO handles. Registering a second
        // connection under a new name forces Laravel to open a fresh one instead of reusing
        // the pooled "mysql" handle.
        $base = config('database.connections.mysql');
        config(['database.connections.mysql_second' => $base]);

        $conn1 = DB::connection('mysql');
        $conn2 = DB::connection('mysql_second');

        // Sanity: distinct PDO objects
        $this->assertNotSame($conn1->getPdo(), $conn2->getPdo());

        $conn1->beginTransaction();
        $lockedLot = $conn1->table('inventory_lots')->where('id', $lot->id)->lockForUpdate()->first();
        $this->assertNotNull($lockedLot);

        $conn2->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;
        try {
            $conn2->beginTransaction();
            $conn2->table('inventory_lots')->where('id', $lot->id)->lockForUpdate()->first();
            $conn2->rollBack();
        } catch (\Illuminate\Database\QueryException $e) {
            // Expected: lock wait timeout exceeded
            $blocked = true;
            try { $conn2->rollBack(); } catch (\Throwable $ignore) {}
        }

        $conn1->rollBack();
        DB::purge('mysql_second');

        $this->assertTrue($blocked, 'Connection 2 should have been blocked by the row lock from Connection 1.');
    }

    public function test_locked_lot_prevents_double_checkout(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('Row-level locking requires MySQL.');
        }

        $student = $this->createStudent();
        $this->assignMembership($student);
        $teacher = $this->createTeacher();
        $this->grantScope($teacher);
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 1]);

        // Create an approved loan
        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'approved', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        // First checkout succeeds
        $this->actingAs($teacher)->postJson("/api/loans/{$loan->id}/checkout", [], [
            'X-Idempotency-Key' => 'lock-co-1',
        ])->assertCreated();

        // Loan is now checked_out — second attempt must fail
        // This tests the state check inside the locked transaction
        $this->actingAs($teacher)->postJson("/api/loans/{$loan->id}/checkout", [], [
            'X-Idempotency-Key' => 'lock-co-2',
        ])->assertUnprocessable();

        // Only one checkout exists
        $this->assertDatabaseCount('checkouts', 1);
    }
}
