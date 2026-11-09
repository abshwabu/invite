<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Campaigns'),
            'pending_review' => Tab::make('Pending Review Queue')
                ->badge(fn () => \App\Models\Campaign::where('status', 'pending_review')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending_review')),
            'sent' => Tab::make('Sent')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'sent')),
            'failed' => Tab::make('Failed / Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['failed', 'rejected'])),
        ];
    }
}
