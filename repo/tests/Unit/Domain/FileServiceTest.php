<?php
namespace Tests\Unit\Domain;

use Tests\TestCase;
use App\Domain\Files\FileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestHelpers;

class FileServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected FileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FileService::class);
        Storage::fake('local');
    }

    public function test_upload_valid_pdf(): void
    {
        $user = $this->createStudent();
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
        $asset = $this->service->upload($file, $user);
        $this->assertEquals('application/pdf', $asset->mime_type);
        $this->assertNotEmpty($asset->checksum);
    }

    public function test_upload_valid_image(): void
    {
        $user = $this->createStudent();
        $file = UploadedFile::fake()->image('photo.jpg', 640, 480);
        $asset = $this->service->upload($file, $user);
        $this->assertStringContainsString('image/', $asset->mime_type);
    }

    public function test_reject_disallowed_type(): void
    {
        $user = $this->createStudent();
        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');
        $this->expectException(\App\Common\Exceptions\BusinessRuleException::class);
        $this->service->upload($file, $user);
    }

    public function test_reject_oversized_file(): void
    {
        $user = $this->createStudent();
        $file = UploadedFile::fake()->create('huge.pdf', 11264, 'application/pdf'); // 11MB
        $this->expectException(\App\Common\Exceptions\BusinessRuleException::class);
        $this->service->upload($file, $user);
    }

    public function test_download_logs_access(): void
    {
        $user = $this->createStudent();
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $asset = $this->service->upload($file, $user);
        $this->service->download($asset, $user);
        $this->assertDatabaseHas('file_access_logs', ['file_asset_id' => $asset->id, 'accessed_by' => $user->id]);
    }

    public function test_download_verifies_checksum(): void
    {
        $user = $this->createStudent();
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $asset = $this->service->upload($file, $user);

        // Corrupt the stored checksum to simulate tampering
        $asset->update(['checksum' => 'bad_checksum_value']);

        $this->expectException(\App\Common\Exceptions\BusinessRuleException::class);
        $this->expectExceptionMessage('integrity check failed');
        $this->service->download($asset->fresh(), $user);
    }

    public function test_download_fails_when_file_missing(): void
    {
        $user = $this->createStudent();
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $asset = $this->service->upload($file, $user);

        // Delete the file from storage
        Storage::disk('local')->delete($asset->storage_path);

        $this->expectException(\App\Common\Exceptions\BusinessRuleException::class);
        $this->expectExceptionMessage('not found on disk');
        $this->service->download($asset, $user);
    }
}
