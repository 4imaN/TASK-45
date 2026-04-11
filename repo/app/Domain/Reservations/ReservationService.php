<?php
namespace App\Domain\Reservations;

use App\Domain\Availability\AvailabilityService;
use App\Models\{ReservationRequest, User, AuditLog, VenueTimeSlot};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationService
{
    public function __construct(protected AvailabilityService $availability) {}

    public function createReservation(User $user, array $data): ReservationRequest
    {
        return DB::transaction(function () use ($user, $data) {
            // Check allowlist/blacklist
            $resource = \App\Models\Resource::findOrFail($data['resource_id']);
            $accessCheck = $this->availability->checkUserResourceAccess($user, $resource);
            if (!$accessCheck['allowed']) {
                throw new \App\Common\Exceptions\BusinessRuleException($accessCheck['reason']);
            }

            // Validate resource type matches reservation type
            if ($data['reservation_type'] === 'venue' && $resource->resource_type !== 'venue') {
                throw new \App\Common\Exceptions\BusinessRuleException('Venue reservations can only be made for venue resources.');
            }
            if ($data['reservation_type'] === 'equipment' && $resource->resource_type !== 'equipment') {
                throw new \App\Common\Exceptions\BusinessRuleException('Equipment reservations can only be made for equipment resources.');
            }

            if ($data['reservation_type'] === 'venue') {
                // Verify the venue belongs to the claimed resource
                $venue = \App\Models\Venue::where('id', $data['venue_id'] ?? 0)
                    ->where('resource_id', $resource->id)
                    ->first();

                if (!$venue) {
                    throw new \App\Common\Exceptions\BusinessRuleException('Venue does not belong to this resource.');
                }

                $slot = VenueTimeSlot::where('id', $data['venue_time_slot_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$slot->is_available) {
                    throw new \App\Common\Exceptions\BusinessRuleException('Time slot is not available.');
                }

                if ($slot->reserved_by_reservation_id !== null) {
                    throw new \App\Common\Exceptions\BusinessRuleException(
                        'Time slot conflict: this slot is already reserved.'
                    );
                }

                // Verify slot belongs to the validated venue
                if ($slot->venue_id !== $venue->id) {
                    throw new \App\Common\Exceptions\BusinessRuleException('Time slot does not belong to the requested venue.');
                }

                // Derive dates from the slot
                $startDate = $slot->date;
                $endDate = $slot->date;
            } else {
                // Equipment reservation: check user limits
                $check = $this->availability->canUserBorrow($user);
                if (!$check['allowed']) {
                    throw new \App\Common\Exceptions\BusinessRuleException($check['reason']);
                }
                $startDate = $data['start_date'];
                $endDate = $data['end_date'];

                // Check for overlapping equipment reservations
                if ($this->availability->checkEquipmentReservationOverlap(
                    $data['resource_id'], $data['start_date'], $data['end_date']
                )) {
                    throw new \App\Common\Exceptions\BusinessRuleException(
                        'Equipment reservation conflict: an existing reservation overlaps this date range.'
                    );
                }
            }

            // Validate requester's claim to class/assignment
            $classId = $data['class_id'] ?? null;
            $assignmentId = $data['assignment_id'] ?? null;

            // If assignment_id is provided, resolve and enforce consistency
            if ($assignmentId) {
                $assignment = \App\Models\Assignment::find($assignmentId);
                if (!$assignment) {
                    throw new \App\Common\Exceptions\BusinessRuleException('The specified assignment does not exist.');
                }

                // Enforce internal consistency: assignment must belong to the claimed class
                if ($classId && $assignment->class_id !== (int) $classId) {
                    throw new \App\Common\Exceptions\BusinessRuleException('The assignment does not belong to the specified class.');
                }

                // Auto-resolve class_id from the assignment if not provided
                if (!$classId) {
                    $classId = $assignment->class_id;
                    $data['class_id'] = $classId;
                }

                // Student must have a scope covering this assignment's class or course
                $assignmentClassId = $assignment->class_id;
                $assignmentCourseId = \App\Models\ClassModel::where('id', $assignmentClassId)->value('course_id');

                $ok = $user->permissionScopes()->where(function ($q) use ($assignmentClassId, $assignmentCourseId) {
                    $q->where('scope_type', 'full')
                      ->orWhere('class_id', $assignmentClassId);
                    if ($assignmentCourseId) {
                        $q->orWhere('course_id', $assignmentCourseId);
                    }
                })->exists();

                if (!$ok) {
                    throw new \App\Common\Exceptions\BusinessRuleException('You do not have access to the specified assignment.');
                }
            }

            // Require class context if the student has class/course scopes
            // (ensures scoped staff can see and approve the request)
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
                $ok = $user->permissionScopes()->where(function ($q) use ($classId) {
                    $q->where('class_id', $classId)->orWhere('scope_type', 'full');
                })->exists();
                if (!$ok) {
                    $courseId = \App\Models\ClassModel::where('id', $classId)->value('course_id');
                    $ok = $courseId && $user->permissionScopes()->where('course_id', $courseId)->exists();
                }
                if (!$ok) {
                    throw new \App\Common\Exceptions\BusinessRuleException('You are not enrolled in the specified class.');
                }
            }

            $reservation = ReservationRequest::create([
                'user_id' => $user->id,
                'resource_id' => $data['resource_id'],
                'venue_id' => $data['venue_id'] ?? null,
                'venue_time_slot_id' => $data['venue_time_slot_id'] ?? null,
                'reservation_type' => $data['reservation_type'],
                'status' => 'pending',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'class_id' => $classId,
                'assignment_id' => $assignmentId,
            ]);

            // Mark the slot as occupied
            if ($data['reservation_type'] === 'venue') {
                $slot->update(['reserved_by_reservation_id' => $reservation->id]);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'reservation_created',
                'auditable_type' => ReservationRequest::class,
                'auditable_id' => $reservation->id,
            ]);

            return $reservation;
        });
    }

    public function approveReservation(ReservationRequest $reservation, User $approver, string $status, ?string $reason = null): ReservationRequest
    {
        return DB::transaction(function () use ($reservation, $approver, $status, $reason) {
            $reservation = ReservationRequest::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->status !== 'pending') {
                throw new \App\Common\Exceptions\BusinessRuleException('Can only act on pending reservations.');
            }

            if ($status === 'rejected' && $reservation->reservation_type === 'venue' && $reservation->venue_time_slot_id) {
                // Release the slot
                VenueTimeSlot::where('id', $reservation->venue_time_slot_id)
                    ->where('reserved_by_reservation_id', $reservation->id)
                    ->update(['reserved_by_reservation_id' => null]);
            }

            $reservation->update(['status' => $status]);

            $reservation->approval()->create([
                'approved_by' => $approver->id,
                'status' => $status,
                'reason' => $reason,
            ]);

            AuditLog::create([
                'user_id' => $approver->id,
                'action' => "reservation_{$status}",
                'auditable_type' => ReservationRequest::class,
                'auditable_id' => $reservation->id,
            ]);

            Log::channel('operations')->info("Reservation {$status}", [
                'reservation_id' => $reservation->id, 'approver_id' => $approver->id,
            ]);

            return $reservation->fresh();
        });
    }

    public function cancelReservation(ReservationRequest $reservation, User $user): ReservationRequest
    {
        return DB::transaction(function () use ($reservation, $user) {
            $reservation = ReservationRequest::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (!in_array($reservation->status, ['pending', 'approved'])) {
                throw new \App\Common\Exceptions\BusinessRuleException('Cannot cancel a reservation in this state.');
            }

            // Release the venue time slot if applicable
            if ($reservation->reservation_type === 'venue' && $reservation->venue_time_slot_id) {
                VenueTimeSlot::where('id', $reservation->venue_time_slot_id)
                    ->where('reserved_by_reservation_id', $reservation->id)
                    ->update(['reserved_by_reservation_id' => null]);
            }

            $reservation->update(['status' => 'cancelled']);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'reservation_cancelled',
                'auditable_type' => ReservationRequest::class,
                'auditable_id' => $reservation->id,
            ]);

            return $reservation->fresh();
        });
    }
}
