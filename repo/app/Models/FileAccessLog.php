<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileAccessLog extends Model
{
    /**
     * Access logs are append-only; never updated after insert.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'file_asset_id',
        'accessed_by',
        'access_type',
        'ip_address',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function fileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class);
    }

    public function accessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accessed_by');
    }
}
