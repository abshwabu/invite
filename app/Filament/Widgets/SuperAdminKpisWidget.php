<?php

namespace App\Filament\Widgets;

use App\Services\SuperAdminDashboardService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SuperAdminKpisWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $period = $this->filters['period'] ?? '30d';
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $kpis = SuperAdminDashboardService::getKpiStats($period, $startDate, $endDate);

        return [
            Stat::make('Total Users', number_format($kpis['total_users']))
                ->description("Today: +{$kpis['registrations_today']} | 7d: +{$kpis['registrations_7d']} | 30d: +{$kpis['registrations_30d']}")
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Active Users (30d)', number_format($kpis['active_users_30d']))
                ->description('Logged in last 30 days')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('success'),

            Stat::make('Total Events', number_format($kpis['total_events']))
                ->description("Period: {$kpis['events_period']} created")
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),

            Stat::make('Total Guests', number_format($kpis['total_guests']))
                ->description("Period: {$kpis['guests_period']} added")
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Cards Sent', number_format($kpis['cards_sent_total']))
                ->description("Period: " . number_format($kpis['cards_sent_period']))
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('primary'),

            Stat::make('Delivery Success', "{$kpis['delivery_success_rate']}%")
                ->description('Delivered / Sent ratio')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($kpis['delivery_success_rate'] >= 95 ? 'success' : 'warning'),

            Stat::make('Total Check-ins', number_format($kpis['checkins_count']))
                ->description('Guests admitted across events')
                ->descriptionIcon('heroicon-m-qr-code')
                ->color('success'),

            Stat::make('Total Revenue', number_format($kpis['revenue_total'], 2) . ' ETB')
                ->description("Period: " . number_format($kpis['revenue_period'], 2) . ' ETB')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Low Quota Users', number_format($kpis['users_near_exhaustion']))
                ->description('≤ 10% cards remaining')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($kpis['users_near_exhaustion'] > 0 ? 'warning' : 'gray'),

            Stat::make('Provider Cost vs Rev', number_format($kpis['estimated_cost_total'], 2) . ' ETB')
                ->description("Margin: " . number_format($kpis['net_margin_total'], 2) . " ETB (@{$kpis['cost_per_sms']}/SMS)")
                ->descriptionIcon('heroicon-m-scale')
                ->color($kpis['net_margin_total'] >= 0 ? 'success' : 'danger'),
        ];
    }
}
