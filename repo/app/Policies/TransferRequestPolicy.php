<?php

namespace App\Policies;

use App\Models\{User, TransferRequest, Course, ClassModel};

class TransferRequestPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isAdmin()) return true;
        if (!$user->isTeacher() && !$user->isTA()) return false;
        if ($user->permissionScopes()->where('scope_type', 'full')->exists()) return true;
        return $this->hasAnyDepartmentAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function approve(User $user, TransferRequest $transfer): bool
    {
        if ($user->isAdmin()) return true;
        if (!$user->isTeacher() && !$user->isTA()) return false;
        $userDeptIds = $this->getUserDepartmentIds($user);
        return $userDeptIds->contains($transfer->from_department_id);
    }

    public function transition(User $user, TransferRequest $transfer): bool
    {
        return $this->approve($user, $transfer);
    }

    /**
     * Derive all department IDs a user has access to from their permission scopes.
     * Resolves: full -> all, department -> direct, course -> course.department_id,
     * class -> class.course.department_id
     */
    protected function getUserDepartmentIds(User $user): \Illuminate\Support\Collection
    {
        if ($user->permissionScopes()->where('scope_type', 'full')->exists()) {
            return \App\Models\Department::pluck('id');
        }

        $deptIds = $user->permissionScopes()->whereNotNull('department_id')->pluck('department_id');

        // Derive from course scopes
        $courseIds = $user->permissionScopes()->whereNotNull('course_id')->pluck('course_id');
        if ($courseIds->isNotEmpty()) {
            $courseDeptIds = Course::whereIn('id', $courseIds)->whereNotNull('department_id')->pluck('department_id');
            $deptIds = $deptIds->merge($courseDeptIds);
        }

        // Derive from class scopes
        $classIds = $user->permissionScopes()->whereNotNull('class_id')->pluck('class_id');
        if ($classIds->isNotEmpty()) {
            $classCourseIds = ClassModel::whereIn('id', $classIds)->pluck('course_id');
            $classDeptIds = Course::whereIn('id', $classCourseIds)->whereNotNull('department_id')->pluck('department_id');
            $deptIds = $deptIds->merge($classDeptIds);
        }

        return $deptIds->unique();
    }

    protected function hasAnyDepartmentAccess(User $user): bool
    {
        return $this->getUserDepartmentIds($user)->isNotEmpty();
    }
}
