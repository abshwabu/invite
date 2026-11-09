<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminAuthenticate
{
    /**
     * Handle an incoming request for the super admin panel.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');

        if (! Auth::guard('superadmin')->check()) {
            return redirect("/{$secretPath}/login");
        }

        $admin = Auth::guard('superadmin')->user();

        // Check if 2FA passed for current session
        if (! $request->session()->get('superadmin_2fa_passed')) {
            if (! $admin->two_factor_confirmed_at) {
                return redirect("/{$secretPath}/two-factor-setup");
            }

            return redirect("/{$secretPath}/two-factor-challenge");
        }

        return $next($request);
    }
}
