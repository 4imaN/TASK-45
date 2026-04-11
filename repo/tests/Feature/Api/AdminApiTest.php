<?php
namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Hold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class AdminApiTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_assign_scope(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $teacher = $this->createTeacher();
        $structure = $this->createCourseStructure();

        $response = $this->actingAs($admin)->postJson('/api/admin/scopes', [
            'user_id' => $teacher->id, 'scope_type' => 'course', 'course_id' => $structure['course']->id,
        ], ['X-Idempotency-Key' => 'test-assign-scope-1']);
        $response->assertOk();
        $this->assertDatabaseHas('permission_scopes', ['user_id' => $teacher->id]);
    }

    public function test_release_hold(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();
        $hold = Hold::create([
            'user_id' => $student->id, 'hold_type' => 'manual', 'reason' => 'Test',
            'status' => 'active', 'triggered_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson("/api/admin/holds/{$hold->id}/release", ['reason' => 'Reviewed and cleared'], ['X-Idempotency-Key' => 'test-release-hold-1']);
        $response->assertOk();
        $this->assertEquals('released', $hold->fresh()->status);
    }

    public function test_add_to_blacklist(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent();

        $response = $this->actingAs($admin)->postJson('/api/admin/blacklists', [
            'scope_type' => 'global', 'scope_id' => 0, 'user_id' => $student->id, 'reason' => 'Policy violation',
        ], ['X-Idempotency-Key' => 'test-add-blacklist-1']);
        $response->assertOk();
        $this->assertDatabaseHas('blacklists', ['user_id' => $student->id]);
    }

    public function test_reveal_field_logs_audit(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $student = $this->createStudent(['phone' => '555-1234']);

        $response = $this->actingAs($admin)->postJson('/api/admin/reveal-field', [
            'model_type' => 'App\\Models\\User', 'model_id' => $student->id,
            'fields' => ['phone'], 'reason' => 'Identity verification',
        ], ['X-Idempotency-Key' => 'test-reveal-field-1']);
        $response->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'sensitive_field_revealed', 'user_id' => $admin->id]);
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $student = $this->createStudent();
        $this->actingAs($student)->getJson('/api/admin/scopes')->assertForbidden();
    }

    public function test_view_audit_logs(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $response = $this->actingAs($admin)->getJson('/api/admin/audit-logs');
        $response->assertOk();
    }
}
