<?php

namespace App\Policies;

use App\Models\SuperAdmin;
use App\Models\User;

class UserPolicy
{
    public function viewAny($user): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function view($user, User $model): bool
    {
        return $user instanceof SuperAdmin || ($user instanceof User && $user->id === $model->id);
    }

    public function create($user): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function update($user, User $model): bool
    {
        return ($user instanceof SuperAdmin && $user->isOwner())
            || ($user instanceof User && $user->id === $model->id);
    }

    public function delete($user, User $model): bool
    {
        return ($user instanceof SuperAdmin && $user->isOwner())
            || ($user instanceof User && $user->id === $model->id);
    }
}
