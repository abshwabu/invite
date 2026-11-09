<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HandleImpersonationMode
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('impersonator_superadmin_id')) {
            $expiresAt = session('impersonation_expires_at');

            // Check if impersonation session expired
            if ($expiresAt && now()->timestamp > $expiresAt) {
                session()->forget([
                    'impersonator_superadmin_id',
                    'impersonator_name',
                    'impersonation_mode',
                    'impersonation_expires_at',
                ]);

                Auth::guard('web')->logout();

                return redirect()->route('login')->with('warning', 'Impersonation session has expired.');
            }

            // Check read-only mode restrictions
            if (session('impersonation_mode') === 'read_only') {
                $method = strtoupper($request->method());
                if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    // Allow leave impersonation and logout
                    if ($request->is('impersonation/leave') || $request->is('logout') || $request->is('locale')) {
                        return $next($request);
                    }

                    if ($request->wantsJson()) {
                        return response()->json([
                            'message' => 'Read-only impersonation mode: modifications are disabled.',
                        ], 403);
                    }

                    abort(403, 'Read-only impersonation mode: modifications are disabled.');
                }
            }
        }

        return $next($request);
    }
}
