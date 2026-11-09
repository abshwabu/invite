<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Record an entry into the audit_logs table.
     */
    public static function log(
        string $action,
        ?Model $actor = null,
        ?Model $subject = null,
        array $meta = []
    ): AuditLog {
        $actor = $actor ?? auth('superadmin')->user() ?? auth('web')->user();

        return AuditLog::create([
            'actor_type' => $actor ? get_class($actor) : 'system',
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta,
            'ip' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'CLI/System',
            'created_at' => now(),
        ]);
    }
}
