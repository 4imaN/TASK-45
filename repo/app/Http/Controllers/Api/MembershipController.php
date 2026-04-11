<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Membership\MembershipService;
use App\Domain\Membership\PointsService;
use App\Domain\Membership\StoredValueService;
use App\Models\MembershipTier;
use App\Models\EntitlementPackage;
use App\Models\EntitlementGrant;
use App\Models\User;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function __construct(
        protected MembershipService $membershipService,
        protected PointsService $pointsService,
        protected StoredValueService $storedValueService,
    ) {}

    public function tiers()
    {
        return response()->json(MembershipTier::all());
    }

    public function myMembership(Request $request)
    {
        $user = $request->user()->load('membership.tier', 'entitlementGrants.package');
        $membership = $user->membership;

        return response()->json([
            'membership' => $membership ? [
                'id' => $membership->id,
                'tier_id' => $membership->tier_id,
                'tier_name' => $membership->tier?->name,
                'tier' => $membership->tier,
                'status' => $membership->status,
                'started_at' => $membership->started_at,
                'starts_at' => $membership->started_at,
                'expires_at' => $membership->expires_at,
            ] : null,
            'points_balance' => $this->pointsService->getBalance($user),
            'stored_value_cents' => $this->storedValueService->getBalanceCents($user),
            'entitlements' => $user->entitlementGrants->map(fn($g) => [
                'id' => $g->id,
                'package' => $g->package,
                'package_name' => $g->package?->name,
                'remaining' => $g->remaining_quantity,
                'remaining_quantity' => $g->remaining_quantity,
                'limit' => $g->package?->quantity,
                'usage' => $g->package ? $g->package->quantity - $g->remaining_quantity : 0,
                'unit' => $g->package?->unit,
                'expires_at' => $g->expires_at,
                'granted_at' => $g->granted_at,
            ]),
            'loan_rules' => [
                'max_active' => $membership?->tier?->max_active_loans ?? 2,
                'max_days' => $membership?->tier?->max_loan_days ?? 7,
                'max_renewals' => $membership?->tier?->max_renewals ?? 1,
            ],
        ]);
    }

    public function packages()
    {
        return response()->json(EntitlementPackage::all());
    }

    public function redeemPoints(Request $request)
    {
        $request->validate(['points' => 'required|integer|min:1', 'description' => 'required|string']);
        $entry = $this->pointsService->spendPoints($request->user(), $request->points, $request->description);
        return response()->json(['message' => 'Points redeemed.', 'balance' => $entry->balance_after]);
    }

    public function redeemStoredValue(Request $request)
    {
        $request->validate([
            'amount_cents' => 'required|integer|min:1',
            'description' => 'required|string',
            'idempotency_key' => 'required|string',
        ]);

        $entry = $this->storedValueService->redeem(
            $request->user(), $request->amount_cents, $request->description, $request->idempotency_key
        );
        return response()->json(['message' => 'Redeemed.', 'balance_cents' => $entry->balance_after_cents]);
    }

    public function consumeEntitlement(Request $request, EntitlementGrant $grant)
    {
        $this->authorize('consume', $grant);
        $request->validate(['quantity' => 'required|integer|min:1']);
        $this->membershipService->consumeEntitlement($grant, $request->quantity, $request->resource_id, $request->notes, $request->user());
        return response()->json(['message' => 'Entitlement consumed.', 'remaining' => $grant->fresh()->remaining_quantity]);
    }

    public function assignTier(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'tier_id' => 'required|exists:membership_tiers,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $tier = MembershipTier::findOrFail($request->tier_id);
        $membership = $this->membershipService->assignTier($user, $tier, $request->user());
        return response()->json(['message' => 'Tier assigned.', 'membership' => $membership]);
    }

    public function depositStoredValue(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount_cents' => 'required|integer|min:1',
            'description' => 'required|string',
        ]);

        $user = User::findOrFail($request->user_id);
        $entry = $this->storedValueService->deposit($user, $request->amount_cents, $request->description, $request->user());
        return response()->json(['message' => 'Deposited.', 'balance_cents' => $entry->balance_after_cents]);
    }

    public function grantEntitlement(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'package_id' => 'required|exists:entitlement_packages,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $package = EntitlementPackage::findOrFail($request->package_id);
        $grant = $this->membershipService->grantEntitlement($user, $package, $request->user());
        return response()->json(['message' => 'Entitlement granted.', 'grant' => $grant]);
    }
}
