<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualOverride extends Model
{
    protected $fillable = [
        'batch_id',
        'resource_id',
        'overridden_by',
        'override_type',
        'reason',
        'previous_state',
        'new_state',
    ];

    protected function casts(): array
    {
        return [
            'previous_state' => 'array',
            'new_state'      => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function recommendationBatch(): BelongsTo
    {
        return $this->belongsTo(RecommendationBatch::class, 'batch_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
