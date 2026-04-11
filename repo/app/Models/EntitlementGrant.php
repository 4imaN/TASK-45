<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntitlementGrant extends Model
{
    protected $fillable = [
        'user_id',
        'package_id',
        'membership_id',
        'remaining_quantity',
        'granted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(EntitlementPackage::class, 'package_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(EntitlementConsumption::class, 'grant_id');
    }
}
