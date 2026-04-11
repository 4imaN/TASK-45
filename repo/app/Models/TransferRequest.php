<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransferRequest extends Model
{
    protected $fillable = [
        'resource_id',
        'inventory_lot_id',
        'from_department_id',
        'to_department_id',
        'initiated_by',
        'approved_by',
        'status',
        'quantity',
        'reason',
        'idempotency_key',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function custodyRecords(): HasMany
    {
        return $this->hasMany(CustodyRecord::class);
    }

    public function departmentHandoffs(): HasMany
    {
        return $this->hasMany(DepartmentHandoff::class);
    }
}
