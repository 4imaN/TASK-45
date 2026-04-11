<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'resource_type',
        'category',
        'subcategory',
        'department_id',
        'vendor',
        'manufacturer',
        'model_number',
        'status',
        'is_sensitive',
        'metadata',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'metadata'     => 'array',
            'tags'         => 'array',
            'is_sensitive' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function inventoryLots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    public function venue(): HasOne
    {
        return $this->hasOne(Venue::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function loanRequests(): HasMany
    {
        return $this->hasMany(LoanRequest::class);
    }

    public function reservationRequests(): HasMany
    {
        return $this->hasMany(ReservationRequest::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Resources that are both active and have at least one inventory lot
     * with serviceable quantity > 0. This replaces the prior incorrect
     * filter on a non-existent 'available' status enum value.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereHas('inventoryLots', fn (Builder $q) => $q->where('serviceable_quantity', '>', 0));
    }

    public function scopeEquipment(Builder $query): Builder
    {
        return $query->where('resource_type', 'equipment');
    }

    public function scopeVenue(Builder $query): Builder
    {
        return $query->where('resource_type', 'venue');
    }

    public function scopeEntitlementPackage(Builder $query): Builder
    {
        return $query->where('resource_type', 'entitlement_package');
    }
}
