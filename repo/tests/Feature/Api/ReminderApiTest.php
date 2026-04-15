<?php
namespace Tests\Feature\Api;

use App\Jobs\ProcessReminders;
use App\Models\Checkout;
use App\Models\InventoryLot;
use App\Models\LoanRequest;
use App\Models\ReminderEvent;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Tests\Support\TestHelpers;
use Tests\TestCase;

class ReminderApiTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_list_reminders_returns_only_mine_unacknowledged(): void
    {
        $me = $this->createStudent();
        $other = $this->createStudent();

        $mine = ReminderEvent::create([
            'remindable_type' => Checkout::class, 'remindable_id' => 0,
            'user_id' => $me->id, 'reminder_type' => 'upcoming_due', 'scheduled_at' => now(),
        ]);
        $acked = ReminderEvent::create([
            'remindable_type' => Checkout::class, 'remindable_id' => 0,
            'user_id' => $me->id, 'reminder_type' => 'overdue', 'scheduled_at' => now()->subDay(),
            'acknowledged_at' => now(),
        ]);
        $theirs = ReminderEvent::create([
            'remindable_type' => Checkout::class, 'remindable_id' => 0,
            'user_id' => $other->id, 'reminder_type' => 'upcoming_due', 'scheduled_at' => now(),
        ]);

        $response = $this->actingAs($me)->getJson('/api/reminders');
        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($acked->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_acknowledge_marks_reminder_read(): void
    {
        $me = $this->createStudent();
        $reminder = ReminderEvent::create([
            'remindable_type' => Checkout::class, 'remindable_id' => 0,
            'user_id' => $me->id, 'reminder_type' => 'upcoming_due', 'scheduled_at' => now(),
        ]);

        $response = $this->actingAs($me)->postJson(
            "/api/reminders/{$reminder->id}/acknowledge",
            [],
            ['X-Idempotency-Key' => 'ack-' . uniqid()],
        );
        $response->assertOk();
        $this->assertNotNull($reminder->fresh()->acknowledged_at);
    }

    public function test_cannot_acknowledge_other_users_reminder(): void
    {
        $me = $this->createStudent();
        $other = $this->createStudent();
        $reminder = ReminderEvent::create([
            'remindable_type' => Checkout::class, 'remindable_id' => 0,
            'user_id' => $other->id, 'reminder_type' => 'upcoming_due', 'scheduled_at' => now(),
        ]);

        $response = $this->actingAs($me)->postJson(
            "/api/reminders/{$reminder->id}/acknowledge",
            [],
            ['X-Idempotency-Key' => 'ack-' . uniqid()],
        );
        $response->assertForbidden();
        $this->assertNull($reminder->fresh()->acknowledged_at);
    }

    public function test_reminders_command_dispatches_job(): void
    {
        Bus::fake();
        Artisan::call('reminders:process');
        Bus::assertDispatched(ProcessReminders::class);
    }

    public function test_reminders_job_creates_upcoming_and_overdue_events(): void
    {
        $user = $this->createStudent();
        $staff = $this->createAdmin();
        [$resource, $lot] = $this->createResourceWithLot();
        $loan = LoanRequest::create([
            'user_id' => $user->id, 'resource_id' => $resource->id, 'inventory_lot_id' => $lot->id,
            'quantity' => 1, 'status' => 'approved',
            'requested_at' => now()->subHour(), 'due_date' => now()->addDay(),
            'idempotency_key' => 'test-loan-' . uniqid(),
        ]);
        $upcoming = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $staff->id,
            'checked_out_to' => $user->id, 'inventory_lot_id' => $lot->id, 'quantity' => 1,
            'checked_out_at' => now()->subHour(), 'due_date' => now()->addHours(12),
        ]);
        $overdue = Checkout::create([
            'loan_request_id' => $loan->id, 'checked_out_by' => $staff->id,
            'checked_out_to' => $user->id, 'inventory_lot_id' => $lot->id, 'quantity' => 1,
            'checked_out_at' => now()->subDays(3), 'due_date' => now()->subDay(),
        ]);

        (new ProcessReminders())->handle();

        $this->assertDatabaseHas('reminder_events', [
            'remindable_type' => Checkout::class,
            'remindable_id' => $upcoming->id,
            'reminder_type' => 'upcoming_due',
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('reminder_events', [
            'remindable_type' => Checkout::class,
            'remindable_id' => $overdue->id,
            'reminder_type' => 'overdue',
            'user_id' => $user->id,
        ]);
    }
}
