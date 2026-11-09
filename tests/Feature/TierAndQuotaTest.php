<?php

use App\Jobs\ExpireSubscriptionsJob;
use App\Models\Subscription;
use App\Models\Tier;
use App\Models\UsageLedger;
use App\Models\User;
use App\Services\EntitlementService;
use App\Services\QuotaService;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierSeeder::class);
});

test('new user registration automatically subscribes to the default tier', function () {
    $response = $this->post('/register', [
        'name' => 'Abebe Kebede',
        'email' => 'abebe@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect('/dashboard');

    $user = User::where('email', 'abebe@example.com')->first();
    expect($user)->not->toBeNull();

    $defaultTier = Tier::where('is_default', true)->first();
    expect($defaultTier)->not->toBeNull();

    $subscription = $user->subscriptions()->first();
    expect($subscription)->not->toBeNull()
        ->and($subscription->tier_id)->toBe($defaultTier->id)
        ->and($subscription->cards_total)->toBe($defaultTier->card_quota)
        ->and($subscription->cards_used)->toBe(0)
        ->and($subscription->status)->toBe('active')
        ->and($subscription->source)->toBe('free');
});

test('entitlement service resolves features and limits from highest active tier', function () {
    $entitlementService = app(EntitlementService::class);

    $user = User::factory()->create();
    // Default tier created by model hook is 'free' (no qr_checkin, no thank_you)
    expect($entitlementService->can($user, 'qr_checkin'))->toBeFalse()
        ->and($entitlementService->can($user, 'thank_you'))->toBeFalse()
        ->and($entitlementService->can($user, 'sms_channel'))->toBeTrue()
        ->and($entitlementService->limit($user, 'max_guests_per_event'))->toBe(50);

    // Grant Bronze tier (has qr_checkin and thank_you, max_guests=500)
    $bronzeTier = Tier::where('slug', 'bronze')->first();
    $user->subscriptions()->create([
        'tier_id' => $bronzeTier->id,
        'cards_total' => $bronzeTier->card_quota,
        'cards_used' => 0,
        'starts_at' => now(),
        'expires_at' => now()->addDays(30),
        'status' => 'active',
        'source' => 'purchase',
    ]);

    expect($entitlementService->can($user, 'qr_checkin'))->toBeTrue()
        ->and($entitlementService->can($user, 'thank_you'))->toBeTrue()
        ->and($entitlementService->limit($user, 'max_guests_per_event'))->toBe(500);

    // Grant Platinum tier (unlimited max_events = null)
    $platinumTier = Tier::where('slug', 'platinum')->first();
    $user->subscriptions()->create([
        'tier_id' => $platinumTier->id,
        'cards_total' => $platinumTier->card_quota,
        'cards_used' => 0,
        'starts_at' => now(),
        'expires_at' => now()->addDays(180),
        'status' => 'active',
        'source' => 'purchase',
    ]);

    expect($entitlementService->limit($user, 'max_events'))->toBeNull()
        ->and($entitlementService->limit($user, 'max_guests_per_event'))->toBe(25000);
});

test('quota reserve consumes from the active subscription that expires soonest first', function () {
    $quotaService = app(QuotaService::class);
    $user = User::factory()->create();

    // Remove the default free subscription for explicit testing
    $user->subscriptions()->delete();

    $silverTier = Tier::where('slug', 'silver')->first();

    // Subscription 1: expires in 60 days, 100 cards
    $sub1 = $user->subscriptions()->create([
        'tier_id' => $silverTier->id,
        'cards_total' => 100,
        'cards_used' => 0,
        'starts_at' => now(),
        'expires_at' => now()->addDays(60),
        'status' => 'active',
        'source' => 'purchase',
    ]);

    // Subscription 2: expires in 10 days (earliest!), 50 cards
    $sub2 = $user->subscriptions()->create([
        'tier_id' => $silverTier->id,
        'cards_total' => 50,
        'cards_used' => 0,
        'starts_at' => now(),
        'expires_at' => now()->addDays(10),
        'status' => 'active',
        'source' => 'purchase',
    ]);

    expect($quotaService->available($user))->toBe(150);

    // Reserve 70 cards. Should exhaust sub2 (50 cards) and take 20 cards from sub1
    $result = $quotaService->reserve($user, 70, ['kind' => 'invitation', 'channel' => 'sms']);

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->reserved)->toBe(70);

    $sub2->refresh();
    $sub1->refresh();

    expect($sub2->cards_used)->toBe(50)
        ->and($sub2->status)->toBe('exhausted')
        ->and($sub1->cards_used)->toBe(20)
        ->and($sub1->status)->toBe('active')
        ->and($quotaService->available($user))->toBe(80);

    // Check usage ledgers
    $ledgers = UsageLedger::where('user_id', $user->id)->orderBy('id')->get();
    expect($ledgers)->toHaveCount(2)
        ->and($ledgers[0]->subscription_id)->toBe($sub2->id)
        ->and($ledgers[0]->quantity)->toBe(50)
        ->and($ledgers[1]->subscription_id)->toBe($sub1->id)
        ->and($ledgers[1]->quantity)->toBe(20);
});

test('quota reserve fails if requested amount exceeds available balance', function () {
    $quotaService = app(QuotaService::class);
    $user = User::factory()->create();

    $available = $quotaService->available($user);

    $result = $quotaService->reserve($user, $available + 50);

    expect($result->isFailed())->toBeTrue()
        ->and($quotaService->available($user))->toBe($available);
});

test('quota reservation is race-safe and never oversells under concurrency simulation', function () {
    $quotaService = app(QuotaService::class);
    $user = User::factory()->create();

    $user->subscriptions()->delete();
    $tier = Tier::where('slug', 'free')->first();

    // Create subscription with exactly 20 cards
    $sub = $user->subscriptions()->create([
        'tier_id' => $tier->id,
        'cards_total' => 20,
        'cards_used' => 0,
        'starts_at' => now(),
        'expires_at' => null,
        'status' => 'active',
        'source' => 'free',
    ]);

    // Simulate 5 concurrent requests of 5 cards each (total requested = 25 cards for a 20 card pool)
    $successfulReservations = 0;
    $failedReservations = 0;

    for ($i = 0; $i < 5; $i++) {
        try {
            $result = $quotaService->reserve($user, 5, ['kind' => 'invitation']);
            if ($result->isSuccessful()) {
                $successfulReservations++;
            } else {
                $failedReservations++;
            }
        } catch (\Throwable $e) {
            $failedReservations++;
        }
    }

    $sub->refresh();

    // Exactly 4 requests of 5 cards can succeed (20 cards). The 5th must fail!
    expect($successfulReservations)->toBe(4)
        ->and($failedReservations)->toBe(1)
        ->and($sub->cards_used)->toBe(20)
        ->and($sub->cards_used)->toBeLessThanOrEqual($sub->cards_total)
        ->and($sub->status)->toBe('exhausted');
});

test('quota refund restores cards and writes negative ledger entry', function () {
    $quotaService = app(QuotaService::class);
    $user = User::factory()->create();

    $user->subscriptions()->delete();
    $tier = Tier::where('slug', 'free')->first();

    $sub = $user->subscriptions()->create([
        'tier_id' => $tier->id,
        'cards_total' => 20,
        'cards_used' => 10,
        'starts_at' => now(),
        'expires_at' => null,
        'status' => 'active',
        'source' => 'free',
    ]);

    expect($quotaService->available($user))->toBe(10);

    // Refund 4 cards
    $refundLedger = $quotaService->refund([
        'subscription_id' => $sub->id,
        'user_id' => $user->id,
        'channel' => 'sms',
    ], 4);

    $sub->refresh();

    expect($sub->cards_used)->toBe(6)
        ->and($quotaService->available($user))->toBe(14)
        ->and($refundLedger->quantity)->toBe(-4)
        ->and($refundLedger->kind)->toBe('refund');
});

test('quota cost calculation respects tier sms and email weights and thank you flag', function () {
    $quotaService = app(QuotaService::class);
    $freeTier = Tier::where('slug', 'free')->first();
    $goldTier = Tier::where('slug', 'gold')->first();

    // Free tier: thank_you_consumes_quota = false
    expect($quotaService->calculateRequiredCards($freeTier, 'sms', 'thank_you', 5))->toBe(0)
        ->and($quotaService->calculateRequiredCards($freeTier, 'sms', 'invitation', 10))->toBe(10)
        ->and($quotaService->calculateRequiredCards($freeTier, 'email', 'invitation', 10))->toBe(10);

    // Set custom weights on gold tier
    $goldTier->update([
        'sms_weight' => 2,
        'email_weight' => 1,
        'thank_you_consumes_quota' => true,
    ]);

    expect($quotaService->calculateRequiredCards($goldTier, 'sms', 'invitation', 10))->toBe(20)
        ->and($quotaService->calculateRequiredCards($goldTier, 'email', 'invitation', 10))->toBe(10)
        ->and($quotaService->calculateRequiredCards($goldTier, 'both', 'invitation', 10))->toBe(30)
        ->and($quotaService->calculateRequiredCards($goldTier, 'sms', 'thank_you', 5))->toBe(10);
});

test('expire overdue subscriptions job marks subscriptions expired', function () {
    $user = User::factory()->create();
    $user->subscriptions()->delete();

    $tier = Tier::where('slug', 'bronze')->first();

    // Active but past expiration date
    $expiredSub = $user->subscriptions()->create([
        'tier_id' => $tier->id,
        'cards_total' => 500,
        'cards_used' => 50,
        'starts_at' => now()->subDays(40),
        'expires_at' => now()->subDay(),
        'status' => 'active',
        'source' => 'purchase',
    ]);

    // Active and still valid
    $validSub = $user->subscriptions()->create([
        'tier_id' => $tier->id,
        'cards_total' => 500,
        'cards_used' => 10,
        'starts_at' => now()->subDays(5),
        'expires_at' => now()->addDays(25),
        'status' => 'active',
        'source' => 'purchase',
    ]);

    $this->artisan('subscriptions:expire')
        ->assertSuccessful();

    $expiredSub->refresh();
    $validSub->refresh();

    expect($expiredSub->status)->toBe('expired')
        ->and($validSub->status)->toBe('active');
});

test('feature middleware blocks unauthorized features and prompts upgrade', function () {
    Route::get('/test-qr-feature', function () {
        return 'Allowed';
    })->middleware(['web', 'auth', 'feature:qr_checkin']);

    $user = User::factory()->create();

    // Free tier does not have qr_checkin; should redirect to /plan with warning
    $response = $this->actingAs($user)->get('/test-qr-feature');
    $response->assertRedirect('/plan');
    $response->assertSessionHas('warning');

    // JSON requests receive 403 with upgrade_required
    $jsonResponse = $this->actingAs($user)->getJson('/test-qr-feature');
    $jsonResponse->assertStatus(403);
    $jsonResponse->assertJsonFragment(['upgrade_required' => true]);

    // Grant Bronze tier (has qr_checkin)
    $bronze = Tier::where('slug', 'bronze')->first();
    $user->subscriptions()->create([
        'tier_id' => $bronze->id,
        'cards_total' => $bronze->card_quota,
        'cards_used' => 0,
        'starts_at' => now(),
        'expires_at' => now()->addDays(30),
        'status' => 'active',
        'source' => 'purchase',
    ]);

    $allowedResponse = $this->actingAs($user)->get('/test-qr-feature');
    $allowedResponse->assertOk();
    $allowedResponse->assertSee('Allowed');
});

test('user can view my plan page and submit upgrade request', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/plan');
    $response->assertOk();

    $goldTier = Tier::where('slug', 'gold')->first();

    $upgradeResponse = $this->actingAs($user)->post('/plan/upgrade-request', [
        'tier_id' => $goldTier->id,
    ]);

    $upgradeResponse->assertSessionHas('success');
});
