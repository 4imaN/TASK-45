<?php
namespace App\Domain\Files;

use App\Models\{FileAsset, FileAccessLog, User, AuditLog};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    const ALLOWED_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];
    const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];
    const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10MB

    public function upload(UploadedFile $file, User $user, ?string $attachableType = null, ?int $attachableId = null): FileAsset
    {
        // Validate extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new \App\Common\Exceptions\BusinessRuleException("File type '{$extension}' is not allowed. Allowed: " . implode(', ', self::ALLOWED_EXTENSIONS));
        }

        // Validate MIME
        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIMES)) {
            throw new \App\Common\Exceptions\BusinessRuleException("MIME type '{$mime}' is not allowed.");
        }

        // Validate size
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new \App\Common\Exceptions\BusinessRuleException('File exceeds maximum size of 10MB.');
        }

        // Generate checksum
        $checksum = hash_file('sha256', $file->getRealPath());

        // Store locally
        $filename = Str::uuid() . '.' . $extension;
        $path = $file->storeAs('private/uploads', $filename, 'local');

        $asset = FileAsset::create([
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => $file->getSize(),
            'checksum' => $checksum,
            'storage_path' => $path,
            'uploaded_by' => $user->id,
            'attachable_type' => $attachableType,
            'attachable_id' => $attachableId,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'file_uploaded',
            'auditable_type' => FileAsset::class,
            'auditable_id' => $asset->id,
        ]);

        return $asset;
    }

    public function download(FileAsset $asset, User $user): string
    {
        $path = Storage::disk('local')->path($asset->storage_path);

        // Verify file integrity before serving
        if (!file_exists($path)) {
            throw new \App\Common\Exceptions\BusinessRuleException('File not found on disk.');
        }

        if (!$this->verifyChecksum($asset)) {
            throw new \App\Common\Exceptions\BusinessRuleException('File integrity check failed. The file may be corrupted.');
        }

        // Log access
        FileAccessLog::create([
            'file_asset_id' => $asset->id,
            'accessed_by' => $user->id,
            'access_type' => 'download',
            'ip_address' => request()?->ip(),
        ]);

        return $path;
    }

    public function verifyChecksum(FileAsset $asset): bool
    {
        $path = Storage::disk('local')->path($asset->storage_path);
        return hash_file('sha256', $path) === $asset->checksum;
    }
}
