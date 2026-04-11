<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Recommendations\RecommendationService;
use App\Models\RecommendationBatch;
use App\Models\RuleTrace;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(protected RecommendationService $recommendationService) {}

    public function forClass(Request $request)
    {
        $request->validate(['class_id' => 'nullable|exists:classes,id']);

        if ($request->class_id) {
            $user = $request->user();
            $classId = $request->class_id;
            $hasScope = $user->permissionScopes()->where(function ($q) use ($classId) {
                $q->where('class_id', $classId)->orWhere('scope_type', 'full');
            })->exists();
            if (!$hasScope) {
                $courseId = \App\Models\ClassModel::where('id', $classId)->value('course_id');
                $hasScope = $courseId && $user->permissionScopes()->where('course_id', $courseId)->exists();
            }
            if (!$hasScope) {
                return response()->json(['error' => 'You are not enrolled in this class.'], 403);
            }
        }

        $batch = $this->recommendationService->generateRecommendations($request->user(), $request->class_id);
        $traces = RuleTrace::where('batch_id', $batch->id)->where('excluded', false)
            ->orderBy('rank')->with('resource')->limit(10)->get();

        $maxScore = $traces->max('score') ?: 1;

        return response()->json([
            'batch_id' => $batch->id,
            'recommendations' => $traces->map(fn($t) => [
                'resource' => $t->resource,
                'rank' => $t->rank,
                'score' => $maxScore > 0 ? round($t->score / $maxScore, 2) : 0,
                'factors' => collect($t->contributing_factors)->map(fn($f) => [
                    'type' => $f['factor'] ?? $f['type'] ?? 'unknown',
                    'label' => $this->factorLabel($f['factor'] ?? $f['type'] ?? ''),
                    'score' => $maxScore > 0 ? round(($f['weight'] ?? $f['score'] ?? 0) / $maxScore, 2) : 0,
                ])->values(),
            ]),
        ]);
    }

    public function batchTrace(RecommendationBatch $batch)
    {
        $this->authorize('view', $batch);
        return response()->json([
            'batch' => $batch,
            'traces' => RuleTrace::where('batch_id', $batch->id)->orderBy('rank')->get(),
        ]);
    }

    public function override(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:recommendation_batches,id',
            'resource_id' => 'required|exists:resources,id',
            'override_type' => 'required|string',
            'reason' => 'required|string|min:10',
        ]);

        $batch = RecommendationBatch::findOrFail($request->batch_id);
        $this->authorize('override', $batch);

        $override = $this->recommendationService->createOverride(
            $request->user(), $request->batch_id, $request->resource_id,
            $request->override_type, $request->reason
        );
        return response()->json(['message' => 'Override recorded.', 'id' => $override->id]);
    }

    protected function factorLabel(string $factor): string
    {
        return match ($factor) {
            'course_enrollment_match' => 'Course match',
            'past_checkout_similarity' => 'Previously borrowed',
            default => str_replace('_', ' ', ucfirst($factor)),
        };
    }
}
