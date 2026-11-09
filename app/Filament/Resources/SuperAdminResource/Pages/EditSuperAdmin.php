<?php

namespace App\Filament\Resources\SuperAdminResource\Pages;

use App\Filament\Resources\SuperAdminResource;
use App\Services\AuditLogService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSuperAdmin extends EditRecord
{
    protected static string $resource = SuperAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        AuditLogService::log(
            'update_super_admin',
            null,
            $this->record,
            [
                'email' => $this->record->email,
                'role' => $this->record->role,
            ]
        );
    }
}
