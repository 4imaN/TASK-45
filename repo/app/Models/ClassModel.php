<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a class section. Named ClassModel to avoid collision with PHP
 * reserved word 'Class'. Maps to the `classes` database table.
 */
class ClassModel extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'course_id',
        'name',
        'section',
        'semester',
        'year',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'class_id');
    }

    public function loanRequests(): HasMany
    {
        return $this->hasMany(LoanRequest::class, 'class_id');
    }

    public function reservationRequests(): HasMany
    {
        return $this->hasMany(ReservationRequest::class, 'class_id');
    }

    public function permissionScopes(): HasMany
    {
        return $this->hasMany(PermissionScope::class, 'class_id');
    }
}
