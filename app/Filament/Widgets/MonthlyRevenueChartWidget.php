<?php

namespace App\Filament\Widgets;

use App\Services\SuperAdminDashboardService;
use Filament\Widgets\ChartWidget;

class MonthlyRevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue per Month (ETB)';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $chart = SuperAdminDashboardService::getMonthlyRevenue();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (ETB)',
                    'data' => $chart['data'],
                    'backgroundColor' => '#10b981',
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
