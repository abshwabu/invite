<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\SuperAdmin;

class AuditLogPolicy
{
    public function viewAny($user): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function view($user, AuditLog $auditLog): bool
    {
        return $user instanceof SuperAdmin;
    }

    public function create($user): bool
    {
        return false;
    }

    public function update($user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete($user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function deleteAny($user): bool
    {
        return false;
    }
}
