<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PointsLedger extends Model
{
    protected $table = 'points_ledger';

    protected $fillable = [
        'user_id',
        'points',
        'balance_after',
        'transaction_type',
        'description',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'points'        => 'integer',
            'balance_after' => 'integer',
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
