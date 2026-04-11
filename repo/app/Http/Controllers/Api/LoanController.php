<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Lending\LendingService;
use App\Models\LoanRequest;
use App\Models\Checkout;
use App\Http\Requests\CreateLoanRequest;
use App\Http\Requests\ApproveLoanRequestForm;
use App\Http\Resources\LoanRequestResource;
use App\Http\Resources\CheckoutResource;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(protected LendingService $lending) {}

    public function index(Request $request)
    {
        $query = LoanRequest::with(['user', 'resource', 'approval', 'checkout']);
        $user = $request->user();

        if ($user->isStudent()) {
            $query->where('user_id', $user->id);
        } elseif (!$user->isAdmin()) {
            // Teachers/TAs: only loans within their scoped classes/assignments/courses
            $hasFullScope = $user->permissionScopes()->where('scope_type', 'full')->exists();

            if (!$hasFullScope) {
                $scopedClassIds = $user->permissionScopes()->whereNotNull('class_id')->pluck('class_id');
                $scopedAssignmentIds = $user->permissionScopes()->whereNotNull('assignment_id')->pluck('assignment_id');
                // Resolve course scopes to their class IDs
                $scopedCourseIds = $user->permissionScopes()->whereNotNull('course_id')->pluck('course_id');
                $courseClassIds = \App\Models\ClassModel::whereIn('course_id', $scopedCourseIds)->pluck('id');
                $allClassIds = $scopedClassIds->merge($courseClassIds)->unique();

                $query->where(function ($q) use ($allClassIds, $scopedAssignmentIds) {
                    $q->whereIn('class_id', $allClassIds)
                      ->orWhereIn('assignment_id', $scopedAssignmentIds);
                });
            }
        }

        if ($request->filled('status')) $query->where('status', $request->status);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', fn($q) => $q->where('display_name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
        }

        return LoanRequestResource::collection($query->latest()->paginate(20));
    }

    public function store(CreateLoanRequest $request)
    {
        if (!$request->user()->isStudent()) {
            return response()->json(['error' => 'Only students can submit loan requests.'], 403);
        }
        $loan = $this->lending->createLoanRequest($request->user(), $request->validated());
        return (new LoanRequestResource($loan))->response()->setStatusCode(201);
    }

    public function show(LoanRequest $loan)
    {
        $this->authorize('view', $loan);
        return new LoanRequestResource($loan->load(['user', 'resource', 'approval', 'checkout.checkin', 'checkout.renewals']));
    }

    public function approve(ApproveLoanRequestForm $request, LoanRequest $loan)
    {
        $this->authorize('approve', $loan);
        $loan = $this->lending->approveLoanRequest($loan, $request->user(), $request->status, $request->reason);
        return new LoanRequestResource($loan);
    }

    public function checkout(Request $request, LoanRequest $loan)
    {
        $this->authorize('checkout', $loan);
        $checkout = $this->lending->checkout($loan, $request->user());
        return (new CheckoutResource($checkout))->response()->setStatusCode(201);
    }

    public function checkin(Request $request, Checkout $checkout)
    {
        $this->authorize('checkin', $checkout);
        $checkin = $this->lending->checkin($checkout, $request->user(), $request->condition, $request->notes);
        return response()->json(['message' => 'Check-in completed.', 'checkin_id' => $checkin->id]);
    }

    public function renew(Request $request, Checkout $checkout)
    {
        $this->authorize('renew', $checkout);
        $renewal = $this->lending->renew($checkout, $request->user());
        return response()->json(['message' => 'Renewed.', 'new_due_date' => $renewal->new_due_date]);
    }

    public function checkoutsList(Request $request)
    {
        $user = $request->user();

        // Students cannot access the checkouts list
        if ($user->isStudent()) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $query = Checkout::with(['loanRequest.resource', 'loanRequest.user', 'checkedOutTo', 'checkedOutBy']);

        // Scope for non-admin staff
        if (!$user->isAdmin()) {
            $hasFullScope = $user->permissionScopes()->where('scope_type', 'full')->exists();

            if (!$hasFullScope) {
                $scopedClassIds = $user->permissionScopes()->whereNotNull('class_id')->pluck('class_id');
                $scopedAssignmentIds = $user->permissionScopes()->whereNotNull('assignment_id')->pluck('assignment_id');
                $scopedCourseIds = $user->permissionScopes()->whereNotNull('course_id')->pluck('course_id');
                $courseClassIds = \App\Models\ClassModel::whereIn('course_id', $scopedCourseIds)->pluck('id');
                $allClassIds = $scopedClassIds->merge($courseClassIds)->unique();

                $query->whereHas('loanRequest', function ($q) use ($allClassIds, $scopedAssignmentIds) {
                    $q->whereIn('class_id', $allClassIds)
                      ->orWhereIn('assignment_id', $scopedAssignmentIds);
                });
            }
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->whereNull('returned_at');
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('checkedOutTo', fn($q) => $q->where('display_name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
        }

        return CheckoutResource::collection($query->latest()->paginate(20));
    }
}
