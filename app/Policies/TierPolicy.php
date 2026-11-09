<?php

namespace App\Policies;

use App\Models\SuperAdmin;
use App\Models\Tier;

class TierPolicy
{
    public function viewAny($user): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function view($user, Tier $tier): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function create($user): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function update($user, Tier $tier): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function delete($user, Tier $tier): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }
}
