<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\SuperAdmin;

class PaymentPolicy
{
    public function viewAny($user): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function view($user, Payment $payment): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function create($user): bool
    {
        return false;
    }

    public function update($user, Payment $payment): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function delete($user, Payment $payment): bool
    {
        return false;
    }

    public function approve($user, Payment $payment): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function reject($user, Payment $payment): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function refund($user, Payment $payment): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }
}
