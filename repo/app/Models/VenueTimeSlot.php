<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VenueTimeSlot extends Model
{
    protected $fillable = [
        'venue_id',
        'date',
        'start_time',
        'end_time',
        'is_available',
        'reserved_by_reservation_id',
    ];

    protected function casts(): array
    {
        return [
            'date'         => 'date',
            'is_available' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function reservationRequests(): HasMany
    {
        return $this->hasMany(ReservationRequest::class, 'venue_time_slot_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ReservationRequest::class, 'reserved_by_reservation_id');
    }
}
