<?php
namespace App\Console\Commands;

use App\Jobs\ProcessReminders;
use Illuminate\Console\Command;

class ProcessRemindersCommand extends Command
{
    protected $signature = 'reminders:process';
    protected $description = 'Process due date reminders for active checkouts';

    public function handle(): int
    {
        ProcessReminders::dispatch();
        $this->info('Reminder processing dispatched.');
        return 0;
    }
}
