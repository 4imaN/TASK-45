<?php
namespace App\Jobs;

use App\Models\Checkout;
use App\Models\ReminderEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // exponential backoff

    public function handle(): void
    {
        // Find checkouts due within 48 hours that haven't been returned
        $upcoming = Checkout::whereNull('returned_at')
            ->where('due_date', '<=', now()->addHours(48))
            ->where('due_date', '>', now())
            ->get();

        foreach ($upcoming as $checkout) {
            $lastReminder = ReminderEvent::where('remindable_type', Checkout::class)
                ->where('remindable_id', $checkout->id)
                ->where('reminder_type', 'upcoming_due')
                ->orderByDesc('scheduled_at')
                ->first();

            if (!$lastReminder || $lastReminder->scheduled_at->diffInHours(now()) >= 24) {
                ReminderEvent::create([
                    'remindable_type' => Checkout::class,
                    'remindable_id' => $checkout->id,
                    'user_id' => $checkout->checked_out_to,
                    'reminder_type' => 'upcoming_due',
                    'scheduled_at' => now(),
                ]);
            }
        }

        // Find overdue checkouts
        $overdue = Checkout::whereNull('returned_at')
            ->where('due_date', '<', now())
            ->get();

        foreach ($overdue as $checkout) {
            $checkout->loanRequest?->update(['status' => 'overdue']);

            $lastReminder = ReminderEvent::where('remindable_type', Checkout::class)
                ->where('remindable_id', $checkout->id)
                ->where('reminder_type', 'overdue')
                ->orderByDesc('scheduled_at')
                ->first();

            if (!$lastReminder || $lastReminder->scheduled_at->diffInHours(now()) >= 24) {
                ReminderEvent::create([
                    'remindable_type' => Checkout::class,
                    'remindable_id' => $checkout->id,
                    'user_id' => $checkout->checked_out_to,
                    'reminder_type' => 'overdue',
                    'scheduled_at' => now(),
                ]);
            }
        }
    }
}
