<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'username',
        'email',
        'password',
        'display_name',
        'phone',
        'force_password_change',
        'account_status',
        'last_login_at',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email',
        'phone',
    ];

    protected function casts(): array
    {
        return [
            'force_password_change' => 'boolean',
            'last_login_at'         => 'datetime',
            'locked_until'          => 'datetime',
            'email'                 => 'encrypted',
            'phone'                 => 'encrypted',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function permissionScopes(): HasMany
    {
        return $this->hasMany(PermissionScope::class);
    }

    public function loanRequests(): HasMany
    {
        return $this->hasMany(LoanRequest::class);
    }

    public function reservationRequests(): HasMany
    {
        return $this->hasMany(ReservationRequest::class);
    }

    public function checkoutsAsStudent(): HasMany
    {
        return $this->hasMany(Checkout::class, 'checked_out_to');
    }

    public function checkoutsAsStaff(): HasMany
    {
        return $this->hasMany(Checkout::class, 'checked_out_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class)->latestOfMany();
    }

    public function storedValueEntries(): HasMany
    {
        return $this->hasMany(StoredValueLedger::class);
    }

    public function pointsEntries(): HasMany
    {
        return $this->hasMany(PointsLedger::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(Hold::class);
    }

    public function fileAssets(): HasMany
    {
        return $this->hasMany(FileAsset::class, 'uploaded_by');
    }

    public function entitlementGrants(): HasMany
    {
        return $this->hasMany(EntitlementGrant::class);
    }

    public function blacklists(): HasMany
    {
        return $this->hasMany(Blacklist::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isTeacher(): bool
    {
        return $this->hasRole('teacher');
    }

    public function isTA(): bool
    {
        return $this->hasRole('ta');
    }

    public function isStudent(): bool
    {
        return $this->hasRole('student');
    }

    public function hasActiveHold(): bool
    {
        return $this->holds()->active()->exists();
    }

    public function getPointsBalance(): int
    {
        return (int) $this->pointsEntries()->sum('points');
    }

    public function getStoredValueBalance(): int
    {
        return (int) $this->storedValueEntries()->sum('amount_cents');
    }
}
