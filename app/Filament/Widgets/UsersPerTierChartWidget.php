<?php

namespace App\Filament\Widgets;

use App\Services\SuperAdminDashboardService;
use Filament\Widgets\ChartWidget;

class UsersPerTierChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Active Users per Subscription Tier';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $chart = SuperAdminDashboardService::getUsersPerTierData();

        return [
            'datasets' => [
                [
                    'label' => 'Subscribers',
                    'data' => $chart['data'],
                    'backgroundColor' => [
                        '#6366f1',
                        '#10b981',
                        '#f59e0b',
                        '#ec4899',
                        '#8b5cf6',
                        '#64748b',
                    ],
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
