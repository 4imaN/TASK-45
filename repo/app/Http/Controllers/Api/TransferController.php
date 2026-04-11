<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Transfers\TransferService;
use App\Models\TransferRequest;
use App\Http\Resources\TransferResource;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(protected TransferService $transferService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', TransferRequest::class);
        $user = $request->user();

        $query = TransferRequest::with(['resource', 'fromDepartment', 'toDepartment', 'initiatedBy']);

        if (!$user->isAdmin()) {
            if (!$user->permissionScopes()->where('scope_type', 'full')->exists()) {
                $policy = new \App\Policies\TransferRequestPolicy();
                // Use reflection to call the protected method, or extract it
                // Simpler: inline the same derivation
                $deptIds = $user->permissionScopes()->whereNotNull('department_id')->pluck('department_id');
                $courseIds = $user->permissionScopes()->whereNotNull('course_id')->pluck('course_id');
                if ($courseIds->isNotEmpty()) {
                    $courseDeptIds = \App\Models\Course::whereIn('id', $courseIds)->whereNotNull('department_id')->pluck('department_id');
                    $deptIds = $deptIds->merge($courseDeptIds);
                }
                $classIds = $user->permissionScopes()->whereNotNull('class_id')->pluck('class_id');
                if ($classIds->isNotEmpty()) {
                    $classCourseIds = \App\Models\ClassModel::whereIn('id', $classIds)->pluck('course_id');
                    $classDeptIds = \App\Models\Course::whereIn('id', $classCourseIds)->whereNotNull('department_id')->pluck('department_id');
                    $deptIds = $deptIds->merge($classDeptIds);
                }
                $deptIds = $deptIds->unique();

                $query->where(function ($q) use ($deptIds) {
                    $q->whereIn('from_department_id', $deptIds)
                      ->orWhereIn('to_department_id', $deptIds);
                });
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return TransferResource::collection($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $this->authorize('create', TransferRequest::class);
        $request->validate([
            'inventory_lot_id' => 'required|exists:inventory_lots,id',
            'from_department_id' => 'required|exists:departments,id',
            'to_department_id' => 'required|exists:departments,id|different:from_department_id',
            'quantity' => 'integer|min:1',
            'reason' => 'nullable|string',
            'idempotency_key' => 'required|string',
        ]);

        // Verify user has scope on the source department (derived from course/class/dept scopes)
        $user = $request->user();
        if (!$user->isAdmin()) {
            $policy = new \App\Policies\TransferRequestPolicy();
            $dummyTransfer = new TransferRequest([
                'from_department_id' => $request->from_department_id,
                'to_department_id' => $request->to_department_id,
            ]);
            if (!$policy->approve($user, $dummyTransfer)) {
                return response()->json(['error' => 'You do not have scope on the source or destination department.'], 403);
            }
        }

        $transfer = $this->transferService->initiateTransfer($request->user(), $request->all());
        $transfer->load(['resource', 'fromDepartment', 'toDepartment', 'initiatedBy']);
        return (new TransferResource($transfer))->response()->setStatusCode(201);
    }

    public function approve(Request $request, TransferRequest $transfer)
    {
        $this->authorize('approve', $transfer);
        $transfer = $this->transferService->approveTransfer($transfer, $request->user());
        return new TransferResource($transfer);
    }

    public function markInTransit(Request $request, TransferRequest $transfer)
    {
        $this->authorize('transition', $transfer);
        $transfer = $this->transferService->markInTransit($transfer, $request->user());
        return new TransferResource($transfer);
    }

    public function complete(Request $request, TransferRequest $transfer)
    {
        $this->authorize('transition', $transfer);
        $transfer = $this->transferService->completeTransfer($transfer, $request->user());
        return new TransferResource($transfer);
    }
    public function cancel(Request $request, TransferRequest $transfer)
    {
        $this->authorize('transition', $transfer);
        $transfer = $this->transferService->cancelTransfer($transfer, $request->user());
        return new TransferResource($transfer);
    }
}

