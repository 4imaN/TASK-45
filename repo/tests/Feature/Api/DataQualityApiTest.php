<?php
namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\TaxonomyTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class DataQualityApiTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_import_with_validation(): void
    {
        $admin = $this->createAdmin();
        TaxonomyTerm::create(['type' => 'category', 'value' => 'Electronics']);

        $response = $this->actingAs($admin)->postJson('/api/data-quality/import', [
            'rows' => [
                ['name' => 'Valid Item', 'category' => 'Electronics'],
                ['name' => '', 'category' => 'Electronics'],
            ],
        ], ['X-Idempotency-Key' => 'test-import-validation-1']);
        $response->assertOk()->assertJsonPath('summary.total_rows', 2);
    }

    public function test_remediation_queue(): void
    {
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin)->getJson('/api/data-quality/remediation');
        $response->assertOk();
    }

    public function test_download_report(): void
    {
        $admin = $this->createAdmin();
        $batch = \App\Models\ImportBatch::create([
            'imported_by' => $admin->id, 'filename' => 'test.csv',
            'total_rows' => 1, 'processed_rows' => 1, 'valid_rows' => 1, 'status' => 'completed',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/data-quality/batches/{$batch->id}/download");
        $response->assertOk()->assertHeader('Content-Disposition');
    }
}
