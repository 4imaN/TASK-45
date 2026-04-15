<?php
namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerWiringTest extends TestCase
{
    /**
     * Verify that expected commands are actually registered in routes/console.php.
     * Without this, a bad deploy can ship with reminders/expirations silently never running.
     */
    public function test_reminders_process_is_scheduled_hourly(): void
    {
        $event = $this->findScheduledEvent('reminders:process');
        $this->assertNotNull($event, 'reminders:process is not registered on the scheduler');
        $this->assertSame('0 * * * *', $event->expression, 'reminders:process should run hourly');
    }

    public function test_holds_expire_is_scheduled_every_fifteen_minutes(): void
    {
        $event = $this->findScheduledEvent('holds:expire');
        $this->assertNotNull($event, 'holds:expire is not registered on the scheduler');
        $this->assertSame('*/15 * * * *', $event->expression, 'holds:expire should run every 15 minutes');
    }

    public function test_both_command_handles_are_registered_with_artisan(): void
    {
        $all = array_keys(Artisan::all());
        $this->assertContains('reminders:process', $all);
        $this->assertContains('holds:expire', $all);
    }

    private function findScheduledEvent(string $commandName): ?\Illuminate\Console\Scheduling\Event
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        foreach ($schedule->events() as $event) {
            if (str_contains($event->command ?? '', $commandName)) {
                return $event;
            }
        }
        return null;
    }
}
