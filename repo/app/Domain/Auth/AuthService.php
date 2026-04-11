<?php
namespace App\Domain\Auth;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthService
{
    const MAX_FAILED_ATTEMPTS = 5;
    const LOCKOUT_MINUTES = 30;

    public function authenticate(string $username, string $password): User
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            throw ValidationException::withMessages(['username' => 'Invalid credentials.']);
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            throw ValidationException::withMessages(['username' => 'Account is locked. Try again later.']);
        }

        if ($user->account_status !== 'active') {
            throw ValidationException::withMessages(['username' => 'Account is not active.']);
        }

        // Note: department-level blacklists are enforced at the resource-access layer
        // (AvailabilityService::checkUserResourceAccess), not at login.
        // Only account_status='suspended' blocks login entirely.

        if (!Hash::check($password, $user->password)) {
            $user->increment('failed_login_attempts');
            if ($user->failed_login_attempts >= self::MAX_FAILED_ATTEMPTS) {
                $user->update(['locked_until' => now()->addMinutes(self::LOCKOUT_MINUTES)]);
            }
            $this->auditLog($user->id, 'login_failed');
            throw ValidationException::withMessages(['username' => 'Invalid credentials.']);
        }

        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ]);

        $this->auditLog($user->id, 'login_success');
        return $user;
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'force_password_change' => false,
        ]);

        $this->auditLog($user->id, 'password_changed');
    }

    public function forcePasswordChange(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword),
            'force_password_change' => false,
        ]);
        $this->auditLog($user->id, 'password_force_changed');
    }

    protected function auditLog(int $userId, string $action, array $context = []): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'context' => $context ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
