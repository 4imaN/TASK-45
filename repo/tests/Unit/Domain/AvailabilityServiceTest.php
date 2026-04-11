<?php
namespace Tests\Unit\Domain;

use Tests\TestCase;
use App\Domain\Availability\AvailabilityService;
use App\Models\{Resource, InventoryLot, Checkout, LoanRequest, ReservationRequest, CustodyRecord, User, Waitlist, Renewal};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected AvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AvailabilityService::class);
    }

    public function test_available_quantity_with_no_checkouts(): void
    {
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 10]);
        $this->assertEquals(10, $this->service->getAvailableQuantity($resource->fresh()->load('inventoryLots')));
    }

    public function test_available_quantity_reduced_by_checkouts(): void
    {
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 10]);
        $student = $this->createStudent();
        $staff = $this->createTeacher();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $staff->id, 'checked_out_to' => $student->id,
            'inventory_lot_id' => $lot->id, 'quantity' => 2, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        $this->assertEquals(8, $this->service->getAvailableQuantity($resource->fresh()->load('inventoryLots')));
    }

    public function test_available_quantity_reduced_by_approved_requests(): void
    {
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 5]);
        $student = $this->createStudent();

        LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 3, 'status' => 'approved', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        $this->assertEquals(2, $this->service->getAvailableQuantity($resource->fresh()->load('inventoryLots')));
    }

    public function test_available_quantity_reduced_by_transit(): void
    {
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 5]);
        $admin = $this->createAdmin();
        $dept2 = \App\Models\Department::create(['name' => 'Dept2', 'code' => 'D2', 'description' => 'Test']);

        $transfer = \App\Models\TransferRequest::create([
            'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'from_department_id' => $resource->department_id, 'to_department_id' => $dept2->id,
            'initiated_by' => $admin->id, 'status' => 'in_transit', 'idempotency_key' => uniqid(),
        ]);

        CustodyRecord::create([
            'transfer_request_id' => $transfer->id, 'inventory_lot_id' => $lot->id, 'department_id' => $resource->department_id,
            'custody_type' => 'in_transit', 'custodian_id' => $admin->id, 'started_at' => now(),
        ]);

        $this->assertEquals(4, $this->service->getAvailableQuantity($resource->fresh()->load('inventoryLots')));
    }

    public function test_user_active_item_count(): void
    {
        $student = $this->createStudent();
        [$resource, $lot] = $this->createResourceWithLot();
        $staff = $this->createTeacher();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $staff->id, 'checked_out_to' => $student->id,
            'inventory_lot_id' => $lot->id, 'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        $this->assertEquals(1, $this->service->getUserActiveItemCount($student));
    }

    public function test_can_user_borrow_within_limit(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student, 'Basic');
        $result = $this->service->canUserBorrow($student);
        $this->assertTrue($result['allowed']);
    }

    public function test_can_user_borrow_over_limit(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student, 'Basic');
        $staff = $this->createTeacher();

        // Create 2 active checkouts (Basic limit)
        for ($i = 0; $i < 2; $i++) {
            [$resource, $lot] = $this->createResourceWithLot();
            $loan = LoanRequest::create([
                'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
                'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
            ]);
            Checkout::create([
                'loan_request_id' => $loan->id, 'checked_out_by' => $staff->id, 'checked_out_to' => $student->id,
                'inventory_lot_id' => $lot->id, 'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
            ]);
        }

        $result = $this->service->canUserBorrow($student);
        $this->assertFalse($result['allowed']);
    }

    public function test_can_renew_with_no_waitlist(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student, 'Basic');
        $staff = $this->createTeacher();
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        $checkout = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $staff->id, 'checked_out_to' => $student->id,
            'inventory_lot_id' => $lot->id, 'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        $result = $this->service->canRenew($checkout->load('checkedOutTo.membership.tier', 'loanRequest', 'renewals'));
        $this->assertTrue($result['allowed']);
    }

    public function test_cannot_renew_with_waitlist(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student, 'Basic');
        $staff = $this->createTeacher();
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        $checkout = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $staff->id, 'checked_out_to' => $student->id,
            'inventory_lot_id' => $lot->id, 'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        Waitlist::create([
            'resource_id' => $resource->id, 'user_id' => $this->createStudent()->id, 'position' => 1, 'requested_at' => now(),
        ]);

        $result = $this->service->canRenew($checkout->load('checkedOutTo.membership.tier', 'loanRequest', 'renewals'));
        $this->assertFalse($result['allowed']);
    }

    public function test_cannot_renew_past_max_renewals(): void
    {
        $student = $this->createStudent();
        $this->assignMembership($student, 'Basic');
        $staff = $this->createTeacher();
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);
        $checkout = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $staff->id, 'checked_out_to' => $student->id,
            'inventory_lot_id' => $lot->id, 'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        Renewal::create([
            'checkout_id' => $checkout->id, 'renewed_by' => $student->id,
            'original_due_date' => now()->addDays(7), 'new_due_date' => now()->addDays(14), 'renewal_number' => 1,
        ]);

        $result = $this->service->canRenew($checkout->load('checkedOutTo.membership.tier', 'loanRequest', 'renewals'));
        $this->assertFalse($result['allowed']);
    }

    public function test_pending_equipment_reservation_reduces_availability(): void
    {
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 10]);
        $student = $this->createStudent();

        // Reservation overlapping today (start <= now <= end)
        ReservationRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id,
            'reservation_type' => 'equipment', 'status' => 'pending',
            'start_date' => now()->subDay(), 'end_date' => now()->addDays(3),
            'idempotency_key' => 'res-avail-unit-1',
        ]);

        $this->assertEquals(9, $this->service->getAvailableQuantity($resource->fresh()->load('inventoryLots')));
    }

    public function test_approved_equipment_reservation_reduces_availability(): void
    {
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 10]);
        $student = $this->createStudent();

        // Reservation overlapping today
        ReservationRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id,
            'reservation_type' => 'equipment', 'status' => 'approved',
            'start_date' => now()->subDay(), 'end_date' => now()->addDays(3),
            'idempotency_key' => 'res-avail-unit-2',
        ]);

        $this->assertEquals(9, $this->service->getAvailableQuantity($resource->fresh()->load('inventoryLots')));
    }

    public function test_cancelled_reservation_does_not_reduce_availability(): void
    {
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 10]);
        $student = $this->createStudent();

        ReservationRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id,
            'reservation_type' => 'equipment', 'status' => 'cancelled',
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(3),
            'idempotency_key' => 'res-avail-unit-3',
        ]);

        $this->assertEquals(10, $this->service->getAvailableQuantity($resource->fresh()->load('inventoryLots')));
    }

    public function test_venue_reservation_does_not_affect_equipment_availability(): void
    {
        [$resource, $lot] = $this->createResourceWithLot([], ['serviceable_quantity' => 10]);
        $student = $this->createStudent();

        ReservationRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id,
            'reservation_type' => 'venue', 'status' => 'approved',
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(3),
            'idempotency_key' => 'res-avail-unit-4',
        ]);

        // Venue reservations don't consume inventory lot quantity
        $this->assertEquals(10, $this->service->getAvailableQuantity($resource->fresh()->load('inventoryLots')));
    }
}
