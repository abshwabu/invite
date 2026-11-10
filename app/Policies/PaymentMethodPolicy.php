<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\SuperAdmin;

class PaymentMethodPolicy
{
    public function viewAny($user): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function view($user, PaymentMethod $method): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function create($user): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function update($user, PaymentMethod $method): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function delete($user, PaymentMethod $method): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }
}
