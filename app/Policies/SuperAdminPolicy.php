<?php

namespace App\Policies;

use App\Models\SuperAdmin;

class SuperAdminPolicy
{
    public function viewAny($user): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function view($user, SuperAdmin $superAdmin): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function create($user): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function update($user, SuperAdmin $superAdmin): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function delete($user, SuperAdmin $superAdmin): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }
}
