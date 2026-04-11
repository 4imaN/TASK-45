<?php

namespace App\Policies;

use App\Models\{User, RecommendationBatch};

class RecommendationBatchPolicy
{
    public function view(User $user, RecommendationBatch $batch): bool
    {
        if ($user->id === $batch->user_id) return true;
        return $user->isAdmin();
    }

    /**
     * Override requires admin, or a teacher acting on their own batch.
     */
    public function override(User $user, RecommendationBatch $batch): bool
    {
        if ($user->isAdmin()) return true;
        // Teachers can only override their own batches
        if ($user->isTeacher() && $user->id === $batch->user_id) return true;
        return false;
    }
}
