<?php
namespace App\Domain\Availability;

use App\Models\Resource;
use App\Models\InventoryLot;
use App\Models\Checkout;
use App\Models\LoanRequest;
use App\Models\ReservationRequest;
use App\Models\CustodyRecord;
use App\Models\User;
use App\Models\VenueTimeSlot;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    const DEFAULT_MAX_ACTIVE_ITEMS = 2;
    const DEFAULT_LOAN_DAYS = 7;
    const DEFAULT_MAX_RENEWALS = 1;

    public function getAvailableQuantity(Resource $resource): int
    {
        return $resource->inventoryLots->sum(function (InventoryLot $lot) {
            return $this->getLotAvailableQuantity($lot);
        });
    }

    public function getLotAvailableQuantity(InventoryLot $lot): int
    {
        $total = $lot->serviceable_quantity;

        // Active checkouts (not returned)
        $checkedOut = Checkout::where('inventory_lot_id', $lot->id)
            ->whereNull('returned_at')
            ->sum('quantity');

        // Approved pending loan requests
        $approvedLoans = LoanRequest::where('inventory_lot_id', $lot->id)
            ->where('status', 'approved')
            ->sum('quantity');

        // Pending/approved equipment reservations overlapping with the present
        $approvedReservations = ReservationRequest::where('resource_id', $lot->resource_id)
            ->where('reservation_type', 'equipment')
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->count();

        // In-transit and source-held transfers (sum quantity from transfer_requests)
        $inTransit = \App\Models\TransferRequest::where('inventory_lot_id', $lot->id)
            ->whereIn('status', ['pending', 'approved', 'in_transit'])
            ->sum('quantity');

        return max(0, $total - $checkedOut - $approvedLoans - $approvedReservations - $inTransit);
    }

    public function getUserActiveItemCount(User $user): int
    {
        $activeCheckouts = Checkout::where('checked_out_to', $user->id)
            ->whereNull('returned_at')
            ->sum('quantity');

        $approvedRequests = LoanRequest::where('user_id', $user->id)
            ->where('status', 'approved')
            ->sum('quantity');

        return $activeCheckouts + $approvedRequests;
    }

    public function canUserBorrow(User $user, int $requestedQuantity = 1): array
    {
        $maxItems = $this->getMaxActiveItems($user);
        $currentCount = $this->getUserActiveItemCount($user);
        $remaining = $maxItems - $currentCount;

        if ($remaining < $requestedQuantity) {
            return [
                'allowed' => false,
                'reason' => "Active item limit reached. Current: {$currentCount}, Max: {$maxItems}, Requested: {$requestedQuantity}",
                'current_count' => $currentCount,
                'max_items' => $maxItems,
            ];
        }

        return ['allowed' => true, 'current_count' => $currentCount, 'max_items' => $maxItems];
    }

    public function isTimeSlotAvailable(int $slotId): bool
    {
        $slot = VenueTimeSlot::find($slotId);
        if (!$slot) return false;
        return $slot->is_available && $slot->reserved_by_reservation_id === null;
    }

    public function getMaxActiveItems(User $user): int
    {
        if ($user->membership && $user->membership->tier) {
            return $user->membership->tier->max_active_loans;
        }
        return self::DEFAULT_MAX_ACTIVE_ITEMS;
    }

    public function getMaxLoanDays(User $user): int
    {
        if ($user->membership && $user->membership->tier) {
            return $user->membership->tier->max_loan_days;
        }
        return self::DEFAULT_LOAN_DAYS;
    }

    public function canRenew(Checkout $checkout): array
    {
        $maxRenewals = self::DEFAULT_MAX_RENEWALS;
        if ($checkout->checkedOutTo->membership?->tier) {
            $maxRenewals = $checkout->checkedOutTo->membership->tier->max_renewals;
        }

        if ($checkout->renewals()->count() >= $maxRenewals) {
            return ['allowed' => false, 'reason' => 'Maximum renewals reached.'];
        }

        // Check waitlist
        $hasWaitlist = \App\Models\Waitlist::where('resource_id', $checkout->loanRequest->resource_id)
            ->whereNull('fulfilled_at')
            ->whereNull('expired_at')
            ->exists();

        if ($hasWaitlist) {
            return ['allowed' => false, 'reason' => 'Cannot renew while others are waiting.'];
        }

        return ['allowed' => true];
    }

    /**
     * Check whether adding one more equipment reservation for the given date range
     * would exceed the resource's available inventory. Returns true if there IS a conflict.
     *
     * Each reservation consumes 1 unit. Available = total serviceable across all lots
     * minus active checkouts minus approved loans minus overlapping reservations minus transfers.
     */
    public function checkEquipmentReservationOverlap(int $resourceId, string $startDate, string $endDate, ?int $excludeId = null): bool
    {
        $resource = Resource::with('inventoryLots')->find($resourceId);
        if (!$resource) return true;

        // Total serviceable across all lots
        $totalServiceable = $resource->inventoryLots->sum('serviceable_quantity');

        // Active checkouts (not returned) for any lot of this resource
        $lotIds = $resource->inventoryLots->pluck('id');
        $checkedOut = Checkout::whereIn('inventory_lot_id', $lotIds)
            ->whereNull('returned_at')
            ->sum('quantity');

        // Approved loan requests
        $approvedLoans = LoanRequest::whereIn('inventory_lot_id', $lotIds)
            ->where('status', 'approved')
            ->sum('quantity');

        // Pending/in-transit transfers
        $inTransfer = \App\Models\TransferRequest::whereIn('inventory_lot_id', $lotIds)
            ->whereIn('status', ['pending', 'approved', 'in_transit'])
            ->sum('quantity');

        // Count overlapping equipment reservations (each = 1 unit)
        $overlappingQuery = ReservationRequest::where('resource_id', $resourceId)
            ->where('reservation_type', 'equipment')
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);

        if ($excludeId) {
            $overlappingQuery->where('id', '!=', $excludeId);
        }

        $overlappingCount = $overlappingQuery->count();

        // Available for this date range = total - committed - already overlapping reservations
        $availableForRange = $totalServiceable - $checkedOut - $approvedLoans - $inTransfer - $overlappingCount;

        // Conflict if adding 1 more reservation would exceed available
        return $availableForRange < 1;
    }

    public function checkUserResourceAccess(User $user, Resource $resource): array
    {
        // Check if user is blacklisted for this resource's department
        $isBlacklisted = \App\Models\Blacklist::where('user_id', $user->id)
            ->where(function ($q) use ($resource) {
                $q->where(function ($inner) use ($resource) {
                    $inner->where('scope_type', 'department')
                          ->where('scope_id', $resource->department_id);
                })->orWhere(function ($inner) {
                    $inner->where('scope_type', 'global');
                });
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($isBlacklisted) {
            return ['allowed' => false, 'reason' => 'You are restricted from accessing resources in this department.'];
        }

        // Check if user is on the allowlist (if allowlist entries exist for this scope, user must be on it)
        $hasAllowlist = \App\Models\Allowlist::where('scope_type', 'department')
            ->where('scope_id', $resource->department_id)
            ->exists();

        if ($hasAllowlist) {
            $isAllowed = \App\Models\Allowlist::where('user_id', $user->id)
                ->where('scope_type', 'department')
                ->where('scope_id', $resource->department_id)
                ->exists();

            if (!$isAllowed) {
                return ['allowed' => false, 'reason' => 'You are not on the allowlist for this department.'];
            }
        }

        return ['allowed' => true];
    }
}
