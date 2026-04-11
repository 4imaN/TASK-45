<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReminderEvent extends Model
{
    protected $fillable = [
        'remindable_type',
        'remindable_id',
        'user_id',
        'reminder_type',
        'scheduled_at',
        'sent_at',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at'    => 'datetime',
            'sent_at'         => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }
}
