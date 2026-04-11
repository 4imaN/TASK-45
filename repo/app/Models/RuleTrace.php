<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleTrace extends Model
{
    protected $fillable = [
        'batch_id',
        'resource_id',
        'rank',
        'score',
        'contributing_factors',
        'applied_filters',
        'excluded',
        'exclusion_reason',
    ];

    protected function casts(): array
    {
        return [
            'score'                => 'float',
            'contributing_factors' => 'array',
            'applied_filters'      => 'array',
            'excluded'             => 'boolean',
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
}
