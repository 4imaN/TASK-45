<?php

namespace App\Policies;

use App\Models\User;
use App\Models\LoanRequest;
use App\Models\ClassModel;

class LoanRequestPolicy
{
    public function view(User $user, LoanRequest $loan): bool
    {
        if ($user->id === $loan->user_id) return true;
        if ($user->isAdmin()) return true;
        if (!$user->isTeacher() && !$user->isTA()) return false;
        return $this->hasMatchingScope($user, $loan);
    }

    public function approve(User $user, LoanRequest $loan): bool
    {
        if ($user->isAdmin()) return true;
        if (!$user->isTeacher() && !$user->isTA()) return false;
        return $this->hasMatchingScope($user, $loan);
    }

    public function checkout(User $user, LoanRequest $loan): bool
    {
        if ($user->isAdmin()) return true;
        if (!$user->isTeacher() && !$user->isTA()) return false;
        return $this->hasMatchingScope($user, $loan);
    }

    /**
     * Check if user has a permission scope that covers this loan.
     * Matches on: full scope, direct class_id, direct assignment_id,
     * or course_id that contains the loan's class.
     */
    protected function hasMatchingScope(User $user, LoanRequest $loan): bool
    {
        // Full scope covers everything
        if ($user->permissionScopes()->where('scope_type', 'full')->exists()) {
            return true;
        }

        // Direct class or assignment match
        if ($loan->class_id && $user->permissionScopes()->where('class_id', $loan->class_id)->exists()) {
            return true;
        }
        if ($loan->assignment_id && $user->permissionScopes()->where('assignment_id', $loan->assignment_id)->exists()) {
            return true;
        }

        // Course-level scope: check if the loan's class belongs to a course the user is scoped to
        if ($loan->class_id) {
            $courseId = ClassModel::where('id', $loan->class_id)->value('course_id');
            if ($courseId && $user->permissionScopes()->where('course_id', $courseId)->exists()) {
                return true;
            }
        }

        return false;
    }
}
