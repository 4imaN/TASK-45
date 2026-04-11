<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\FileAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestHelpers;

class FileApiTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_upload_valid_pdf(): void
    {
        $user = $this->createStudent();
        $response = $this->actingAs($user)->postJson('/api/files/upload', [
            'file' => UploadedFile::fake()->create('doc.pdf', 500, 'application/pdf'),
        ], ['X-Idempotency-Key' => 'upload-1']);
        $response->assertOk()->assertJsonStructure(['file']);
    }

    public function test_reject_disallowed_extension(): void
    {
        $user = $this->createStudent();
        $response = $this->actingAs($user)->postJson('/api/files/upload', [
            'file' => UploadedFile::fake()->create('script.sh', 100, 'text/x-shellscript'),
        ], ['X-Idempotency-Key' => 'upload-bad-1']);
        $response->assertUnprocessable();
    }

    public function test_reject_oversized_file(): void
    {
        $user = $this->createStudent();
        $response = $this->actingAs($user)->postJson('/api/files/upload', [
            'file' => UploadedFile::fake()->create('huge.pdf', 11264, 'application/pdf'),
        ], ['X-Idempotency-Key' => 'upload-big-1']);
        $response->assertUnprocessable();
    }

    public function test_list_own_files_only_for_students(): void
    {
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();

        $this->actingAs($s1)->postJson('/api/files/upload', [
            'file' => UploadedFile::fake()->create('s1.pdf', 100, 'application/pdf'),
        ], ['X-Idempotency-Key' => 'list-1']);

        $this->actingAs($s2)->postJson('/api/files/upload', [
            'file' => UploadedFile::fake()->create('s2.pdf', 100, 'application/pdf'),
        ], ['X-Idempotency-Key' => 'list-2']);

        $response = $this->actingAs($s1)->getJson('/api/files');
        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $file) {
            $this->assertEquals($s1->id, $file['uploaded_by']);
        }
    }

    public function test_owner_can_download_own_file(): void
    {
        $user = $this->createStudent();
        $this->actingAs($user)->postJson('/api/files/upload', [
            'file' => UploadedFile::fake()->create('mine.pdf', 100, 'application/pdf'),
        ], ['X-Idempotency-Key' => 'dl-1']);

        $file = FileAsset::first();
        $response = $this->actingAs($user)->getJson("/api/files/{$file->id}/download");
        $response->assertOk();
    }

    public function test_other_student_cannot_download_file(): void
    {
        $s1 = $this->createStudent();
        $s2 = $this->createStudent();

        $this->actingAs($s1)->postJson('/api/files/upload', [
            'file' => UploadedFile::fake()->create('private.pdf', 100, 'application/pdf'),
        ], ['X-Idempotency-Key' => 'dl-cross-1']);

        $file = FileAsset::first();
        $response = $this->actingAs($s2)->getJson("/api/files/{$file->id}/download");
        $response->assertForbidden();
    }

    public function test_upload_response_does_not_expose_storage_path_or_checksum(): void
    {
        $user = $this->createStudent();
        $response = $this->actingAs($user)->postJson('/api/files/upload', [
            'file' => UploadedFile::fake()->create('shape-test.pdf', 200, 'application/pdf'),
        ], ['X-Idempotency-Key' => 'upload-shape-1']);

        $response->assertOk();
        $fileData = $response->json('file');
        $this->assertArrayNotHasKey('storage_path', $fileData);
        $this->assertArrayNotHasKey('checksum', $fileData);
        // Verify expected keys are present
        $this->assertArrayHasKey('id', $fileData);
        $this->assertArrayHasKey('filename', $fileData);
        $this->assertArrayHasKey('mime_type', $fileData);
    }

    public function test_file_list_does_not_expose_storage_path_or_checksum(): void
    {
        $user = $this->createStudent();
        $this->actingAs($user)->postJson('/api/files/upload', [
            'file' => UploadedFile::fake()->create('list-shape.pdf', 100, 'application/pdf'),
        ], ['X-Idempotency-Key' => 'upload-list-shape-1']);

        $response = $this->actingAs($user)->getJson('/api/files');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        foreach ($data as $fileData) {
            $this->assertArrayNotHasKey('storage_path', $fileData);
            $this->assertArrayNotHasKey('checksum', $fileData);
            $this->assertArrayHasKey('id', $fileData);
            $this->assertArrayHasKey('filename', $fileData);
        }
    }
}
