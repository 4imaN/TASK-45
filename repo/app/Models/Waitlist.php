<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Waitlist extends Model
{
    protected $fillable = [
        'resource_id',
        'user_id',
        'position',
        'requested_at',
        'notified_at',
        'fulfilled_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'notified_at'  => 'datetime',
            'fulfilled_at' => 'datetime',
            'expired_at'   => 'datetime',
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
}
