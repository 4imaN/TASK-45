<?php
namespace Tests\Support;

use App\Models\{User, Role, Department, Resource, InventoryLot, Course, ClassModel, Assignment, PermissionScope, MembershipTier, Membership};
use Illuminate\Support\Facades\Hash;

trait TestHelpers
{
    protected function createUserWithRole(string $roleName, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $role = Role::firstOrCreate(['name' => $roleName], ['description' => ucfirst($roleName)]);
        $user->roles()->attach($role->id);
        return $user->fresh();
    }

    protected function createAdmin(array $attrs = []): User { return $this->createUserWithRole('admin', $attrs); }
    protected function createTeacher(array $attrs = []): User { return $this->createUserWithRole('teacher', $attrs); }
    protected function createTA(array $attrs = []): User { return $this->createUserWithRole('ta', $attrs); }
    protected function createStudent(array $attrs = []): User { return $this->createUserWithRole('student', $attrs); }

    protected function createDepartment(array $attrs = []): Department
    {
        return Department::factory()->create($attrs);
    }

    protected function createResourceWithLot(array $resourceAttrs = [], array $lotAttrs = []): array
    {
        $dept = Department::first() ?? Department::create(['name' => 'Test Dept', 'code' => 'TST', 'description' => 'Test']);
        $resource = Resource::create(array_merge([
            'name' => 'Test Resource ' . uniqid(),
            'resource_type' => 'equipment',
            'category' => 'Computing',
            'department_id' => $dept->id,
            'status' => 'active',
        ], $resourceAttrs));

        $lot = InventoryLot::create(array_merge([
            'resource_id' => $resource->id,
            'department_id' => $resource->department_id,
            'lot_number' => 'LOT-' . uniqid(),
            'total_quantity' => 5,
            'serviceable_quantity' => 5,
            'condition' => 'good',
        ], $lotAttrs));

        return [$resource, $lot];
    }

    protected function createCourseStructure(): array
    {
        $dept = Department::first() ?? Department::create(['name' => 'Test Dept', 'code' => 'TST', 'description' => 'Test']);
        $course = Course::create(['code' => 'TST' . uniqid(), 'name' => 'Test Course', 'department_id' => $dept->id]);
        $class = ClassModel::create(['course_id' => $course->id, 'name' => 'Test Class', 'section' => 'A', 'semester' => 'Fall', 'year' => 2024]);
        $assignment = Assignment::create(['class_id' => $class->id, 'name' => 'Test Assignment']);
        return compact('dept', 'course', 'class', 'assignment');
    }

    protected function grantScope(User $user, array $attrs = []): PermissionScope
    {
        return PermissionScope::create(array_merge(['user_id' => $user->id, 'scope_type' => 'full'], $attrs));
    }

    protected function assignMembership(User $user, string $tierName = 'Basic'): Membership
    {
        $tier = MembershipTier::firstOrCreate(['name' => $tierName], [
            'description' => $tierName, 'max_active_loans' => 2, 'max_loan_days' => 7,
            'max_renewals' => 1, 'points_multiplier' => 1.00,
        ]);
        return Membership::create([
            'user_id' => $user->id, 'tier_id' => $tier->id, 'status' => 'active',
            'started_at' => now(), 'expires_at' => now()->addYear(),
        ]);
    }

    protected function loginAs(string $username, string $password): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/login', [
            'username' => $username, 'password' => $password,
        ], ['X-Idempotency-Key' => 'login-test-' . uniqid()]);
    }
}
