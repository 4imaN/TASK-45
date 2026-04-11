<?php
namespace App\Policies;

use App\Models\{User, EntitlementGrant};

class EntitlementGrantPolicy
{
    public function consume(User $user, EntitlementGrant $grant): bool
    {
        return $user->id === $grant->user_id || $user->isAdmin();
    }
}
