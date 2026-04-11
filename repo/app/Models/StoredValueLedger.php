<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StoredValueLedger extends Model
{
    protected $table = 'stored_value_ledger';

    protected $fillable = [
        'user_id',
        'amount_cents',
        'balance_after_cents',
        'transaction_type',
        'description',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents'        => 'integer',
            'balance_after_cents' => 'integer',
            'description'         => 'encrypted',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
