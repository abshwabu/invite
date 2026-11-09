<?php

namespace App\Filament\Widgets;

use App\Services\SuperAdminDashboardService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class RegistrationsChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'User Registrations per Day';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $period = $this->filters['period'] ?? '30d';
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $chart = SuperAdminDashboardService::getRegistrationsChartData($period, $startDate, $endDate);

        return [
            'datasets' => [
                [
                    'label' => 'New Users',
                    'data' => $chart['data'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                    'fill' => true,
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
