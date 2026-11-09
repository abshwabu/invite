<?php

namespace App\Filament\Resources\SuperAdminResource\Pages;

use App\Filament\Resources\SuperAdminResource;
use App\Services\AuditLogService;
use Filament\Resources\Pages\CreateRecord;

class CreateSuperAdmin extends CreateRecord
{
    protected static string $resource = SuperAdminResource::class;

    protected function afterCreate(): void
    {
        AuditLogService::log(
            'create_super_admin',
            null,
            $this->record,
            [
                'email' => $this->record->email,
                'role' => $this->record->role,
            ]
        );
    }
}
