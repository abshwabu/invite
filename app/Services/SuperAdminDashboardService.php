<?php

namespace App\Services;

use App\Models\CheckinLog;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\Tier;
use App\Models\UsageLedger;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SuperAdminDashboardService
{
    /**
     * Resolve date range from filter array.
     */
    public static function resolveDateRange(?string $period = '30d', ?string $startDate = null, ?string $endDate = null): array
    {
        if ($startDate && $endDate) {
            return [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ];
        }

        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            '7d' => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
            'all' => [Carbon::create(2020, 1, 1), now()->endOfDay()],
            default => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
        };
    }

    /**
     * Get aggregated KPIs with 60-second caching.
     */
    public static function getKpiStats(?string $period = '30d', ?string $startDate = null, ?string $endDate = null): array
    {
        [$from, $to] = static::resolveDateRange($period, $startDate, $endDate);
        $cacheKey = 'superadmin_kpis_' . md5($from->toIso8601String() . $to->toIso8601String());

        return Cache::remember($cacheKey, 60, function () use ($from, $to) {
            $totalUsers = User::count();
            $registrationsToday = User::whereDate('created_at', today())->count();
            $registrations7d = User::where('created_at', '>=', now()->subDays(7))->count();
            $registrations30d = User::where('created_at', '>=', now()->subDays(30))->count();
            $registrationsPeriod = User::whereBetween('created_at', [$from, $to])->count();

            $activeUsers30d = User::where('last_login_at', '>=', now()->subDays(30))->count();
            $totalEvents = Event::count();
            $eventsPeriod = Event::whereBetween('created_at', [$from, $to])->count();
            $totalGuests = Guest::count();
            $guestsPeriod = Guest::whereBetween('created_at', [$from, $to])->count();

            $cardsSentTotal = Delivery::whereIn('status', ['sent', 'delivered'])->count();
            $cardsSentPeriod = Delivery::whereIn('status', ['sent', 'delivered'])
                ->whereBetween('created_at', [$from, $to])
                ->count();

            $totalDeliveries = Delivery::whereIn('status', ['sent', 'delivered', 'failed'])->count();
            $successfulDeliveries = Delivery::whereIn('status', ['sent', 'delivered'])->count();
            $deliverySuccessRate = $totalDeliveries > 0
                ? round(($successfulDeliveries / $totalDeliveries) * 100, 1)
                : 100.0;

            $checkinsCount = CheckinLog::count();
            if ($checkinsCount === 0) {
                $checkinsCount = Guest::whereNotNull('checked_in_at')->count();
            }

            // Step 14A: Count only approved/paid payments using verified amount, strictly excluding refunded/unapproved
            $revenueTotal = (float) Payment::whereIn('status', ['approved', 'paid'])
                ->selectRaw('COALESCE(SUM(COALESCE(amount_verified, amount_expected, amount)), 0) as total')
                ->value('total');

            $revenuePeriod = (float) Payment::whereIn('status', ['approved', 'paid'])
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('reviewed_at', [$from, $to])
                        ->orWhere(function ($sub) use ($from, $to) {
                            $sub->whereNull('reviewed_at')
                                ->whereBetween('paid_at', [$from, $to]);
                        });
                })
                ->selectRaw('COALESCE(SUM(COALESCE(amount_verified, amount_expected, amount)), 0) as total')
                ->value('total');

            // Payments awaiting verification (submitted + needs_info)
            $paymentsAwaitingVerification = Payment::whereIn('status', ['submitted', 'needs_info'])->count();

            // Average verification time (hours) for approved payments
            $avgVerificationSeconds = Payment::where('status', 'approved')
                ->whereNotNull('submitted_at')
                ->whereNotNull('reviewed_at')
                ->selectRaw('AVG(strftime("%s", reviewed_at) - strftime("%s", submitted_at)) as avg_time')
                ->value('avg_time');
            $avgVerificationHours = $avgVerificationSeconds ? round($avgVerificationSeconds / 3600, 1) : 0.0;

            // Users near quota exhaustion (cards remaining <= 10% of total)
            $usersNearExhaustion = Subscription::where('status', 'active')
                ->where('cards_total', '>', 0)
                ->whereRaw('(cards_total - cards_used) <= (cards_total * 0.1)')
                ->distinct('user_id')
                ->count('user_id');

            // Provider cost vs revenue
            $costPerSms = (float) Setting::get('cost_per_sms', 0.45);
            $smsSentTotal = Delivery::where('channel', 'sms')->whereIn('status', ['sent', 'delivered'])->count();
            $smsSentPeriod = Delivery::where('channel', 'sms')->whereIn('status', ['sent', 'delivered'])
                ->whereBetween('created_at', [$from, $to])
                ->count();

            $estimatedCostTotal = round($smsSentTotal * $costPerSms, 2);
            $estimatedCostPeriod = round($smsSentPeriod * $costPerSms, 2);

            return [
                'total_users' => $totalUsers,
                'registrations_today' => $registrationsToday,
                'registrations_7d' => $registrations7d,
                'registrations_30d' => $registrations30d,
                'registrations_period' => $registrationsPeriod,
                'active_users_30d' => $activeUsers30d,
                'total_events' => $totalEvents,
                'events_period' => $eventsPeriod,
                'total_guests' => $totalGuests,
                'guests_period' => $guestsPeriod,
                'cards_sent_total' => $cardsSentTotal,
                'cards_sent_period' => $cardsSentPeriod,
                'delivery_success_rate' => $deliverySuccessRate,
                'checkins_count' => $checkinsCount,
                'revenue_total' => $revenueTotal,
                'revenue_period' => $revenuePeriod,
                'payments_awaiting_verification' => $paymentsAwaitingVerification,
                'avg_verification_hours' => $avgVerificationHours,
                'users_near_exhaustion' => $usersNearExhaustion,
                'cost_per_sms' => $costPerSms,
                'estimated_cost_total' => $estimatedCostTotal,
                'estimated_cost_period' => $estimatedCostPeriod,
                'net_margin_total' => round($revenueTotal - $estimatedCostTotal, 2),
            ];
        });
    }

    /**
     * Registrations chart data.
     */
    public static function getRegistrationsChartData(?string $period = '30d', ?string $startDate = null, ?string $endDate = null): array
    {
        [$from, $to] = static::resolveDateRange($period, $startDate, $endDate);
        $cacheKey = 'superadmin_reg_chart_' . md5($from->toIso8601String() . $to->toIso8601String());

        return Cache::remember($cacheKey, 60, function () use ($from, $to) {
            $days = [];
            $current = $from->copy();
            while ($current <= $to) {
                $days[$current->format('Y-m-d')] = 0;
                $current->addDay();
            }

            $results = User::select(DB::raw("strftime('%Y-%m-%d', created_at) as date"), DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('date')
                ->pluck('count', 'date')
                ->toArray();

            foreach ($results as $date => $count) {
                if (isset($days[$date])) {
                    $days[$date] = (int) $count;
                }
            }

            return [
                'labels' => array_map(fn ($d) => Carbon::parse($d)->format('M d'), array_keys($days)),
                'data' => array_values($days),
            ];
        });
    }

    /**
     * Cards sent chart data by channel and by campaign type.
     */
    public static function getCardsSentChartData(?string $period = '30d', ?string $startDate = null, ?string $endDate = null): array
    {
        [$from, $to] = static::resolveDateRange($period, $startDate, $endDate);
        $cacheKey = 'superadmin_cards_chart_' . md5($from->toIso8601String() . $to->toIso8601String());

        return Cache::remember($cacheKey, 60, function () use ($from, $to) {
            $days = [];
            $smsData = [];
            $emailData = [];

            $current = $from->copy();
            while ($current <= $to) {
                $d = $current->format('Y-m-d');
                $days[] = Carbon::parse($d)->format('M d');
                $smsData[$d] = 0;
                $emailData[$d] = 0;
                $current->addDay();
            }

            $channelDeliveries = Delivery::select(
                DB::raw("strftime('%Y-%m-%d', created_at) as date"),
                'channel',
                DB::raw('count(*) as count')
            )
                ->whereIn('status', ['sent', 'delivered'])
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('date', 'channel')
                ->get();

            foreach ($channelDeliveries as $cd) {
                if ($cd->channel === 'sms' && isset($smsData[$cd->date])) {
                    $smsData[$cd->date] = (int) $cd->count;
                } elseif ($cd->channel === 'email' && isset($emailData[$cd->date])) {
                    $emailData[$cd->date] = (int) $cd->count;
                }
            }

            // By type from usage_ledger
            $typeUsage = UsageLedger::select('kind', DB::raw('sum(quantity) as total'))
                ->where('quantity', '>', 0)
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('kind')
                ->pluck('total', 'kind')
                ->toArray();

            return [
                'labels' => $days,
                'sms' => array_values($smsData),
                'email' => array_values($emailData),
                'by_type' => [
                    'invitation' => (int) ($typeUsage['invitation'] ?? 0),
                    'reminder' => (int) ($typeUsage['reminder'] ?? 0),
                    'thank_you' => (int) ($typeUsage['thank_you'] ?? 0),
                    'custom' => (int) ($typeUsage['custom'] ?? 0),
                ],
            ];
        });
    }

    /**
     * Users per tier chart.
     */
    public static function getUsersPerTierData(): array
    {
        return Cache::remember('superadmin_users_tier_chart', 60, function () {
            $tiers = Tier::pluck('name_en', 'id')->toArray();
            $counts = Subscription::where('status', 'active')
                ->select('tier_id', DB::raw('count(distinct user_id) as count'))
                ->groupBy('tier_id')
                ->pluck('count', 'tier_id')
                ->toArray();

            $labels = [];
            $data = [];

            foreach ($tiers as $id => $name) {
                $labels[] = $name;
                $data[] = (int) ($counts[$id] ?? 0);
            }

            // Also users without active subscription
            $activeSubUsers = Subscription::where('status', 'active')->distinct()->pluck('user_id');
            $noSubCount = User::whereNotIn('id', $activeSubUsers)->count();
            if ($noSubCount > 0) {
                $labels[] = 'No Tier / Free';
                $data[] = $noSubCount;
            }

            return [
                'labels' => $labels,
                'data' => $data,
            ];
        });
    }

    /**
     * Top 10 users by usage.
     */
    public static function getTopUsersUsage(): array
    {
        return Cache::remember('superadmin_top_users_chart', 60, function () {
            $topUsers = UsageLedger::select('user_id', DB::raw('sum(quantity) as total_used'))
                ->where('quantity', '>', 0)
                ->groupBy('user_id')
                ->orderByDesc('total_used')
                ->limit(10)
                ->with('user')
                ->get();

            $labels = [];
            $data = [];

            foreach ($topUsers as $entry) {
                $labels[] = $entry->user?->name ?? "User #{$entry->user_id}";
                $data[] = (int) $entry->total_used;
            }

            return [
                'labels' => $labels,
                'data' => $data,
            ];
        });
    }

    /**
     * Revenue per month.
     */
    public static function getMonthlyRevenue(): array
    {
        return Cache::remember('superadmin_monthly_rev_chart', 60, function () {
            $months = [];
            $start = now()->subMonths(5)->startOfMonth();
            $end = now()->endOfMonth();

            $curr = $start->copy();
            while ($curr <= $end) {
                $months[$curr->format('Y-m')] = [
                    'label' => $curr->format('M Y'),
                    'amount' => 0.0,
                ];
                $curr->addMonth();
            }

            $payments = Payment::where('status', 'approved')
                ->select(DB::raw("strftime('%Y-%m', COALESCE(reviewed_at, created_at)) as month"), DB::raw('sum(COALESCE(amount_verified, amount_expected, amount)) as total'))
                ->where('created_at', '>=', $start)
                ->groupBy('month')
                ->get();

            foreach ($payments as $p) {
                if (isset($months[$p->month])) {
                    $months[$p->month]['amount'] = (float) $p->total;
                }
            }

            return [
                'labels' => array_column($months, 'label'),
                'data' => array_column($months, 'amount'),
            ];
        });
    }

    /**
     * Failures per provider or status breakdown.
     */
    public static function getDeliveryFailuresData(): array
    {
        return Cache::remember('superadmin_failures_chart', 60, function () {
            $failed = Delivery::where('status', 'failed')->count();
            $delivered = Delivery::where('status', 'delivered')->count();
            $sent = Delivery::where('status', 'sent')->count();
            $queued = Delivery::where('status', 'queued')->count();

            return [
                'labels' => ['Delivered', 'Sent (In Transit)', 'Failed', 'Queued'],
                'data' => [$delivered, $sent, $failed, $queued],
                'failed_count' => $failed,
            ];
        });
    }
}
