<?php
namespace App\Policies;

use App\Models\{User, Checkout, ClassModel};

class CheckoutPolicy
{
    public function checkin(User $user, Checkout $checkout): bool
    {
        if ($user->isAdmin()) return true;
        if (!$user->isTeacher() && !$user->isTA()) return false;
        return $this->hasMatchingScope($user, $checkout);
    }

    public function renew(User $user, Checkout $checkout): bool
    {
        if ($user->id === $checkout->checked_out_to) return true;
        if ($user->isAdmin()) return true;
        if (!$user->isTeacher() && !$user->isTA()) return false;
        return $this->hasMatchingScope($user, $checkout);
    }

    protected function hasMatchingScope(User $user, Checkout $checkout): bool
    {
        if ($user->permissionScopes()->where('scope_type', 'full')->exists()) {
            return true;
        }
        $loan = $checkout->loanRequest;
        if (!$loan) return false;

        if ($loan->class_id && $user->permissionScopes()->where('class_id', $loan->class_id)->exists()) {
            return true;
        }
        if ($loan->assignment_id && $user->permissionScopes()->where('assignment_id', $loan->assignment_id)->exists()) {
            return true;
        }
        // Course-level scope
        if ($loan->class_id) {
            $courseId = ClassModel::where('id', $loan->class_id)->value('course_id');
            if ($courseId && $user->permissionScopes()->where('course_id', $courseId)->exists()) {
                return true;
            }
        }
        return false;
    }
}
