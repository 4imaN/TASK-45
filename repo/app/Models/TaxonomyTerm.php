<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxonomyTerm extends Model
{
    protected $fillable = [
        'type',
        'value',
        'parent_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class, 'parent_id');
    }
}
