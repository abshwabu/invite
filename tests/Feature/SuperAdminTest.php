<?php

use App\Models\AuditLog;
use App\Models\SuperAdmin;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierSeeder::class);
    $this->secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');
});

test('guessable /admin path and other standard admin paths return 404', function () {
    $response1 = $this->get('/admin');
    $response1->assertNotFound();

    $response2 = $this->get('/admin/login');
    $response2->assertNotFound();

    $response3 = $this->get('/admin/dashboard');
    $response3->assertNotFound();

    $response4 = $this->get('/wrong-secret-path');
    $response4->assertNotFound();
});

test('super admin login page is accessible only via secret path', function () {
    $response = $this->get("/{$this->secretPath}/login");
    $response->assertOk();
    $response->assertSee('Control Panel');
    $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
});

test('ip allowlist blocks unauthorized ips with 404', function () {
    Config::set('invite.superadmin_allowed_ips', '192.168.1.100, 10.0.0.1');

    // Request from unallowed IP
    $unallowedResponse = $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
        ->get("/{$this->secretPath}/login");
    $unallowedResponse->assertNotFound();

    // Request from allowlisted IP
    $allowedResponse = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])
        ->get("/{$this->secretPath}/login");
    $allowedResponse->assertOk();
});

test('superadmin:create console command creates super admin user', function () {
    $this->artisan('superadmin:create', [
        '--name' => 'Admin Boss',
        '--email' => 'boss@invite.et',
        '--password' => 'SecurePass123!',
    ])->assertSuccessful();

    $admin = SuperAdmin::where('email', 'boss@invite.et')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Admin Boss')
        ->and(Hash::check('SecurePass123!', $admin->password))->toBeTrue()
        ->and($admin->two_factor_confirmed_at)->toBeNull();

    $auditLog = AuditLog::where('action', 'superadmin_created_via_cli')->first();
    expect($auditLog)->not->toBeNull()
        ->and($auditLog->subject_id)->toBe($admin->id);
});

test('login throttles after 5 failed attempts per 15 minutes and writes audit logs', function () {
    $admin = SuperAdmin::factory()->create([
        'email' => 'admin@invite.et',
        'password' => Hash::make('CorrectPassword123!'),
    ]);

    // 5 failed login attempts
    for ($i = 0; $i < 5; $i++) {
        $response = $this->post("/{$this->secretPath}/login", [
            'email' => 'admin@invite.et',
            'password' => 'WrongPassword!',
        ]);
        $response->assertSessionHasErrors('email');
    }

    // 6th attempt should be locked out
    $lockoutResponse = $this->post("/{$this->secretPath}/login", [
        'email' => 'admin@invite.et',
        'password' => 'WrongPassword!',
    ]);

    $lockoutResponse->assertSessionHasErrors('email');
    $errors = session('errors')->get('email');
    expect($errors[0])->toContain('Too many failed login attempts');

    // Check audit logs
    $failedLogs = AuditLog::where('action', 'login_failed')->count();
    $lockedLogs = AuditLog::where('action', 'login_locked_out')->count();

    expect($failedLogs)->toBe(5)
        ->and($lockedLogs)->toBeGreaterThanOrEqual(1);
});

test('first-time login requires mandatory 2FA setup with QR code and 8 recovery codes', function () {
    $admin = SuperAdmin::factory()->create([
        'email' => 'setup2fa@invite.et',
        'password' => Hash::make('Password123!'),
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    // Step 1: Submit valid credentials
    $loginResponse = $this->post("/{$this->secretPath}/login", [
        'email' => 'setup2fa@invite.et',
        'password' => 'Password123!',
    ]);

    $loginResponse->assertRedirect("/{$this->secretPath}/two-factor-setup");

    // Step 2: Access setup page
    $setupPageResponse = $this->get("/{$this->secretPath}/two-factor-setup");
    $setupPageResponse->assertOk();
    $setupPageResponse->assertSee('Setup Two-Factor Authentication');
    $setupPageResponse->assertSee('Emergency Recovery Codes (8)');

    $secret = session('superadmin_temp_2fa_secret');
    $recoveryCodes = session('superadmin_temp_recovery_codes');
    expect($secret)->not->toBeEmpty()
        ->and($recoveryCodes)->toHaveCount(8);

    // Step 3: Provide invalid code
    $invalidResponse = $this->post("/{$this->secretPath}/two-factor-setup", [
        'code' => '000000',
    ]);
    $invalidResponse->assertSessionHasErrors('code');

    // Step 4: Provide valid code from Google2FA
    $google2fa = new Google2FA();
    $validCode = $google2fa->getCurrentOtp($secret);

    $confirmResponse = $this->post("/{$this->secretPath}/two-factor-setup", [
        'code' => $validCode,
    ]);

    $confirmResponse->assertRedirect("/{$this->secretPath}");

    $admin->refresh();
    expect($admin->two_factor_confirmed_at)->not->toBeNull()
        ->and($admin->two_factor_secret)->toBe($secret)
        ->and($admin->two_factor_recovery_codes)->toHaveCount(8)
        ->and(auth('superadmin')->check())->toBeTrue();
});

test('subsequent login requires 2FA challenge and accepts TOTP code', function () {
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();

    $admin = SuperAdmin::factory()->create([
        'email' => 'returning@invite.et',
        'password' => Hash::make('Password123!'),
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => ['CODE-1111', 'CODE-2222'],
    ]);

    // Submit credentials
    $response = $this->post("/{$this->secretPath}/login", [
        'email' => 'returning@invite.et',
        'password' => 'Password123!',
    ]);

    $response->assertRedirect("/{$this->secretPath}/two-factor-challenge");

    // Challenge page
    $challengePage = $this->get("/{$this->secretPath}/two-factor-challenge");
    $challengePage->assertOk();

    // Submit valid TOTP
    $validOtp = $google2fa->getCurrentOtp($secret);
    $verifyResponse = $this->post("/{$this->secretPath}/two-factor-challenge", [
        'code' => $validOtp,
    ]);

    $verifyResponse->assertRedirect("/{$this->secretPath}");
    expect(auth('superadmin')->check())->toBeTrue();
});

test('2FA challenge accepts recovery code and burns it upon usage', function () {
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();

    $admin = SuperAdmin::factory()->create([
        'email' => 'recovery@invite.et',
        'password' => Hash::make('Password123!'),
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => ['RECV-AAAA', 'RECV-BBBB', 'RECV-CCCC'],
    ]);

    $this->post("/{$this->secretPath}/login", [
        'email' => 'recovery@invite.et',
        'password' => 'Password123!',
    ]);

    // Use recovery code RECV-BBBB
    $verifyResponse = $this->post("/{$this->secretPath}/two-factor-challenge", [
        'recovery_code' => 'RECV-BBBB',
    ]);

    $verifyResponse->assertRedirect("/{$this->secretPath}");
    expect(auth('superadmin')->check())->toBeTrue();

    $admin->refresh();
    // RECV-BBBB must be removed
    expect($admin->two_factor_recovery_codes)->toHaveCount(2)
        ->and($admin->two_factor_recovery_codes)->not->toContain('RECV-BBBB');
});

test('normal user session cannot access super admin panel', function () {
    $user = User::factory()->create();

    // Authenticate as normal web user
    $this->actingAs($user, 'web');

    // Attempt to access secret panel dashboard
    $response = $this->get("/{$this->secretPath}");

    // Must be redirected to superadmin login
    $response->assertRedirect("/{$this->secretPath}/login");
    expect(auth('superadmin')->check())->toBeFalse();
});

test('super admin session times out after 30 minutes of idle inactivity', function () {
    $admin = SuperAdmin::factory()->create([
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($admin, 'superadmin');
    session([
        'superadmin_2fa_passed' => true,
        // Set last activity to 31 minutes ago
        'superadmin_last_activity' => time() - 1860,
    ]);

    $response = $this->get("/{$this->secretPath}");

    $response->assertRedirect("/{$this->secretPath}/login");
    $response->assertSessionHasErrors('email');
    expect(auth('superadmin')->check())->toBeFalse();

    $timeoutAudit = AuditLog::where('action', 'session_timeout')->first();
    expect($timeoutAudit)->not->toBeNull();
});

test('enforce only one default tier at a time', function () {
    $free = Tier::where('slug', 'free')->first();
    expect($free->is_default)->toBeTrue();

    $bronze = Tier::where('slug', 'bronze')->first();
    expect($bronze->is_default)->toBeFalse();

    // Switch bronze to default
    $bronze->update(['is_default' => true]);

    $free->refresh();
    $bronze->refresh();

    expect($bronze->is_default)->toBeTrue()
        ->and($free->is_default)->toBeFalse();
});

test('cannot delete tier if active or past subscriptions exist', function () {
    $tier = Tier::where('slug', 'bronze')->first();
    $user = User::factory()->create();

    $user->subscriptions()->create([
        'tier_id' => $tier->id,
        'cards_total' => 500,
        'cards_used' => 0,
        'starts_at' => now(),
        'status' => 'active',
        'source' => 'purchase',
    ]);

    expect(fn () => $tier->delete())->toThrow(\Exception::class);
});
