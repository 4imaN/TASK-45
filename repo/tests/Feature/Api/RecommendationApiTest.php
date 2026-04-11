<?php
namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\{PermissionScope, Course, Department, Resource, InventoryLot, RuleTrace};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class RecommendationApiTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_get_recommendations(): void
    {
        $student = $this->createStudent();
        $this->createResourceWithLot(['name' => 'Rec Item 1']);
        $this->createResourceWithLot(['name' => 'Rec Item 2']);

        $response = $this->actingAs($student)->postJson('/api/recommendations/for-class', [], ['X-Idempotency-Key' => 'rec-' . uniqid()]);
        $response->assertOk()->assertJsonStructure(['batch_id', 'recommendations']);
    }

    public function test_recommendation_excludes_sensitive(): void
    {
        $student = $this->createStudent();
        $this->createResourceWithLot(['name' => 'Normal', 'is_sensitive' => false]);
        $this->createResourceWithLot(['name' => 'Sensitive', 'is_sensitive' => true]);

        $response = $this->actingAs($student)->postJson('/api/recommendations/for-class', [], ['X-Idempotency-Key' => 'rec-' . uniqid()]);
        $recs = collect($response->json('recommendations'));
        $this->assertFalse($recs->contains(fn($r) => $r['resource']['name'] === 'Sensitive'));
    }

    public function test_course_enrollment_boosts_recommendation_score(): void
    {
        $student = $this->createStudent();

        // Create a department with a course
        $dept = Department::create(['name' => 'RecDept', 'code' => 'RD', 'description' => 'T']);
        $course = Course::create(['code' => 'REC101', 'name' => 'Rec Course', 'department_id' => $dept->id]);

        // Enroll student in that course via permission scope
        PermissionScope::create([
            'user_id' => $student->id, 'course_id' => $course->id, 'scope_type' => 'course',
        ]);

        // Resource in the same department (should rank higher)
        $matchResource = Resource::create([
            'name' => 'Match Resource', 'resource_type' => 'equipment', 'category' => 'Computing',
            'department_id' => $dept->id, 'status' => 'active',
        ]);
        InventoryLot::create([
            'resource_id' => $matchResource->id, 'department_id' => $dept->id,
            'lot_number' => 'LOT-REC-1', 'total_quantity' => 5, 'serviceable_quantity' => 5, 'condition' => 'good',
        ]);

        // Resource in a different department (should rank lower)
        $otherDept = Department::create(['name' => 'OtherDept', 'code' => 'OD', 'description' => 'O']);
        $otherResource = Resource::create([
            'name' => 'Other Resource', 'resource_type' => 'equipment', 'category' => 'Computing',
            'department_id' => $otherDept->id, 'status' => 'active',
        ]);
        InventoryLot::create([
            'resource_id' => $otherResource->id, 'department_id' => $otherDept->id,
            'lot_number' => 'LOT-REC-2', 'total_quantity' => 5, 'serviceable_quantity' => 5, 'condition' => 'good',
        ]);

        $response = $this->actingAs($student)->postJson('/api/recommendations/for-class', [], ['X-Idempotency-Key' => 'rec-' . uniqid()]);
        $response->assertOk();

        $recs = collect($response->json('recommendations'));
        $this->assertNotEmpty($recs);

        // The matching department resource should appear with higher score
        $matchRec = $recs->firstWhere('resource.name', 'Match Resource');
        $otherRec = $recs->firstWhere('resource.name', 'Other Resource');

        // The enrolled-course resource should have enrollment factor in its score
        if ($matchRec && $otherRec) {
            $this->assertGreaterThan($otherRec['score'], $matchRec['score']);
        }

        // Verify the rule trace records the enrollment factor
        $batchId = $response->json('batch_id');
        $trace = RuleTrace::where('batch_id', $batchId)
            ->where('resource_id', $matchResource->id)
            ->first();
        $this->assertNotNull($trace);
        $factors = collect($trace->contributing_factors);
        $this->assertTrue($factors->contains(fn($f) => $f['factor'] === 'course_enrollment_match'));
    }

    public function test_manual_override_requires_reason(): void
    {
        $admin = $this->createAdmin();
        $this->grantScope($admin);
        $this->createResourceWithLot();
        $batchResponse = $this->actingAs($admin)->postJson('/api/recommendations/for-class', [], ['X-Idempotency-Key' => 'rec-' . uniqid()]);
        $batchId = $batchResponse->json('batch_id');

        $response = $this->actingAs($admin)->postJson('/api/recommendations/override', [
            'batch_id' => $batchId, 'resource_id' => 1, 'override_type' => 'exclude', 'reason' => 'Short',
        ], ['X-Idempotency-Key' => 'test-manual-override-1']);
        // Reason must be at least 10 chars — "Short" is only 5 chars
        $response->assertUnprocessable();
    }
}
