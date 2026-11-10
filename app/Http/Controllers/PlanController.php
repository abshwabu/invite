<?php

namespace App\Http\Controllers;

use App\Models\Tier;
use App\Models\UsageLedger;
use App\Services\EntitlementService;
use App\Services\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
        protected QuotaService $quotaService
    ) {}

    /**
     * Display the user's current plan, quota stats, usage history, and tier comparison.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $activeSubscriptions = $this->entitlementService->getActiveSubscriptions($user);
        $currentTier = $this->entitlementService->getHighestTier($user);

        $availableCards = $this->quotaService->available($user);
        $totalCards = $this->quotaService->totalCards($user);
        $usedCards = $this->quotaService->usedCards($user);

        // Find earliest expiration among active subscriptions
        $earliestExpiry = $activeSubscriptions
            ->filter(fn ($sub) => $sub->expires_at !== null)
            ->sortBy('expires_at')
            ->first()?->expires_at;

        $usageHistory = UsageLedger::query()
            ->where('user_id', $user->id)
            ->with(['event:id,title', 'guest:id,name'])
            ->latest('id')
            ->limit(30)
            ->get();

        $allTiers = \Illuminate\Support\Facades\Cache::remember('active_public_tiers', 3600, function () {
            return Tier::query()
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('sort')
                ->get();
        });

        return Inertia::render('Plan/Index', [
            'currentTier' => $currentTier,
            'activeSubscriptions' => $activeSubscriptions,
            'quota' => [
                'available' => $availableCards,
                'total' => $totalCards,
                'used' => $usedCards,
                'percentage_used' => $totalCards > 0 ? round(($usedCards / $totalCards) * 100, 1) : 0,
                'is_low' => $totalCards > 0 && ($availableCards / $totalCards) <= 0.10 && $availableCards > 0,
                'is_exhausted' => $availableCards <= 0,
                'earliest_expiry' => $earliestExpiry?->toIso8601String(),
            ],
            'usageHistory' => $usageHistory,
            'allTiers' => $allTiers,
            'features' => $this->entitlementService->allFeatures($user),
        ]);
    }

    /**
     * Handle user upgrade request until payment gateway is implemented in Step 14.
     */
    public function upgradeRequest(Request $request): RedirectResponse
    {
        $request->validate([
            'tier_id' => 'required|exists:tiers,id',
        ]);

        $tier = Tier::findOrFail($request->tier_id);

        return back()->with(
            'success',
            __('Upgrade request for :tier received! Please contact the admin or payment desk at +251 91 123 4567 to activate your quota.', [
                'tier' => $request->header('X-Inertia') && app()->getLocale() === 'am' ? $tier->name_am : $tier->name_en,
            ])
        );
    }
}
