<?php
namespace App\Domain\Membership;

use App\Models\{User, PointsLedger, AuditLog};
use Illuminate\Support\Facades\DB;

class PointsService
{
    public function awardPoints(User $user, int $points, string $description, ?string $refType = null, ?int $refId = null): PointsLedger
    {
        return DB::transaction(function () use ($user, $points, $description, $refType, $refId) {
            $currentBalance = PointsLedger::where('user_id', $user->id)
                ->orderByDesc('id')
                ->value('balance_after') ?? 0;

            // Apply points multiplier from membership tier
            $multiplier = $user->membership?->tier?->points_multiplier ?? 1.0;
            $adjustedPoints = (int) round($points * $multiplier);

            return PointsLedger::create([
                'user_id' => $user->id,
                'points' => $adjustedPoints,
                'balance_after' => $currentBalance + $adjustedPoints,
                'transaction_type' => 'earned',
                'description' => $description,
                'reference_type' => $refType,
                'reference_id' => $refId,
            ]);
        });
    }

    public function spendPoints(User $user, int $points, string $description, ?string $refType = null, ?int $refId = null): PointsLedger
    {
        return DB::transaction(function () use ($user, $points, $description, $refType, $refId) {
            $currentBalance = PointsLedger::where('user_id', $user->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('balance_after') ?? 0;

            if ($currentBalance < $points) {
                throw new \App\Common\Exceptions\BusinessRuleException("Insufficient points. Balance: {$currentBalance}, Required: {$points}");
            }

            return PointsLedger::create([
                'user_id' => $user->id,
                'points' => -$points,
                'balance_after' => $currentBalance - $points,
                'transaction_type' => 'spent',
                'description' => $description,
                'reference_type' => $refType,
                'reference_id' => $refId,
            ]);
        });
    }

    public function getBalance(User $user): int
    {
        return PointsLedger::where('user_id', $user->id)
            ->orderByDesc('id')
            ->value('balance_after') ?? 0;
    }
}
