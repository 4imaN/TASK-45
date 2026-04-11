<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Renewal extends Model
{
    protected $fillable = [
        'checkout_id',
        'renewed_by',
        'original_due_date',
        'new_due_date',
        'renewal_number',
    ];

    protected function casts(): array
    {
        return [
            'original_due_date' => 'datetime',
            'new_due_date'      => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }

    public function renewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renewed_by');
    }
}
