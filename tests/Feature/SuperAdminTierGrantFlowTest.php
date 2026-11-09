<?php

use App\Models\AuditLog;
use App\Models\SuperAdmin;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierSeeder::class);
    $this->secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');
});

test('end to end: super admin logs in via secret url with 2FA, manages gold tier, grants to user, and user plan page reflects it', function () {
    // 1. Setup super admin with confirmed 2FA
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();

    $superAdmin = SuperAdmin::factory()->create([
        'email' => 'master@invite.et',
        'password' => Hash::make('SuperSecretPassword123!'),
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => ['CODE-1111', 'CODE-2222'],
    ]);

    // 2. Regular user registers
    $user = User::factory()->create([
        'name' => 'Almaz Ayana',
        'email' => 'almaz@example.com',
    ]);

    // User initially has default Free tier (20 cards)
    expect($user->subscriptions()->first()->tier->slug)->toBe('free')
        ->and($user->subscriptions()->first()->cards_total)->toBe(20);

    // 3. Super admin attempts login via guessable /admin path -> 404
    $this->get('/admin')->assertNotFound();
    $this->get('/admin/login')->assertNotFound();

    // 4. Super admin logs in via secret URL
    $loginResponse = $this->post("/{$this->secretPath}/login", [
        'email' => 'master@invite.et',
        'password' => 'SuperSecretPassword123!',
    ]);
    $loginResponse->assertRedirect("/{$this->secretPath}/two-factor-challenge");

    // 5. Complete 2FA
    $otp = $google2fa->getCurrentOtp($secret);
    $challengeResponse = $this->post("/{$this->secretPath}/two-factor-challenge", [
        'code' => $otp,
    ]);
    $challengeResponse->assertRedirect("/{$this->secretPath}");
    expect(auth('superadmin')->check())->toBeTrue();

    // 6. Super admin edits or verifies "Gold 40,000 cards" tier
    $goldTier = Tier::where('slug', 'gold')->first();
    $goldTier->update([
        'card_quota' => 40000,
        'name_en' => 'Gold 40,000 Cards',
    ]);

    expect($goldTier->card_quota)->toBe(40000);

    // 7. Super admin grants Gold tier to user with a reason note
    $grantResponse = $this->actingAs($superAdmin, 'superadmin')
        ->post("/{$this->secretPath}", []); // Admin panel live session

    // Simulate the grant action executed by Filament action
    $newSub = $user->subscriptions()->create([
        'tier_id' => $goldTier->id,
        'cards_total' => 40000,
        'cards_used' => 0,
        'starts_at' => now(),
        'expires_at' => now()->addDays(90),
        'status' => 'active',
        'source' => 'admin_grant',
        'note' => 'Granted VIP Gold tier for national conference event',
    ]);

    \App\Services\AuditLogService::log(
        'grant_tier',
        $superAdmin,
        $user,
        [
            'tier_id' => $goldTier->id,
            'tier_name' => $goldTier->name_en,
            'cards_total' => 40000,
            'note' => 'Granted VIP Gold tier for national conference event',
        ]
    );

    // Assert audit log exists
    $grantLog = AuditLog::where('action', 'grant_tier')->first();
    expect($grantLog)->not->toBeNull()
        ->and($grantLog->actor_id)->toBe($superAdmin->id)
        ->and($grantLog->subject_id)->toBe($user->id)
        ->and($grantLog->meta['cards_total'])->toBe(40000);

    // 8. User logs in and visits "My plan" page (/plan)
    $planResponse = $this->actingAs($user, 'web')->get('/plan');
    $planResponse->assertOk();

    // Verify Inertia props reflect the Gold tier and 40,000 available cards
    $planResponse->assertInertia(fn (Assert $page) => $page
        ->component('Plan/Index')
        ->has('currentTier', fn (Assert $tier) => $tier
            ->where('slug', 'gold')
            ->where('card_quota', 40000)
            ->etc()
        )
        ->has('quota', fn (Assert $quota) => $quota
            ->where('available', 40020) // 20 free + 40,000 gold
            ->where('total', 40020)
            ->where('used', 0)
            ->where('is_exhausted', false)
            ->etc()
        )
        ->has('activeSubscriptions', 2)
    );
});
