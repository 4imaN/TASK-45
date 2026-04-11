<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportValidationResult extends Model
{
    protected $fillable = [
        'batch_id',
        'row_number',
        'original_data',
        'validation_errors',
        'is_duplicate',
        'duplicate_of_id',
        'status',
        'remediated_by',
        'remediated_at',
    ];

    protected function casts(): array
    {
        return [
            'original_data'     => 'array',
            'validation_errors' => 'array',
            'is_duplicate'      => 'boolean',
            'remediated_at'     => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'duplicate_of_id');
    }

    public function remediatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remediated_by');
    }
}
