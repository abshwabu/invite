<?php

namespace App\Filament\Widgets;

use App\Services\SuperAdminDashboardService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CardsSentChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Cards Sent per Day by Channel';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $period = $this->filters['period'] ?? '30d';
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $chart = SuperAdminDashboardService::getCardsSentChartData($period, $startDate, $endDate);

        return [
            'datasets' => [
                [
                    'label' => 'SMS Deliveries',
                    'data' => $chart['sms'],
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label' => 'Email Deliveries',
                    'data' => $chart['email'],
                    'backgroundColor' => '#6366f1',
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
