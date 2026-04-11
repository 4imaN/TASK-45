<?php
namespace App\Domain\Membership;

use App\Models\{User, StoredValueLedger, Hold, AuditLog};
use Illuminate\Support\Facades\DB;

class StoredValueService
{
    const HIGH_VALUE_THRESHOLD_CENTS = 20000; // $200.00
    const HIGH_FREQUENCY_THRESHOLD = 5;
    const HIGH_FREQUENCY_WINDOW_MINUTES = 10;
    const DEFAULT_HOLD_HOURS = 24;

    public function deposit(User $user, int $amountCents, string $description, ?User $createdBy = null): StoredValueLedger
    {
        return DB::transaction(function () use ($user, $amountCents, $description, $createdBy) {
            $currentBalance = $this->getBalanceCents($user);

            return StoredValueLedger::create([
                'user_id' => $user->id,
                'amount_cents' => $amountCents,
                'balance_after_cents' => $currentBalance + $amountCents,
                'transaction_type' => 'deposit',
                'description' => $description,
                'created_by' => $createdBy?->id,
            ]);
        });
    }

    public function redeem(User $user, int $amountCents, string $description, ?string $idempotencyKey = null, ?string $refType = null, ?int $refId = null): StoredValueLedger
    {
        // Pre-transaction checks: holds are created outside the deduction transaction
        // so they persist even when the exception prevents the redemption.

        // Check for active holds
        if ($user->holds()->where('status', 'active')->exists()) {
            throw new \App\Common\Exceptions\BusinessRuleException('Account has active holds. Redemption blocked.');
        }

        // High-value check
        if ($amountCents > self::HIGH_VALUE_THRESHOLD_CENTS) {
            $this->triggerHold($user, 'high_value', "Redemption of \${$this->formatCents($amountCents)} exceeds threshold.");
            throw new \App\Common\Exceptions\BusinessRuleException('High-value redemption triggered a hold. Please contact an administrator.');
        }

        // High-frequency check
        $recentCount = StoredValueLedger::where('user_id', $user->id)
            ->where('transaction_type', 'redemption')
            ->where('created_at', '>=', now()->subMinutes(self::HIGH_FREQUENCY_WINDOW_MINUTES))
            ->count();

        if ($recentCount >= self::HIGH_FREQUENCY_THRESHOLD) {
            $this->triggerHold($user, 'frequency', "More than " . self::HIGH_FREQUENCY_THRESHOLD . " redemptions in " . self::HIGH_FREQUENCY_WINDOW_MINUTES . " minutes.");
            throw new \App\Common\Exceptions\BusinessRuleException('Too many redemptions. A temporary hold has been placed.');
        }

        // Deduction transaction: balance check + ledger write under lock
        return DB::transaction(function () use ($user, $amountCents, $description, $idempotencyKey, $refType, $refId) {
            $currentBalance = StoredValueLedger::where('user_id', $user->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('balance_after_cents') ?? 0;

            if ($currentBalance < $amountCents) {
                throw new \App\Common\Exceptions\BusinessRuleException("Insufficient stored value. Balance: \${$this->formatCents($currentBalance)}, Required: \${$this->formatCents($amountCents)}");
            }

            return StoredValueLedger::create([
                'user_id' => $user->id,
                'amount_cents' => -$amountCents,
                'balance_after_cents' => $currentBalance - $amountCents,
                'transaction_type' => 'redemption',
                'description' => $description,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    public function getBalanceCents(User $user): int
    {
        return StoredValueLedger::where('user_id', $user->id)
            ->orderByDesc('id')
            ->value('balance_after_cents') ?? 0;
    }

    protected function triggerHold(User $user, string $type, string $reason): Hold
    {
        $hold = Hold::create([
            'user_id' => $user->id,
            'hold_type' => $type,
            'reason' => $reason,
            'status' => 'active',
            'triggered_at' => now(),
            'expires_at' => now()->addHours(self::DEFAULT_HOLD_HOURS),
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'hold_triggered',
            'auditable_type' => Hold::class,
            'auditable_id' => $hold->id,
            'context' => ['type' => $type, 'reason' => $reason],
        ]);

        // Create intervention log for administrator review
        \App\Models\InterventionLog::create([
            'user_id' => $user->id,
            'action_type' => "hold_{$type}",
            'reason' => $reason,
            'details' => ['hold_id' => $hold->id, 'hold_type' => $type],
        ]);

        return $hold;
    }

    protected function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
