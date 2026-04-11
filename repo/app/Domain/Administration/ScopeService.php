<?php
namespace App\Domain\Administration;

use App\Models\{PermissionScope, Allowlist, Blacklist, Hold, User, AuditLog, InterventionLog};
use Illuminate\Support\Facades\DB;

class ScopeService
{
    public function assignScope(User $user, array $data, User $grantedBy): PermissionScope
    {
        return DB::transaction(function () use ($user, $data, $grantedBy) {
            $scope = PermissionScope::create([
                'user_id' => $user->id,
                'course_id' => $data['course_id'] ?? null,
                'class_id' => $data['class_id'] ?? null,
                'assignment_id' => $data['assignment_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'scope_type' => $data['scope_type'],
                'granted_by' => $grantedBy->id,
            ]);

            AuditLog::create([
                'user_id' => $grantedBy->id,
                'action' => 'scope_assigned',
                'auditable_type' => PermissionScope::class,
                'auditable_id' => $scope->id,
                'new_values' => $data,
            ]);

            return $scope;
        });
    }

    public function addToAllowlist(string $scopeType, int $scopeId, User $user, string $reason, User $addedBy): Allowlist
    {
        $entry = Allowlist::create([
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'user_id' => $user->id,
            'reason' => $reason,
            'added_by' => $addedBy->id,
        ]);

        AuditLog::create([
            'user_id' => $addedBy->id,
            'action' => 'allowlist_added',
            'auditable_type' => Allowlist::class,
            'auditable_id' => $entry->id,
        ]);

        return $entry;
    }

    public function addToBlacklist(string $scopeType, int $scopeId, User $user, string $reason, User $addedBy, ?\DateTimeInterface $expiresAt = null): Blacklist
    {
        $entry = Blacklist::create([
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'user_id' => $user->id,
            'reason' => $reason,
            'added_by' => $addedBy->id,
            'expires_at' => $expiresAt,
        ]);

        AuditLog::create([
            'user_id' => $addedBy->id,
            'action' => 'blacklist_added',
            'auditable_type' => Blacklist::class,
            'auditable_id' => $entry->id,
        ]);

        return $entry;
    }

    public function releaseHold(Hold $hold, User $admin, string $reason): Hold
    {
        return DB::transaction(function () use ($hold, $admin, $reason) {
            $hold->update([
                'status' => 'released',
                'released_at' => now(),
                'released_by' => $admin->id,
                'release_reason' => $reason,
            ]);

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'hold_released',
                'auditable_type' => Hold::class,
                'auditable_id' => $hold->id,
                'context' => ['reason' => $reason],
            ]);

            return $hold->fresh();
        });
    }

    public function revealSensitiveField(User $actor, string $modelType, int $modelId, array $fields, string $reason): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'sensitive_field_revealed',
            'auditable_type' => $modelType,
            'auditable_id' => $modelId,
            'context' => [
                'fields' => $fields,
                'reason' => $reason,
            ],
        ]);
    }
}
