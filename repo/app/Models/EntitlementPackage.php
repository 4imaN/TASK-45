<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntitlementPackage extends Model
{
    protected $fillable = [
        'name',
        'description',
        'tier_id',
        'resource_type',
        'quantity',
        'unit',
        'validity_days',
        'price_in_cents',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function membershipTier(): BelongsTo
    {
        return $this->belongsTo(MembershipTier::class, 'tier_id');
    }

    public function entitlementGrants(): HasMany
    {
        return $this->hasMany(EntitlementGrant::class, 'package_id');
    }
}
