<?php

namespace App\Filament\Widgets;

use App\Services\SuperAdminDashboardService;
use Filament\Widgets\ChartWidget;

class TopUsersUsageChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Top 10 Users by Card Usage';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $chart = SuperAdminDashboardService::getTopUsersUsage();

        return [
            'datasets' => [
                [
                    'label' => 'Total Cards Consumed',
                    'data' => $chart['data'],
                    'backgroundColor' => '#f59e0b',
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
