<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FileAsset extends Model
{
    protected $fillable = [
        'filename',
        'original_filename',
        'mime_type',
        'size_bytes',
        'checksum',
        'storage_path',
        'uploaded_by',
        'attachable_type',
        'attachable_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes'        => 'integer',
            'storage_path'      => 'encrypted',
            'original_filename' => 'encrypted',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Polymorphic parent: could be a Resource, User, InventoryLot, etc.
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(FileAccessLog::class);
    }
}
