<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\SuperAdmin;

class CampaignPolicy
{
    public function viewAny($user): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function view($user, Campaign $campaign): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function create($user): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function update($user, Campaign $campaign): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function delete($user, Campaign $campaign): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }
}
