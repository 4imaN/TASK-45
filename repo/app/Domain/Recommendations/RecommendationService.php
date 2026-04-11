<?php
namespace App\Domain\Recommendations;

use App\Models\{User, Resource, RecommendationBatch, RuleTrace, ManualOverride, LoanRequest, AuditLog};
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    public function generateRecommendations(User $user, ?int $classId = null): RecommendationBatch
    {
        return DB::transaction(function () use ($user, $classId) {
            $batch = RecommendationBatch::create([
                'user_id' => $user->id,
                'context_type' => 'class_recommendation',
                'context_id' => $classId,
                'generated_at' => now(),
                'parameters' => ['class_id' => $classId],
            ]);

            $resources = $this->rankResources($user, $classId);

            foreach ($resources as $rank => $item) {
                RuleTrace::create([
                    'batch_id' => $batch->id,
                    'resource_id' => $item['resource']->id,
                    'rank' => $rank + 1,
                    'score' => $item['score'],
                    'contributing_factors' => $item['factors'],
                    'applied_filters' => $item['filters'],
                    'excluded' => $item['excluded'],
                    'exclusion_reason' => $item['exclusion_reason'] ?? null,
                ]);
            }

            return $batch;
        });
    }

    protected function rankResources(User $user, ?int $classId): array
    {
        // Include all resources so filter exclusions (delisted, sensitive, out-of-stock) are
        // persisted in rule traces for explainability. The scoring loop marks them as excluded.
        $query = Resource::query();

        if ($classId) {
            // Get resources from same department as the class's course
            $class = \App\Models\ClassModel::with('course')->find($classId);
            if ($class && $class->course && $class->course->department_id) {
                $query->where('department_id', $class->course->department_id);
            }
        }

        $resources = $query->with('inventoryLots')->get();
        $scored = [];

        foreach ($resources as $resource) {
            $score = 0;
            $factors = [];
            $filters = [];
            $excluded = false;
            $exclusionReason = null;

            // Factor 1: Similar course enrollments
            $courseMatch = $this->courseEnrollmentScore($user, $resource);
            $score += $courseMatch;
            if ($courseMatch > 0) {
                $factors[] = ['factor' => 'course_enrollment_match', 'weight' => $courseMatch];
            }

            // Factor 2: Past checkout history
            $historyScore = $this->checkoutHistoryScore($user, $resource);
            $score += $historyScore;
            if ($historyScore > 0) {
                $factors[] = ['factor' => 'past_checkout_similarity', 'weight' => $historyScore];
            }

            // Factor 3: Availability
            $availService = app(\App\Domain\Availability\AvailabilityService::class);
            $available = $availService->getAvailableQuantity($resource);
            if ($available <= 0) {
                $excluded = true;
                $exclusionReason = 'out_of_stock';
                $filters[] = 'excluded_out_of_stock';
            }

            // Filter: delisted
            if ($resource->status === 'delisted') {
                $excluded = true;
                $exclusionReason = 'delisted';
                $filters[] = 'excluded_delisted';
            }

            // Filter: sensitive
            if ($resource->is_sensitive) {
                $excluded = true;
                $exclusionReason = 'sensitive';
                $filters[] = 'excluded_sensitive';
            }

            $scored[] = [
                'resource' => $resource,
                'score' => $score,
                'factors' => $factors,
                'filters' => $filters,
                'excluded' => $excluded,
                'exclusion_reason' => $exclusionReason,
            ];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    protected function courseEnrollmentScore(User $user, Resource $resource): float
    {
        // Enrollment signal: in this offline-first system, permission_scopes serve as the
        // enrollment proxy. A user scoped to a course or class is treated as enrolled.
        // This is an explicit design decision — the system has no separate enrollment table
        // because authorization scopes already represent the user-to-course relationship.
        $userCourseIds = $user->permissionScopes()->whereNotNull('course_id')->pluck('course_id');
        $userClassCourseIds = \App\Models\ClassModel::whereIn('id',
            $user->permissionScopes()->whereNotNull('class_id')->pluck('class_id')
        )->pluck('course_id');

        $allUserCourseIds = $userCourseIds->merge($userClassCourseIds)->unique();
        $resourceDeptCourses = \App\Models\Course::where('department_id', $resource->department_id)->pluck('id');

        $overlap = $allUserCourseIds->intersect($resourceDeptCourses)->count();
        return $overlap * 10.0;
    }

    protected function checkoutHistoryScore(User $user, Resource $resource): float
    {
        // Check past checkouts for similar category
        $pastCategories = LoanRequest::where('user_id', $user->id)
            ->whereIn('status', ['checked_out', 'returned'])
            ->with('resource')
            ->get()
            ->pluck('resource.category')
            ->unique();

        return $pastCategories->contains($resource->category) ? 5.0 : 0.0;
    }

    public function createOverride(User $admin, int $batchId, int $resourceId, string $overrideType, string $reason, ?array $previousState = null, ?array $newState = null): ManualOverride
    {
        $override = ManualOverride::create([
            'batch_id' => $batchId,
            'resource_id' => $resourceId,
            'overridden_by' => $admin->id,
            'override_type' => $overrideType,
            'reason' => $reason,
            'previous_state' => $previousState,
            'new_state' => $newState,
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'recommendation_override',
            'auditable_type' => ManualOverride::class,
            'auditable_id' => $override->id,
            'context' => ['reason' => $reason],
        ]);

        return $override;
    }
}
