<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLot extends Model
{
    protected $fillable = [
        'resource_id',
        'department_id',
        'lot_number',
        'total_quantity',
        'serviceable_quantity',
        'location',
        'condition',
        'notes',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function checkouts(): HasMany
    {
        return $this->hasMany(Checkout::class);
    }

    public function custodyRecords(): HasMany
    {
        return $this->hasMany(CustodyRecord::class);
    }

    public function transferRequests(): HasMany
    {
        return $this->hasMany(TransferRequest::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Compute available quantity: total minus currently checked-out minus
     * approved-but-not-yet-checked-out loan requests.
     */
    public function availableQuantity(): int
    {
        $checkedOut = $this->checkouts()
            ->whereNull('returned_at')
            ->count();

        $approvedPending = LoanRequest::where('inventory_lot_id', $this->id)
            ->where('status', 'approved')
            ->whereDoesntHave('checkout')
            ->count();

        return max(0, ($this->total_quantity ?? 0) - $checkedOut - $approvedPending);
    }
}
