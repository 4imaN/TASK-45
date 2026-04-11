<?php
namespace App\Domain\Membership;

use App\Models\{User, Membership, MembershipTier, EntitlementGrant, EntitlementPackage, Hold, AuditLog};
use Illuminate\Support\Facades\DB;

class MembershipService
{
    public function assignTier(User $user, MembershipTier $tier, ?User $admin = null): Membership
    {
        return DB::transaction(function () use ($user, $tier, $admin) {
            $membership = Membership::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'tier_id' => $tier->id,
                    'status' => 'active',
                    'started_at' => now(),
                    'expires_at' => now()->addYear(),
                ]
            );

            AuditLog::create([
                'user_id' => $admin?->id ?? $user->id,
                'action' => 'membership_tier_assigned',
                'auditable_type' => Membership::class,
                'auditable_id' => $membership->id,
                'new_values' => ['tier' => $tier->name, 'target_user_id' => $user->id],
            ]);

            return $membership;
        });
    }

    public function grantEntitlement(User $user, EntitlementPackage $package, ?User $admin = null): EntitlementGrant
    {
        return DB::transaction(function () use ($user, $package, $admin) {
            $grant = EntitlementGrant::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'membership_id' => $user->membership?->id,
                'remaining_quantity' => $package->quantity,
                'granted_at' => now(),
                'expires_at' => now()->addDays($package->validity_days),
            ]);

            AuditLog::create([
                'user_id' => $admin?->id ?? $user->id,
                'action' => 'entitlement_granted',
                'auditable_type' => EntitlementGrant::class,
                'auditable_id' => $grant->id,
                'new_values' => ['package' => $package->name, 'target_user_id' => $user->id],
            ]);
            return $grant;
        });
    }

    public function consumeEntitlement(EntitlementGrant $grant, int $quantity, ?int $resourceId = null, ?string $notes = null, ?User $actor = null): void
    {
        DB::transaction(function () use ($grant, $quantity, $resourceId, $notes, $actor) {
            $grant = EntitlementGrant::whereKey($grant->id)->lockForUpdate()->firstOrFail();

            if ($grant->remaining_quantity < $quantity) {
                throw new \App\Common\Exceptions\BusinessRuleException('Insufficient entitlement balance.');
            }

            if ($grant->expires_at->isPast()) {
                throw new \App\Common\Exceptions\BusinessRuleException('Entitlement has expired.');
            }

            $grant->consumptions()->create([
                'quantity' => $quantity,
                'consumed_at' => now(),
                'resource_id' => $resourceId,
                'notes' => $notes,
            ]);

            $grant->decrement('remaining_quantity', $quantity);

            AuditLog::create([
                'user_id' => $actor?->id ?? $grant->user_id,
                'action' => 'entitlement_consumed',
                'auditable_type' => EntitlementGrant::class,
                'auditable_id' => $grant->id,
                'new_values' => [
                    'quantity' => $quantity,
                    'remaining' => $grant->remaining_quantity,
                    'resource_id' => $resourceId,
                    'target_user_id' => $grant->user_id,
                ],
            ]);
        });
    }
}
