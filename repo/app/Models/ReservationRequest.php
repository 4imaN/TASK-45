<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ReservationRequest extends Model
{
    protected $fillable = [
        'user_id',
        'resource_id',
        'venue_id',
        'venue_time_slot_id',
        'reservation_type',
        'status',
        'start_date',
        'end_date',
        'notes',
        'idempotency_key',
        'class_id',
        'assignment_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(VenueTimeSlot::class, 'venue_time_slot_id');
    }

    public function venueTimeSlot(): BelongsTo
    {
        return $this->belongsTo(VenueTimeSlot::class, 'venue_time_slot_id');
    }

    public function classModel(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function approval(): MorphOne
    {
        return $this->morphOne(Approval::class, 'approvable');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['approved', 'fulfilled'])
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'fulfilled')
            ->where('end_date', '<', now());
    }
}
