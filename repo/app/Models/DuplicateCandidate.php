<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateCandidate extends Model
{
    protected $fillable = [
        'resource_a_id',
        'resource_b_id',
        'batch_id',
        'match_type',
        'match_score',
        'status',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'match_score' => 'float',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function resourceA(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_a_id');
    }

    public function resourceB(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_b_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
