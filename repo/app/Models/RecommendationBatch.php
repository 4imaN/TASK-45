<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecommendationBatch extends Model
{
    protected $fillable = [
        'user_id',
        'context_type',
        'context_id',
        'generated_at',
        'parameters',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'parameters'   => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ruleTraces(): HasMany
    {
        return $this->hasMany(RuleTrace::class, 'batch_id');
    }

    public function manualOverrides(): HasMany
    {
        return $this->hasMany(ManualOverride::class, 'batch_id');
    }
}
