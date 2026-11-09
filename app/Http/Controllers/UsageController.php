<?php

namespace App\Http\Controllers;

use App\Models\UsageLedger;
use App\Services\EntitlementService;
use App\Services\QuotaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    public function __construct(
        protected QuotaService $quotaService,
        protected EntitlementService $entitlementService,
    ) {}

    /**
     * Show "My Usage" analytics page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 1. Quota overview
        $availableQuota = $this->quotaService->available($user);
        $totalQuota = $this->quotaService->totalCards($user);
        $totalUsed = $this->quotaService->usedCards($user);
        $activeTier = $this->entitlementService->getHighestTier($user);

        // 2. Usage grouped by Event
        $eventUsage = UsageLedger::where('user_id', $user->id)
            ->whereNotNull('event_id')
            ->select('event_id', DB::raw('sum(quantity) as total_quantity'))
            ->groupBy('event_id')
            ->with('event:id,title,status,starts_at')
            ->orderBy('total_quantity', 'desc')
            ->get()
            ->map(fn ($item) => [
                'event_id' => $item->event_id,
                'title' => $item->event?->title ?? 'Unknown Event',
                'status' => $item->event?->status ?? 'closed',
                'cards' => (int) $item->total_quantity,
                'percentage' => $totalUsed > 0 ? (int) round(($item->total_quantity / $totalUsed) * 100) : 0,
            ]);

        // 3. Usage grouped by Campaign Kind
        $kindUsage = UsageLedger::where('user_id', $user->id)
            ->select('kind', DB::raw('sum(quantity) as total_quantity'))
            ->groupBy('kind')
            ->orderBy('total_quantity', 'desc')
            ->get()
            ->map(fn ($item) => [
                'kind' => $item->kind,
                'label' => match ($item->kind) {
                    'invitation' => __('Invitations'),
                    'reminder' => __('Reminders'),
                    'thank_you' => __('Thank-You Notes'),
                    'custom' => __('Custom Messages'),
                    'refund' => __('Refunds'),
                    default => ucfirst($item->kind),
                },
                'cards' => (int) $item->total_quantity,
                'percentage' => $totalUsed > 0 ? (int) round(($item->total_quantity / $totalUsed) * 100) : 0,
            ]);

        // 4. Usage grouped by Channel
        $channelUsage = UsageLedger::where('user_id', $user->id)
            ->whereIn('channel', ['sms', 'email'])
            ->select('channel', DB::raw('sum(quantity) as total_quantity'))
            ->groupBy('channel')
            ->get()
            ->map(fn ($item) => [
                'channel' => $item->channel,
                'label' => strtoupper($item->channel),
                'cards' => (int) $item->total_quantity,
                'percentage' => $totalUsed > 0 ? (int) round(($item->total_quantity / $totalUsed) * 100) : 0,
            ]);

        // 5. Monthly Usage Timeline (Last 6 months)
        $monthlyUsage = UsageLedger::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->select(
                DB::raw("strftime('%Y-%m', created_at) as month"),
                DB::raw('sum(quantity) as total_quantity')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total_quantity', 'month');

        $monthlyTimeline = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthKey = now()->subMonths($i)->format('Y-m');
            $monthlyTimeline[] = [
                'month' => Carbon::parse($monthKey . '-01')->format('M Y'),
                'cards' => (int) ($monthlyUsage[$monthKey] ?? 0),
            ];
        }

        // 6. Recent ledger entries
        $recentTransactions = UsageLedger::where('user_id', $user->id)
            ->with(['event:id,title'])
            ->latest('created_at')
            ->take(25)
            ->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'event_title' => $entry->event?->title ?? 'General / Account',
                'kind' => $entry->kind,
                'channel' => $entry->channel,
                'quantity' => $entry->quantity,
                'is_refund' => $entry->quantity < 0,
                'created_at' => $entry->created_at?->diffForHumans(),
                'created_at_exact' => $entry->created_at?->format('M d, Y H:i'),
            ]);

        return Inertia::render('Usage/Index', [
            'overview' => [
                'available' => $availableQuota,
                'total' => $totalQuota,
                'used' => $totalUsed,
                'tier' => $activeTier ? [
                    'name_en' => $activeTier->name_en,
                    'name_am' => $activeTier->name_am,
                    'slug' => $activeTier->slug,
                ] : null,
            ],
            'byEvent' => $eventUsage,
            'byKind' => $kindUsage,
            'byChannel' => $channelUsage,
            'monthlyTimeline' => $monthlyTimeline,
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
