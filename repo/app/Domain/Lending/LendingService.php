<?php
namespace App\Domain\Lending;

use App\Domain\Availability\AvailabilityService;
use App\Models\{LoanRequest, Checkout, Checkin, Renewal, InventoryLot, User, Waitlist, AuditLog};
use App\Domain\Membership\PointsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LendingService
{
    public function __construct(
        protected AvailabilityService $availability,
        protected PointsService $pointsService,
    ) {}

    public function createLoanRequest(User $user, array $data): LoanRequest
    {
        return DB::transaction(function () use ($user, $data) {
            // Check allowlist/blacklist
            $resource = \App\Models\Resource::findOrFail($data['resource_id']);
            $accessCheck = $this->availability->checkUserResourceAccess($user, $resource);
            if (!$accessCheck['allowed']) {
                throw new \App\Common\Exceptions\BusinessRuleException($accessCheck['reason']);
            }

            // Only equipment resources can be loaned
            if ($resource->resource_type !== 'equipment') {
                throw new \App\Common\Exceptions\BusinessRuleException(
                    'Only equipment resources can be requested for loan. Use reservations for venues or membership for entitlement packages.'
                );
            }

            // Check user limits
            $check = $this->availability->canUserBorrow($user, $data['quantity'] ?? 1);
            if (!$check['allowed']) {
                throw new \App\Common\Exceptions\BusinessRuleException($check['reason']);
            }

            // Find a lot with sufficient available quantity across all lots for this resource
            $requestedQty = $data['quantity'] ?? 1;
            $lots = InventoryLot::where('resource_id', $data['resource_id'])
                ->lockForUpdate()
                ->get();

            if ($lots->isEmpty()) {
                throw new \App\Common\Exceptions\BusinessRuleException('No inventory available for this resource.');
            }

            // Pick the first lot that can fulfill the request
            $lot = null;
            foreach ($lots as $candidate) {
                if ($this->availability->getLotAvailableQuantity($candidate) >= $requestedQty) {
                    $lot = $candidate;
                    break;
                }
            }

            if (!$lot) {
                // Calculate total available across all lots for the error message
                $totalAvailable = $lots->sum(fn($l) => $this->availability->getLotAvailableQuantity($l));
                throw new \App\Common\Exceptions\BusinessRuleException(
                    "Insufficient inventory. Available: {$totalAvailable}, Requested: {$requestedQty}"
                );
            }

            // Require class context if the student has class/course scopes
            // (ensures scoped staff can see and approve the request)
            $classId = $data['class_id'] ?? null;
            $assignmentId = $data['assignment_id'] ?? null;

            if (!$classId && !$assignmentId) {
                $hasScopes = $user->permissionScopes()
                    ->where(function ($q) {
                        $q->whereNotNull('class_id')
                          ->orWhereNotNull('course_id');
                    })->exists();
                if ($hasScopes) {
                    throw new \App\Common\Exceptions\BusinessRuleException(
                        'Please select a class for this request so it can be routed to the appropriate staff for approval.'
                    );
                }
            }

            if ($classId) {
                $hasClassScope = $user->permissionScopes()
                    ->where(function ($q) use ($classId) {
                        $q->where('class_id', $classId)
                          ->orWhere('scope_type', 'full');
                    })->exists();
                // Also allow if user has course scope covering this class
                if (!$hasClassScope) {
                    $courseId = \App\Models\ClassModel::where('id', $classId)->value('course_id');
                    $hasClassScope = $courseId && $user->permissionScopes()->where('course_id', $courseId)->exists();
                }
                if (!$hasClassScope) {
                    throw new \App\Common\Exceptions\BusinessRuleException('You are not enrolled in the specified class.');
                }
            }

            if ($assignmentId) {
                $assignment = \App\Models\Assignment::find($assignmentId);
                if ($assignment) {
                    $hasAssignmentScope = $user->permissionScopes()
                        ->where(function ($q) use ($assignmentId, $assignment) {
                            $q->where('assignment_id', $assignmentId)
                              ->orWhere('class_id', $assignment->class_id)
                              ->orWhere('scope_type', 'full');
                        })->exists();
                    if (!$hasAssignmentScope) {
                        $courseId = \App\Models\ClassModel::where('id', $assignment->class_id)->value('course_id');
                        $hasAssignmentScope = $courseId && $user->permissionScopes()->where('course_id', $courseId)->exists();
                    }
                    if (!$hasAssignmentScope) {
                        throw new \App\Common\Exceptions\BusinessRuleException('You are not enrolled in the class for this assignment.');
                    }
                }
            }

            $loanRequest = LoanRequest::create([
                'user_id' => $user->id,
                'resource_id' => $data['resource_id'],
                'inventory_lot_id' => $lot->id,
                'quantity' => $requestedQty,
                'status' => 'pending',
                'requested_at' => now(),
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'class_id' => $classId,
                'assignment_id' => $assignmentId,
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'loan_request_created',
                'auditable_type' => LoanRequest::class,
                'auditable_id' => $loanRequest->id,
            ]);

            return $loanRequest;
        });
    }

    public function approveLoanRequest(LoanRequest $loanRequest, User $approver, string $status, ?string $reason = null): LoanRequest
    {
        return DB::transaction(function () use ($loanRequest, $approver, $status, $reason) {
            $loanRequest = LoanRequest::whereKey($loanRequest->id)->lockForUpdate()->firstOrFail();

            if ($loanRequest->status !== 'pending') {
                throw new \App\Common\Exceptions\BusinessRuleException('Can only approve pending requests.');
            }

            if ($status === 'approved') {
                // Re-check availability
                $lot = InventoryLot::where('id', $loanRequest->inventory_lot_id)->lockForUpdate()->first();
                $available = $this->availability->getLotAvailableQuantity($lot);
                if ($available < $loanRequest->quantity) {
                    throw new \App\Common\Exceptions\BusinessRuleException('Insufficient inventory for approval.');
                }
            }

            $loanRequest->update(['status' => $status]);

            $loanRequest->approval()->create([
                'approved_by' => $approver->id,
                'status' => $status,
                'reason' => $reason,
            ]);

            AuditLog::create([
                'user_id' => $approver->id,
                'action' => "loan_request_{$status}",
                'auditable_type' => LoanRequest::class,
                'auditable_id' => $loanRequest->id,
                'context' => ['reason' => $reason],
            ]);

            return $loanRequest->fresh();
        });
    }

    public function checkout(LoanRequest $loanRequest, User $staff): Checkout
    {
        return DB::transaction(function () use ($loanRequest, $staff) {
            $loanRequest = LoanRequest::whereKey($loanRequest->id)->lockForUpdate()->firstOrFail();

            if ($loanRequest->status !== 'approved') {
                throw new \App\Common\Exceptions\BusinessRuleException('Can only checkout approved requests.');
            }

            $lot = InventoryLot::where('id', $loanRequest->inventory_lot_id)->lockForUpdate()->first();
            $dueDate = now()->addDays($this->availability->getMaxLoanDays($loanRequest->user));

            $checkout = Checkout::create([
                'loan_request_id' => $loanRequest->id,
                'checked_out_by' => $staff->id,
                'checked_out_to' => $loanRequest->user_id,
                'inventory_lot_id' => $lot->id,
                'quantity' => $loanRequest->quantity,
                'checked_out_at' => now(),
                'due_date' => $dueDate,
            ]);

            $loanRequest->update([
                'status' => 'checked_out',
                'due_date' => $dueDate,
            ]);

            Log::channel('operations')->info('Checkout completed', [
                'loan_id' => $loanRequest->id, 'checkout_id' => $checkout->id,
                'user_id' => $loanRequest->user_id, 'staff_id' => $staff->id,
            ]);

            AuditLog::create([
                'user_id' => $staff->id,
                'action' => 'checkout_performed',
                'auditable_type' => Checkout::class,
                'auditable_id' => $checkout->id,
            ]);

            return $checkout;
        });
    }

    public function checkin(Checkout $checkout, User $staff, ?string $condition = null, ?string $notes = null): Checkin
    {
        return DB::transaction(function () use ($checkout, $staff, $condition, $notes) {
            $checkout = Checkout::whereKey($checkout->id)->lockForUpdate()->firstOrFail();

            if ($checkout->returned_at) {
                throw new \App\Common\Exceptions\BusinessRuleException('Item already returned.');
            }

            $checkin = Checkin::create([
                'checkout_id' => $checkout->id,
                'checked_in_by' => $staff->id,
                'checked_in_at' => now(),
                'condition' => $condition,
                'notes' => $notes,
            ]);

            $checkout->update([
                'returned_at' => now(),
                'condition_at_return' => $condition,
            ]);

            $checkout->loanRequest->update(['status' => 'returned']);

            Log::channel('operations')->info('Checkin completed', [
                'checkout_id' => $checkout->id, 'staff_id' => $staff->id,
            ]);

            // Award points for on-time return
            if (!$checkout->isOverdue()) {
                $this->pointsService->awardPoints(
                    $checkout->checkedOutTo,
                    10,
                    'On-time return',
                    Checkout::class,
                    $checkout->id
                );
            }

            // Notify waitlist
            $this->notifyWaitlist($checkout->loanRequest->resource_id);

            AuditLog::create([
                'user_id' => $staff->id,
                'action' => 'checkin_performed',
                'auditable_type' => Checkin::class,
                'auditable_id' => $checkin->id,
            ]);

            return $checkin;
        });
    }

    public function renew(Checkout $checkout, User $user): Renewal
    {
        return DB::transaction(function () use ($checkout, $user) {
            $checkout = Checkout::whereKey($checkout->id)->lockForUpdate()->firstOrFail();

            $check = $this->availability->canRenew($checkout);
            if (!$check['allowed']) {
                throw new \App\Common\Exceptions\BusinessRuleException($check['reason']);
            }

            $loanDays = $this->availability->getMaxLoanDays($checkout->checkedOutTo);
            $newDueDate = $checkout->due_date->copy()->addDays($loanDays);

            $renewal = Renewal::create([
                'checkout_id' => $checkout->id,
                'renewed_by' => $user->id,
                'original_due_date' => $checkout->due_date,
                'new_due_date' => $newDueDate,
                'renewal_number' => $checkout->renewals()->count() + 1,
            ]);

            $checkout->update(['due_date' => $newDueDate]);
            $checkout->loanRequest->update(['due_date' => $newDueDate]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'loan_renewed',
                'auditable_type' => Renewal::class,
                'auditable_id' => $renewal->id,
            ]);

            return $renewal;
        });
    }

    protected function notifyWaitlist(int $resourceId): void
    {
        $next = Waitlist::where('resource_id', $resourceId)
            ->whereNull('fulfilled_at')
            ->whereNull('expired_at')
            ->orderBy('position')
            ->first();

        if ($next) {
            $next->update(['notified_at' => now()]);
        }
    }
}
