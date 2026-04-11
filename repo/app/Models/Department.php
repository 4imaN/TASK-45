<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function permissionScopes(): HasMany
    {
        return $this->hasMany(PermissionScope::class, 'department_id');
    }

    public function transferRequests(): HasMany
    {
        return $this->hasMany(TransferRequest::class, 'from_department_id');
    }
}
