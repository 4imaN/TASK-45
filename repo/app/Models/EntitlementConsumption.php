<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntitlementConsumption extends Model
{
    protected $fillable = [
        'grant_id',
        'quantity',
        'consumed_at',
        'resource_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function grant(): BelongsTo
    {
        return $this->belongsTo(EntitlementGrant::class, 'grant_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}
