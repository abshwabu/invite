<?php

namespace App\Filament\Resources\PaymentMethodResource\Pages;

use App\Filament\Resources\PaymentMethodResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePaymentMethod extends CreateRecord
{
    protected static string $resource = PaymentMethodResource::class;

    protected function afterCreate(): void
    {
        if ($admin = Auth::guard('superadmin')->user()) {
            AuditLog::create([
                'super_admin_id' => $admin->id,
                'action' => 'payment_method.created',
                'target_type' => PaymentMethod::class,
                'target_id' => $this->record->id,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System',
                'meta' => ['label' => $this->record->label],
            ]);
        }
    }
}
