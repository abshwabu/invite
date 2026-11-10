<?php

namespace App\Policies;

use App\Models\AbuseReport;
use App\Models\SuperAdmin;

class AbuseReportPolicy
{
    public function viewAny($user): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function view($user, AbuseReport $report): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function create($user): bool
    {
        return false;
    }

    public function update($user, AbuseReport $report): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function dismiss($user, AbuseReport $report): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function suspendEvent($user, AbuseReport $report): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function suspendUser($user, AbuseReport $report): bool
    {
        return $user instanceof SuperAdmin && $user->isOwner();
    }

    public function delete($user, AbuseReport $report): bool
    {
        return false;
    }
}
