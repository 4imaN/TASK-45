<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterventionLog extends Model
{
    protected $fillable = [
        'user_id',
        'action_type',
        'reason',
        'details',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'details'     => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
