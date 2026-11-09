<?php

namespace App\Http\Controllers;

use App\Models\SuperAdmin;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /**
     * Start impersonating a user from Super Admin panel.
     */
    public function impersonate(Request $request, User $user): RedirectResponse
    {
        $superAdmin = Auth::guard('superadmin')->user();
        if (! $superAdmin) {
            abort(403, 'Unauthorized super admin action.');
        }

        $mode = $request->input('mode', 'read_only');
        $durationMinutes = (int) $request->input('duration', 30);
        if ($durationMinutes <= 0 || $durationMinutes > 240) {
            $durationMinutes = 30;
        }

        $expiresAt = now()->addMinutes($durationMinutes);

        // Store impersonation flags in session
        session([
            'impersonator_superadmin_id' => $superAdmin->id,
            'impersonator_name' => $superAdmin->name,
            'impersonation_mode' => $mode, // read_only or full
            'impersonation_expires_at' => $expiresAt->timestamp,
        ]);

        // Login as the user on web guard
        Auth::guard('web')->login($user);

        AuditLogService::log(
            action: 'impersonate_user',
            actor: $superAdmin,
            subject: $user,
            meta: [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'mode' => $mode,
                'duration_minutes' => $durationMinutes,
                'expires_at' => $expiresAt->toIso8601String(),
            ]
        );

        return redirect()->route('dashboard')->with('info', "Now impersonating {$user->name} in {$mode} mode.");
    }

    /**
     * Exit impersonation session and return to super admin panel.
     */
    public function leave(Request $request): RedirectResponse
    {
        $superAdminId = session('impersonator_superadmin_id');
        $superAdmin = $superAdminId ? SuperAdmin::find($superAdminId) : null;
        $impersonatedUser = Auth::guard('web')->user();

        if ($superAdmin && $impersonatedUser) {
            AuditLogService::log(
                action: 'impersonation_ended',
                actor: $superAdmin,
                subject: $impersonatedUser,
                meta: [
                    'user_id' => $impersonatedUser->id,
                    'user_name' => $impersonatedUser->name,
                ]
            );
        }

        session()->forget([
            'impersonator_superadmin_id',
            'impersonator_name',
            'impersonation_mode',
            'impersonation_expires_at',
        ]);

        Auth::guard('web')->logout();

        $secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');

        return redirect("/{$secretPath}");
    }
}
