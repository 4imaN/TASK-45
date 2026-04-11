<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'imported_by',
        'filename',
        'total_rows',
        'processed_rows',
        'valid_rows',
        'invalid_rows',
        'duplicate_rows',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function validationResults(): HasMany
    {
        return $this->hasMany(ImportValidationResult::class, 'batch_id');
    }

    public function duplicateCandidates(): HasMany
    {
        return $this->hasMany(DuplicateCandidate::class, 'batch_id');
    }
}
