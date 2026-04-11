<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentHandoff extends Model
{
    protected $fillable = [
        'transfer_request_id',
        'from_custodian_id',
        'to_custodian_id',
        'handed_off_at',
        'condition',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'handed_off_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(TransferRequest::class);
    }

    public function fromCustodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_custodian_id');
    }

    public function toCustodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_custodian_id');
    }
}
