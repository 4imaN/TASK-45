<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Reservations\ReservationService;
use App\Models\ReservationRequest;
use App\Http\Requests\CreateReservationRequestForm;
use App\Http\Resources\ReservationResource;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(protected ReservationService $reservationService) {}

    public function index(Request $request)
    {
        $query = ReservationRequest::with(['user', 'resource', 'venue', 'approval', 'venueTimeSlot']);
        $user = $request->user();

        if ($user->isStudent()) {
            $query->where('user_id', $user->id);
        } elseif (!$user->isAdmin()) {
            $hasFullScope = $user->permissionScopes()->where('scope_type', 'full')->exists();

            if (!$hasFullScope) {
                $scopedClassIds = $user->permissionScopes()->whereNotNull('class_id')->pluck('class_id');
                $scopedAssignmentIds = $user->permissionScopes()->whereNotNull('assignment_id')->pluck('assignment_id');
                $scopedCourseIds = $user->permissionScopes()->whereNotNull('course_id')->pluck('course_id');
                $courseClassIds = \App\Models\ClassModel::whereIn('course_id', $scopedCourseIds)->pluck('id');
                $allClassIds = $scopedClassIds->merge($courseClassIds)->unique();

                $query->where(function ($q) use ($allClassIds, $scopedAssignmentIds) {
                    $q->whereIn('class_id', $allClassIds)
                      ->orWhereIn('assignment_id', $scopedAssignmentIds);
                });
            }
        }

        return ReservationResource::collection($query->latest()->paginate(20));
    }

    public function store(CreateReservationRequestForm $request)
    {
        if (!$request->user()->isStudent()) {
            return response()->json(['error' => 'Only students can submit reservation requests.'], 403);
        }
        $reservation = $this->reservationService->createReservation($request->user(), $request->validated());
        return (new ReservationResource($reservation))->response()->setStatusCode(201);
    }

    public function show(ReservationRequest $reservation)
    {
        $this->authorize('view', $reservation);
        return new ReservationResource($reservation->load(['user', 'resource', 'venue', 'approval']));
    }

    public function approve(Request $request, ReservationRequest $reservation)
    {
        $this->authorize('approve', $reservation);
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'reason' => 'nullable|string|max:1000',
        ]);
        $reservation = $this->reservationService->approveReservation($reservation, $request->user(), $request->status, $request->reason);
        return new ReservationResource($reservation);
    }

    public function cancel(Request $request, ReservationRequest $reservation)
    {
        $this->authorize('cancel', $reservation);
        $reservation = $this->reservationService->cancelReservation($reservation, $request->user());
        return new ReservationResource($reservation);
    }
}
