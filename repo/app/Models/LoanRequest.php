<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class LoanRequest extends Model
{
    protected $fillable = [
        'user_id',
        'resource_id',
        'inventory_lot_id',
        'quantity',
        'status',
        'requested_at',
        'due_date',
        'notes',
        'idempotency_key',
        'class_id',
        'assignment_id',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'due_date'     => 'datetime',
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

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
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

    public function checkout(): HasOne
    {
        return $this->hasOne(Checkout::class);
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
        return $query->whereIn('status', ['approved', 'checked_out']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'checked_out')
            ->where('due_date', '<', now());
    }
}
