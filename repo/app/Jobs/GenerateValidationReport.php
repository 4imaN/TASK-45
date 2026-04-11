<?php
namespace App\Jobs;

use App\Models\ImportBatch;
use App\Domain\DataQuality\DataQualityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateValidationReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(public ImportBatch $batch) {}

    public function handle(DataQualityService $service): void
    {
        $report = $service->generateValidationReport($this->batch);
        $filename = "validation_report_{$this->batch->id}.json";
        Storage::disk('local')->put("private/reports/{$filename}", json_encode($report, JSON_PRETTY_PRINT));
    }
}
