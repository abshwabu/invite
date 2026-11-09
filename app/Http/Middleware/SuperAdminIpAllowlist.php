<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminIpAllowlist
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('invite.superadmin_allowed_ips');

        if (! empty($allowedIps)) {
            $ips = array_filter(array_map('trim', explode(',', $allowedIps)));

            if (! in_array($request->ip(), $ips, true)) {
                // Abort with standard 404 to hide the existence of the secret endpoint
                abort(404);
            }
        }

        return $next($request);
    }
}
