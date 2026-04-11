<?php

namespace App\Policies;

use App\Models\{User, ReservationRequest, ClassModel};

class ReservationRequestPolicy
{
    public function view(User $user, ReservationRequest $reservation): bool
    {
        if ($user->id === $reservation->user_id) return true;
        if ($user->isAdmin()) return true;
        if (!$user->isTeacher() && !$user->isTA()) return false;
        return $this->hasMatchingScope($user, $reservation);
    }

    public function approve(User $user, ReservationRequest $reservation): bool
    {
        if ($user->isAdmin()) return true;
        if (!$user->isTeacher() && !$user->isTA()) return false;
        return $this->hasMatchingScope($user, $reservation);
    }

    public function cancel(User $user, ReservationRequest $reservation): bool
    {
        if ($user->id === $reservation->user_id) return true;
        return $user->isAdmin();
    }

    protected function hasMatchingScope(User $user, ReservationRequest $reservation): bool
    {
        if ($user->permissionScopes()->where('scope_type', 'full')->exists()) {
            return true;
        }
        if ($reservation->class_id && $user->permissionScopes()->where('class_id', $reservation->class_id)->exists()) {
            return true;
        }
        if ($reservation->assignment_id) {
            // Only match if the teacher's assignment scope covers the actual assignment
            // AND the assignment belongs to the reservation's class (prevents forged cross-class scoping)
            $assignment = \App\Models\Assignment::find($reservation->assignment_id);
            if ($assignment) {
                $assignmentClassId = $assignment->class_id;
                // The assignment must belong to the reservation's class for consistency
                if ($reservation->class_id && $assignmentClassId !== $reservation->class_id) {
                    return false;
                }
                if ($user->permissionScopes()->where('assignment_id', $reservation->assignment_id)->exists()) {
                    return true;
                }
            }
        }
        // Course-level scope
        if ($reservation->class_id) {
            $courseId = ClassModel::where('id', $reservation->class_id)->value('course_id');
            if ($courseId && $user->permissionScopes()->where('course_id', $courseId)->exists()) {
                return true;
            }
        }
        return false;
    }
}
