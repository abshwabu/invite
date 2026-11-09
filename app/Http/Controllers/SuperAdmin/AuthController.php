<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use App\Services\AuditLogService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    /**
     * Show the super admin login form.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        $secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');

        if (Auth::guard('superadmin')->check() && session('superadmin_2fa_passed')) {
            return redirect("/{$secretPath}");
        }

        return view('superadmin.login', [
            'secretPath' => $secretPath,
        ]);
    }

    /**
     * Handle initial credential authentication.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');
        $email = strtolower(trim($request->email));
        $ip = $request->ip();

        // 5 attempts per 15 minutes per IP and per account
        $ipThrottleKey = 'superadmin_login_ip_' . $ip;
        $emailThrottleKey = 'superadmin_login_email_' . $email;

        if (RateLimiter::tooManyAttempts($ipThrottleKey, 5) || RateLimiter::tooManyAttempts($emailThrottleKey, 5)) {
            $secondsRemaining = max(
                RateLimiter::availableIn($ipThrottleKey),
                RateLimiter::availableIn($emailThrottleKey)
            );

            AuditLogService::log('login_locked_out', null, null, [
                'email' => $email,
                'ip' => $ip,
                'seconds_remaining' => $secondsRemaining,
            ]);

            return back()->withErrors([
                'email' => __('Too many failed login attempts. Please try again in :minutes minute(s).', [
                    'minutes' => ceil($secondsRemaining / 60),
                ]),
            ])->onlyInput('email');
        }

        $superAdmin = SuperAdmin::where('email', $email)->first();

        if (! $superAdmin || ! Hash::check($request->password, $superAdmin->password)) {
            RateLimiter::hit($ipThrottleKey, 900);
            RateLimiter::hit($emailThrottleKey, 900);

            AuditLogService::log('login_failed', null, $superAdmin, [
                'email' => $email,
                'ip' => $ip,
                'reason' => 'invalid_credentials',
            ]);

            return back()->withErrors([
                'email' => __('These credentials do not match our records.'),
            ])->onlyInput('email');
        }

        // Credentials are valid; clear throttle keys
        RateLimiter::clear($ipThrottleKey);
        RateLimiter::clear($emailThrottleKey);

        AuditLogService::log('login_credentials_verified', $superAdmin, $superAdmin, [
            'ip' => $ip,
        ]);

        // Store pending super admin ID in session for 2FA
        session([
            'superadmin_auth_id' => $superAdmin->id,
            'superadmin_remember' => $request->boolean('remember'),
        ]);

        if (! $superAdmin->two_factor_confirmed_at) {
            return redirect("/{$secretPath}/two-factor-setup");
        }

        return redirect("/{$secretPath}/two-factor-challenge");
    }

    /**
     * Display the 2FA setup form for first-time login.
     */
    public function showTwoFactorSetup(Request $request): View|RedirectResponse
    {
        $secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');
        $adminId = session('superadmin_auth_id');

        if (! $adminId) {
            return redirect("/{$secretPath}/login");
        }

        $admin = SuperAdmin::findOrFail($adminId);

        if ($admin->two_factor_confirmed_at) {
            return redirect("/{$secretPath}/two-factor-challenge");
        }

        $google2fa = new Google2FA();

        if (! session()->has('superadmin_temp_2fa_secret')) {
            session(['superadmin_temp_2fa_secret' => $google2fa->generateSecretKey()]);
        }
        $secret = session('superadmin_temp_2fa_secret');

        if (! session()->has('superadmin_temp_recovery_codes')) {
            $codes = [];
            for ($i = 0; $i < 8; $i++) {
                $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4));
            }
            session(['superadmin_temp_recovery_codes' => $codes]);
        }
        $recoveryCodes = session('superadmin_temp_recovery_codes');

        $qrCodeUrl = $google2fa->getQRCodeUrl('Invite Superadmin', $admin->email, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeUrl);

        return view('superadmin.two-factor-setup', [
            'secretPath' => $secretPath,
            'secret' => $secret,
            'qrCodeSvg' => $qrCodeSvg,
            'recoveryCodes' => $recoveryCodes,
            'admin' => $admin,
        ]);
    }

    /**
     * Confirm and finalize 2FA setup with a valid TOTP code.
     */
    public function confirmTwoFactorSetup(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');
        $adminId = session('superadmin_auth_id');

        if (! $adminId) {
            return redirect("/{$secretPath}/login");
        }

        $admin = SuperAdmin::findOrFail($adminId);
        $secret = session('superadmin_temp_2fa_secret');
        $recoveryCodes = session('superadmin_temp_recovery_codes', []);

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            AuditLogService::log('two_factor_setup_failed', $admin, $admin, [
                'ip' => $request->ip(),
                'reason' => 'invalid_code',
            ]);

            return back()->withErrors([
                'code' => __('The two-factor verification code was invalid. Please try again.'),
            ]);
        }

        // Save 2FA secret and recovery codes
        $admin->update([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Complete login
        Auth::guard('superadmin')->login($admin, session('superadmin_remember', false));
        session([
            'superadmin_2fa_passed' => true,
            'superadmin_last_activity' => time(),
        ]);

        session()->forget([
            'superadmin_auth_id',
            'superadmin_remember',
            'superadmin_temp_2fa_secret',
            'superadmin_temp_recovery_codes',
        ]);

        AuditLogService::log('two_factor_setup_confirmed', $admin, $admin, [
            'ip' => $request->ip(),
        ]);

        AuditLogService::log('login_success', $admin, $admin, [
            'ip' => $request->ip(),
            'method' => 'totp_initial_setup',
        ]);

        return redirect("/{$secretPath}");
    }

    /**
     * Display the 2FA challenge screen for returning admins.
     */
    public function showTwoFactorChallenge(): View|RedirectResponse
    {
        $secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');
        $adminId = session('superadmin_auth_id');

        if (! $adminId) {
            return redirect("/{$secretPath}/login");
        }

        return view('superadmin.two-factor-challenge', [
            'secretPath' => $secretPath,
        ]);
    }

    /**
     * Verify the 2FA challenge via TOTP code or recovery code.
     */
    public function verifyTwoFactorChallenge(Request $request): RedirectResponse
    {
        $secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');
        $adminId = session('superadmin_auth_id');

        if (! $adminId) {
            return redirect("/{$secretPath}/login");
        }

        $admin = SuperAdmin::findOrFail($adminId);

        $code = trim((string) $request->input('code'));
        $recoveryCode = trim((string) $request->input('recovery_code'));

        if (empty($code) && empty($recoveryCode)) {
            return back()->withErrors([
                'code' => __('Please enter either your authenticator code or a recovery code.'),
            ]);
        }

        $authenticated = false;
        $authMethod = 'totp';

        if (! empty($code)) {
            $google2fa = new Google2FA();
            if ($google2fa->verifyKey($admin->two_factor_secret, $code)) {
                $authenticated = true;
                $authMethod = 'totp';
            }
        } elseif (! empty($recoveryCode)) {
            $normalizedInput = strtoupper($recoveryCode);
            $storedCodes = (array) ($admin->two_factor_recovery_codes ?? []);

            $matchedIndex = array_search($normalizedInput, $storedCodes, true);
            if ($matchedIndex !== false) {
                // Use recovery code and burn it
                unset($storedCodes[$matchedIndex]);
                $admin->update([
                    'two_factor_recovery_codes' => array_values($storedCodes),
                ]);

                $authenticated = true;
                $authMethod = 'recovery_code';
            }
        }

        if (! $authenticated) {
            AuditLogService::log('two_factor_challenge_failed', $admin, $admin, [
                'ip' => $request->ip(),
                'method' => $authMethod,
            ]);

            return back()->withErrors([
                'code' => __('The two-factor authentication code or recovery code is invalid.'),
            ]);
        }

        $admin->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        Auth::guard('superadmin')->login($admin, session('superadmin_remember', false));
        session([
            'superadmin_2fa_passed' => true,
            'superadmin_last_activity' => time(),
        ]);

        session()->forget([
            'superadmin_auth_id',
            'superadmin_remember',
        ]);

        AuditLogService::log('login_success', $admin, $admin, [
            'ip' => $request->ip(),
            'method' => $authMethod,
        ]);

        return redirect("/{$secretPath}");
    }

    /**
     * Log out of the super admin panel.
     */
    public function logout(Request $request): RedirectResponse
    {
        $secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');
        $admin = Auth::guard('superadmin')->user();

        if ($admin) {
            AuditLogService::log('logout', $admin, $admin, [
                'ip' => $request->ip(),
            ]);
        }

        Auth::guard('superadmin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect("/{$secretPath}/login")->with('status', __('Logged out successfully.'));
    }
}
