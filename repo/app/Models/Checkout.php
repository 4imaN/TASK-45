<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Checkout extends Model
{
    protected $fillable = [
        'loan_request_id',
        'checked_out_by',
        'checked_out_to',
        'inventory_lot_id',
        'quantity',
        'checked_out_at',
        'due_date',
        'returned_at',
        'condition_at_checkout',
        'condition_at_return',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'checked_out_at' => 'datetime',
            'due_date'       => 'datetime',
            'returned_at'    => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function checkedOutTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_to');
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }

    public function checkin(): HasOne
    {
        return $this->hasOne(Checkin::class);
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(Renewal::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isOverdue(): bool
    {
        return $this->returned_at === null && $this->due_date !== null && $this->due_date->isPast();
    }

    public function daysUntilDue(): ?int
    {
        if ($this->due_date === null) {
            return null;
        }

        return (int) now()->diffInDays($this->due_date, false);
    }

    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }
}
