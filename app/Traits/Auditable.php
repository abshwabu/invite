<?php

namespace App\Traits;

use App\Services\AuditLogService;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            if (auth('superadmin')->check() || auth('web')->check()) {
                AuditLogService::log('created', null, $model, [
                    'attributes' => $model->getAttributes(),
                ]);
            }
        });

        static::updated(function ($model) {
            if (auth('superadmin')->check() || auth('web')->check()) {
                $changes = $model->getChanges();
                // Exclude updated_at from being the only change logged
                if (count($changes) === 1 && isset($changes['updated_at'])) {
                    return;
                }

                AuditLogService::log('updated', null, $model, [
                    'changes' => $changes,
                    'original' => array_intersect_key($model->getOriginal(), $changes),
                ]);
            }
        });

        static::deleted(function ($model) {
            if (auth('superadmin')->check() || auth('web')->check()) {
                AuditLogService::log('deleted', null, $model, [
                    'attributes' => $model->getAttributes(),
                ]);
            }
        });
    }
}
