<?php

namespace App\Filament\Widgets;

use App\Services\SuperAdminDashboardService;
use Filament\Widgets\ChartWidget;

class DeliveryFailuresProviderChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Delivery Status & Failures Breakdown';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $chart = SuperAdminDashboardService::getDeliveryFailuresData();

        return [
            'datasets' => [
                [
                    'label' => 'Deliveries',
                    'data' => $chart['data'],
                    'backgroundColor' => [
                        '#10b981', // Delivered
                        '#3b82f6', // Sent
                        '#ef4444', // Failed
                        '#f59e0b', // Queued
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
