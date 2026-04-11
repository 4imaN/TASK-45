<?php

namespace App\Policies;

use App\Models\{User, FileAsset, LoanRequest, ReservationRequest, Resource, ClassModel};

class FileAssetPolicy
{
    public function download(User $user, FileAsset $file): bool
    {
        // Owner can always download their own files
        if ($user->id === $file->uploaded_by) return true;
        // Admin can download any file
        if ($user->isAdmin()) return true;

        if (!$user->isTeacher() && !$user->isTA()) return false;

        // Loan-attached: check class/assignment/course scope
        if ($file->attachable_type === LoanRequest::class) {
            $loan = LoanRequest::find($file->attachable_id);
            if ($loan) return $this->hasLoanScope($user, $loan);
        }

        // Reservation-attached: check class/assignment/course scope
        if ($file->attachable_type === ReservationRequest::class) {
            $reservation = ReservationRequest::find($file->attachable_id);
            if ($reservation) {
                if ($user->id === $reservation->user_id) return true;
                return $this->hasReservationScope($user, $reservation);
            }
        }

        // Resource-attached: check department scope (matching upload authorization)
        if ($file->attachable_type === Resource::class) {
            $resource = Resource::find($file->attachable_id);
            if ($resource) {
                return $user->permissionScopes()->where(function ($q) use ($resource) {
                    $q->where('scope_type', 'full')
                      ->orWhere('department_id', $resource->department_id);
                })->exists();
            }
        }

        // Unattached files: full scope only
        return $user->permissionScopes()->where('scope_type', 'full')->exists();
    }

    protected function hasLoanScope(User $user, LoanRequest $loan): bool
    {
        if ($user->permissionScopes()->where('scope_type', 'full')->exists()) return true;
        if ($loan->class_id && $user->permissionScopes()->where('class_id', $loan->class_id)->exists()) return true;
        if ($loan->assignment_id && $user->permissionScopes()->where('assignment_id', $loan->assignment_id)->exists()) return true;
        if ($loan->class_id) {
            $courseId = ClassModel::where('id', $loan->class_id)->value('course_id');
            if ($courseId && $user->permissionScopes()->where('course_id', $courseId)->exists()) return true;
        }
        return false;
    }

    protected function hasReservationScope(User $user, ReservationRequest $reservation): bool
    {
        if ($user->permissionScopes()->where('scope_type', 'full')->exists()) return true;
        if ($reservation->class_id && $user->permissionScopes()->where('class_id', $reservation->class_id)->exists()) return true;
        if ($reservation->assignment_id && $user->permissionScopes()->where('assignment_id', $reservation->assignment_id)->exists()) return true;
        if ($reservation->class_id) {
            $courseId = ClassModel::where('id', $reservation->class_id)->value('course_id');
            if ($courseId && $user->permissionScopes()->where('course_id', $courseId)->exists()) return true;
        }
        return false;
    }
}
