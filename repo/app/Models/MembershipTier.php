<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipTier extends Model
{
    protected $fillable = [
        'name',
        'description',
        'max_active_loans',
        'max_loan_days',
        'max_renewals',
        'points_multiplier',
    ];

    protected function casts(): array
    {
        return [
            'points_multiplier' => 'float',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'tier_id');
    }

    public function entitlementPackages(): HasMany
    {
        return $this->hasMany(EntitlementPackage::class, 'tier_id');
    }
}
