<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminSessionTimeout
{
    /**
     * Idle timeout duration in seconds (30 minutes).
     */
    protected const IDLE_TIMEOUT_SECONDS = 1800;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('superadmin')->check()) {
            $lastActivity = $request->session()->get('superadmin_last_activity');

            if ($lastActivity && (time() - $lastActivity > self::IDLE_TIMEOUT_SECONDS)) {
                $admin = Auth::guard('superadmin')->user();

                AuditLogService::log(
                    'session_timeout',
                    $admin,
                    $admin,
                    ['ip' => $request->ip(), 'idle_seconds' => time() - $lastActivity]
                );

                Auth::guard('superadmin')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');

                return redirect("/{$secretPath}/login")->withErrors([
                    'email' => __('Your session has timed out due to 30 minutes of inactivity. Please log in again.'),
                ]);
            }

            $request->session()->put('superadmin_last_activity', time());
        }

        return $next($request);
    }
}
