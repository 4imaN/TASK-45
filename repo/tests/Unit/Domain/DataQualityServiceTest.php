<?php
namespace Tests\Unit\Domain;

use Tests\TestCase;
use App\Domain\DataQuality\DataQualityService;
use App\Models\{TaxonomyTerm, ProhibitedTerm, Resource};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class DataQualityServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected DataQualityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DataQualityService::class);
    }

    public function test_validate_row_catches_prohibited_terms(): void
    {
        ProhibitedTerm::create(['term' => 'classified', 'severity' => 'block']);
        $errors = $this->service->validateRow(['name' => 'Classified Equipment X']);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Prohibited term', $errors[0]);
    }

    public function test_validate_row_catches_invalid_tags(): void
    {
        TaxonomyTerm::create(['type' => 'tag', 'value' => 'portable']);
        $errors = $this->service->validateRow(['name' => 'Test', 'tags' => 'portable,nonexistent']);
        $this->assertNotEmpty(array_filter($errors, fn($e) => str_contains($e, 'Invalid tag')));
    }

    public function test_validate_row_catches_invalid_category(): void
    {
        TaxonomyTerm::create(['type' => 'category', 'value' => 'Electronics']);
        $errors = $this->service->validateRow(['name' => 'Test', 'category' => 'InvalidCat']);
        $this->assertNotEmpty(array_filter($errors, fn($e) => str_contains($e, 'Invalid category')));
    }

    public function test_duplicate_detection(): void
    {
        [$resource, $lot] = $this->createResourceWithLot(['name' => 'Test Widget']);
        $result = $this->service->checkDuplicate(['name' => 'testwidget']); // normalized match
        $this->assertIsInt($result);
        $this->assertEquals($resource->id, $result);
    }

    public function test_normalize_vendor(): void
    {
        \App\Models\VendorAlias::create(['alias' => 'Dell Inc', 'canonical_name' => 'Dell Technologies', 'status' => 'approved']);
        $this->assertEquals('Dell Technologies', $this->service->normalizeVendor('Dell Inc'));
    }

    public function test_import_batch_processes_rows(): void
    {
        TaxonomyTerm::create(['type' => 'category', 'value' => 'Electronics']);
        $user = $this->createAdmin();
        $batch = $this->service->createImportBatch($user, 'test.csv', [
            ['name' => 'Valid Item', 'category' => 'Electronics'],
            ['name' => '', 'category' => 'Electronics'], // Invalid: missing name
        ]);

        $this->assertEquals(2, $batch->total_rows);
        $this->assertEquals(1, $batch->valid_rows);
        $this->assertEquals(1, $batch->invalid_rows);
        $this->assertEquals('completed', $batch->status);
    }

    public function test_generate_validation_report(): void
    {
        $user = $this->createAdmin();
        $batch = $this->service->createImportBatch($user, 'test.csv', [
            ['name' => 'Item 1'], ['name' => ''],
        ]);
        $report = $this->service->generateValidationReport($batch);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('issues', $report);
        $this->assertCount(1, $report['issues']);
    }
}
