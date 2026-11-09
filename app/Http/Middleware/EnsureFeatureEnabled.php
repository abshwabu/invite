<?php

namespace App\Http\Middleware;

use App\Services\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function __construct(
        protected EntitlementService $entitlementService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        if (! $this->entitlementService->can($user, $feature)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "The '{$feature}' feature is not included in your current plan. Please upgrade.",
                    'upgrade_required' => true,
                    'feature' => $feature,
                ], 403);
            }

            return redirect()->route('plan.index')->with('warning', "The {$feature} feature is not available on your current plan. Please upgrade to continue.");
        }

        return $next($request);
    }
}
