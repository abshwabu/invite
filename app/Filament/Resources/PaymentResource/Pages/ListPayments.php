<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function getDefaultActiveTab(): string | int | null
    {
        return 'awaiting_verification';
    }

    public function getTabs(): array
    {
        return [
            'awaiting_verification' => Tab::make('Awaiting Verification')
                ->badge(fn () => $this->getModel()::whereIn('status', ['submitted', 'needs_info'])->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('status', ['submitted', 'needs_info'])
                    ->orderByRaw('CASE WHEN submitted_at IS NULL THEN 1 ELSE 0 END, submitted_at ASC')
                ),

            'all' => Tab::make('All Payments')
                ->modifyQueryUsing(fn (Builder $query) => $query->latest('id')),

            'approved' => Tab::make('Approved')
                ->badge(fn () => $this->getModel()::where('status', 'approved')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved')->latest('reviewed_at')),

            'rejected' => Tab::make('Rejected')
                ->badge(fn () => $this->getModel()::where('status', 'rejected')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected')->latest('reviewed_at')),

            'abandoned' => Tab::make('Abandoned')
                ->badge(fn () => $this->getModel()::where('status', 'abandoned')->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'abandoned')->latest('updated_at')),
        ];
    }
}
