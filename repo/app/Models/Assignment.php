<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = [
        'class_id',
        'name',
        'description',
        'due_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'due_date'  => 'date',
            'is_active' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function classModel(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function loanRequests(): HasMany
    {
        return $this->hasMany(LoanRequest::class);
    }

    public function reservationRequests(): HasMany
    {
        return $this->hasMany(ReservationRequest::class);
    }

    public function permissionScopes(): HasMany
    {
        return $this->hasMany(PermissionScope::class, 'assignment_id');
    }
}
