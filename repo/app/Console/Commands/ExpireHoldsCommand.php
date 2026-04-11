<?php
namespace App\Console\Commands;

use App\Jobs\ExpireHolds;
use Illuminate\Console\Command;

class ExpireHoldsCommand extends Command
{
    protected $signature = 'holds:expire';
    protected $description = 'Expire timed-out holds';

    public function handle(): int
    {
        ExpireHolds::dispatch();
        $this->info('Hold expiration dispatched.');
        return 0;
    }
}
