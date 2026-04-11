<?php
namespace App\Domain\Audit;

use App\Models\AuditLog;
use App\Models\User;

class AuditService
{
    public function log(User $user, string $action, ?string $auditableType = null, ?int $auditableId = null, ?array $oldValues = null, ?array $newValues = null, ?array $context = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'context' => $context,
        ]);
    }

    public function query(array $filters = [])
    {
        $query = AuditLog::query();

        if (!empty($filters['user_id'])) $query->where('user_id', $filters['user_id']);
        if (!empty($filters['action'])) $query->where('action', $filters['action']);
        if (!empty($filters['auditable_type'])) $query->where('auditable_type', $filters['auditable_type']);
        if (!empty($filters['auditable_id'])) $query->where('auditable_id', $filters['auditable_id']);
        if (!empty($filters['from'])) $query->where('created_at', '>=', $filters['from']);
        if (!empty($filters['to'])) $query->where('created_at', '<=', $filters['to']);

        return $query->orderByDesc('created_at');
    }
}
