<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $langFile = base_path("lang/{$locale}.json");
        $translations = file_exists($langFile) ? json_decode(file_get_contents($langFile), true) : [];

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'quota' => fn () => $request->user() ? [
                    'available' => app(\App\Services\QuotaService::class)->available($request->user()),
                    'total' => app(\App\Services\QuotaService::class)->totalCards($request->user()),
                    'used' => app(\App\Services\QuotaService::class)->usedCards($request->user()),
                    'is_low' => (function () use ($request) {
                        $avail = app(\App\Services\QuotaService::class)->available($request->user());
                        $total = app(\App\Services\QuotaService::class)->totalCards($request->user());

                        return $total > 0 && ($avail / $total) <= 0.10 && $avail > 0;
                    })(),
                    'is_exhausted' => app(\App\Services\QuotaService::class)->available($request->user()) <= 0,
                    'tier' => ($tier = app(\App\Services\EntitlementService::class)->getHighestTier($request->user())) ? [
                        'id' => $tier->id,
                        'name_en' => $tier->name_en,
                        'name_am' => $tier->name_am,
                        'slug' => $tier->slug,
                    ] : null,
                ] : null,
            ],
            'locale' => $locale,
            'supportedLocales' => config('invite.supported_locales', ['en', 'am']),
            'translations' => $translations ?? [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
        ];
    }
}
