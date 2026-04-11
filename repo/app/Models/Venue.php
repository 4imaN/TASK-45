<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $fillable = [
        'resource_id',
        'capacity',
        'location',
        'building',
        'floor',
        'amenities',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(VenueTimeSlot::class);
    }

    public function reservationRequests(): HasMany
    {
        return $this->hasMany(ReservationRequest::class);
    }
}
