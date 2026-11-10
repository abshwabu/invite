<?php

namespace App\Filament\Resources\AbuseReportResource\Pages;

use App\Filament\Resources\AbuseReportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAbuseReport extends ViewRecord
{
    protected static string $resource = AbuseReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
