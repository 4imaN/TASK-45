<?php

namespace Tests\Unit\Domain;

use Tests\TestCase;
use App\Jobs\ProcessReminders;
use App\Models\{Checkout, LoanRequest, ReminderEvent};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\TestHelpers;

class ReminderCadenceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_reminder_created_when_due_within_48_hours(): void
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        // Due in 36 hours (within 48h threshold)
        $checkout = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher->id,
            'checked_out_to' => $student->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addHours(36),
        ]);

        (new ProcessReminders)->handle();

        $this->assertDatabaseHas('reminder_events', [
            'remindable_type' => Checkout::class,
            'remindable_id' => $checkout->id,
            'user_id' => $student->id,
            'reminder_type' => 'upcoming_due',
        ]);
    }

    public function test_no_reminder_when_due_beyond_48_hours(): void
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        // Due in 72 hours (beyond 48h threshold)
        Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher->id,
            'checked_out_to' => $student->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addHours(72),
        ]);

        (new ProcessReminders)->handle();

        $this->assertDatabaseCount('reminder_events', 0);
    }

    public function test_overdue_generates_reminder(): void
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        // Due yesterday (overdue)
        $checkout = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher->id,
            'checked_out_to' => $student->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'checked_out_at' => now()->subDays(8), 'due_date' => now()->subDay(),
        ]);

        (new ProcessReminders)->handle();

        $this->assertDatabaseHas('reminder_events', [
            'remindable_id' => $checkout->id,
            'reminder_type' => 'overdue',
        ]);
    }

    public function test_reminder_not_duplicated_within_24_hours(): void
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        $checkout = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher->id,
            'checked_out_to' => $student->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'checked_out_at' => now(), 'due_date' => now()->addHours(24),
        ]);

        // First run creates a reminder
        (new ProcessReminders)->handle();
        $this->assertEquals(1, ReminderEvent::where('remindable_id', $checkout->id)->count());

        // Second run within 24h should NOT create a duplicate
        (new ProcessReminders)->handle();
        $this->assertEquals(1, ReminderEvent::where('remindable_id', $checkout->id)->count());
    }

    public function test_reminder_repeats_after_24_hours(): void
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'checked_out', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        // Overdue checkout
        $checkout = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher->id,
            'checked_out_to' => $student->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'checked_out_at' => now()->subDays(10), 'due_date' => now()->subDays(3),
        ]);

        // First run
        (new ProcessReminders)->handle();
        $this->assertEquals(1, ReminderEvent::where('remindable_id', $checkout->id)->count());

        // Simulate 25 hours passing by backdating the existing reminder
        ReminderEvent::where('remindable_id', $checkout->id)->update([
            'scheduled_at' => now()->subHours(25),
        ]);

        // Second run after 24h should create a new reminder
        (new ProcessReminders)->handle();
        $this->assertEquals(2, ReminderEvent::where('remindable_id', $checkout->id)->count());
    }

    public function test_returned_items_do_not_generate_reminders(): void
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        [$resource, $lot] = $this->createResourceWithLot();

        $loan = LoanRequest::create([
            'user_id' => $student->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'returned', 'requested_at' => now(), 'idempotency_key' => uniqid(),
        ]);

        // Returned checkout with past due date
        Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $teacher->id,
            'checked_out_to' => $student->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'checked_out_at' => now()->subDays(8),
            'due_date' => now()->subDay(), 'returned_at' => now(),
        ]);

        (new ProcessReminders)->handle();

        $this->assertDatabaseCount('reminder_events', 0);
    }
}
