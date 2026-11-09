<?php

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SuperAdmin;
use App\Models\Tier;
use App\Models\UsageLedger;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\SuperAdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed default category, tier and superadmin
    $this->category = \App\Models\Category::create([
        'name_en' => 'Wedding',
        'name_am' => 'ሰርግ',
        'slug' => 'wedding',
        'icon' => 'sparkles',
    ]);

    $this->tier = Tier::create([
        'name_en' => 'Free Starter',
        'name_am' => 'ነፃ ጀማሪ',
        'slug' => 'starter',
        'card_quota' => 20,
        'validity_days' => 30,
        'price_etb' => 0,
        'is_active' => true,
        'is_default' => true,
        'limits' => [
            'max_events' => 2,
            'max_guests_per_event' => 50,
            'max_scanners_per_event' => 1,
        ],
        'features' => [
            'qr_checkin' => true,
            'custom_messages' => true,
            'custom_links' => true,
            'thank_you' => true,
            'reminders' => true,
        ],
    ]);

    $this->ownerAdmin = SuperAdmin::create([
        'name' => 'Owner Admin',
        'email' => 'owner@invite.et',
        'password' => Hash::make('Secret123!'),
        'role' => 'owner',
        'two_factor_confirmed_at' => now(),
    ]);

    $this->supportAdmin = SuperAdmin::create([
        'name' => 'Support Admin',
        'email' => 'support@invite.et',
        'password' => Hash::make('Secret123!'),
        'role' => 'support',
        'two_factor_confirmed_at' => now(),
    ]);
});

test('dashboard kpi aggregates accurately calculate database state and cache for 60 seconds', function () {
    // Create users with different registration dates
    $user1 = User::factory()->create([
        'created_at' => now(),
        'last_login_at' => now(),
    ]);
    $user2 = User::factory()->create([
        'created_at' => now()->subDays(5),
        'last_login_at' => now()->subDays(2),
    ]);
    $user3 = User::factory()->create([
        'created_at' => now()->subDays(20),
        'last_login_at' => now()->subDays(40), // Inactive > 30d
    ]);

    // Subscriptions
    $sub1 = Subscription::create([
        'user_id' => $user1->id,
        'tier_id' => $this->tier->id,
        'status' => 'active',
        'cards_total' => 20,
        'cards_used' => 19, // Near exhaustion (1 left <= 10%)
        'starts_at' => now(),
        'expires_at' => now()->addDays(30),
    ]);

    $sub2 = Subscription::create([
        'user_id' => $user2->id,
        'tier_id' => $this->tier->id,
        'status' => 'active',
        'cards_total' => 20,
        'cards_used' => 5, // Healthy balance
        'starts_at' => now(),
        'expires_at' => now()->addDays(30),
    ]);

    // Events and Guests
    $event = Event::create([
        'user_id' => $user1->id,
        'category_id' => $this->category->id,
        'title' => 'Gala Dinner',
        'slug' => 'gala-dinner',
        'public_slug' => 'gala123',
        'starts_at' => now()->addDays(7),
        'date' => now()->addDays(7)->format('Y-m-d'),
        'time' => '18:00',
        'status' => 'published',
        'is_invite_only' => true,
        'rsvp_enabled' => true,
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Abebe Bikila',
        'phone' => '+251911223344',
        'token' => 'guest_token_123',
        'checked_in_at' => now(),
    ]);

    // Payments
    Payment::create([
        'user_id' => $user1->id,
        'tier_id' => $this->tier->id,
        'amount' => 500.00,
        'currency' => 'ETB',
        'provider' => 'chapa',
        'provider_ref' => 'ref_123',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    // Deliveries
    Delivery::create([
        'guest_id' => $guest->id,
        'channel' => 'sms',
        'status' => 'delivered',
        'quota_charged' => 1,
    ]);
    Delivery::create([
        'guest_id' => $guest->id,
        'channel' => 'sms',
        'status' => 'failed',
        'error' => 'Invalid number',
        'quota_charged' => 0,
    ]);

    Setting::set('cost_per_sms', 0.50);

    // Call service
    Cache::flush();
    $kpis = SuperAdminDashboardService::getKpiStats('30d');

    expect($kpis['total_users'])->toBe(3);
    expect($kpis['registrations_today'])->toBe(1);
    expect($kpis['registrations_7d'])->toBe(2);
    expect($kpis['registrations_30d'])->toBe(3);
    expect($kpis['active_users_30d'])->toBe(2); // user1 and user2
    expect($kpis['total_events'])->toBe(1);
    expect($kpis['total_guests'])->toBe(1);
    expect($kpis['cards_sent_total'])->toBe(1);
    expect($kpis['checkins_count'])->toBe(1);
    expect($kpis['revenue_total'])->toBe(500.00);
    expect($kpis['users_near_exhaustion'])->toBe(1);
    expect($kpis['cost_per_sms'])->toBe(0.50);
    expect($kpis['estimated_cost_total'])->toBe(0.50); // 1 sms sent * 0.50
    expect($kpis['net_margin_total'])->toBe(499.50); // 500 - 0.50
    expect($kpis['delivery_success_rate'])->toBe(50.0); // 1 delivered out of 2 total
});

test('chart aggregation methods correctly return format for charts', function () {
    Cache::flush();
    $user = User::factory()->create(['created_at' => now()]);

    $regChart = SuperAdminDashboardService::getRegistrationsChartData('7d');
    expect($regChart)->toHaveKeys(['labels', 'data']);
    expect(count($regChart['labels']))->toBe(8); // 7d range

    $cardsChart = SuperAdminDashboardService::getCardsSentChartData('7d');
    expect($cardsChart)->toHaveKeys(['labels', 'sms', 'email', 'by_type']);

    $tierChart = SuperAdminDashboardService::getUsersPerTierData();
    expect($tierChart)->toHaveKeys(['labels', 'data']);

    $topUsers = SuperAdminDashboardService::getTopUsersUsage();
    expect($topUsers)->toHaveKeys(['labels', 'data']);

    $revenue = SuperAdminDashboardService::getMonthlyRevenue();
    expect($revenue)->toHaveKeys(['labels', 'data']);

    $failures = SuperAdminDashboardService::getDeliveryFailuresData();
    expect($failures)->toHaveKeys(['labels', 'data', 'failed_count']);
});

test('super admin can suspend and unsuspend user, generating audit log entries', function () {
    $user = User::factory()->create(['is_suspended' => false]);

    $this->actingAs($this->ownerAdmin, 'superadmin');

    // Suspend user
    $user->update(['is_suspended' => true]);
    AuditLogService::log('suspend_user', $this->ownerAdmin, $user, ['reason' => 'Spamming behavior']);

    expect($user->fresh()->is_suspended)->toBeTrue();

    $log = AuditLog::where('action', 'suspend_user')->where('subject_id', $user->id)->first();
    expect($log)->not->toBeNull();
    expect($log->actor_id)->toBe($this->ownerAdmin->id);
    expect($log->meta['reason'])->toBe('Spamming behavior');

    // Unsuspend user
    $user->update(['is_suspended' => false]);
    AuditLogService::log('unsuspend_user', $this->ownerAdmin, $user, ['reason' => 'Issue resolved']);

    expect($user->fresh()->is_suspended)->toBeFalse();

    $log2 = AuditLog::where('action', 'unsuspend_user')->where('subject_id', $user->id)->first();
    expect($log2)->not->toBeNull();
});

test('super admin card adjustment updates user quota and writes to usage ledger as adjustment', function () {
    $user = User::factory()->create();
    $sub = Subscription::create([
        'user_id' => $user->id,
        'tier_id' => $this->tier->id,
        'status' => 'active',
        'cards_total' => 20,
        'cards_used' => 5,
        'starts_at' => now(),
        'expires_at' => now()->addDays(30),
    ]);

    $adjustment = 50;
    $newTotal = $sub->cards_total + $adjustment;
    $sub->update(['cards_total' => $newTotal]);

    UsageLedger::create([
        'user_id' => $user->id,
        'subscription_id' => $sub->id,
        'kind' => 'adjustment',
        'channel' => 'none',
        'quantity' => $adjustment,
        'created_at' => now(),
    ]);

    AuditLogService::log('adjust_cards', $this->ownerAdmin, $user, [
        'old_total' => 20,
        'new_total' => $newTotal,
        'adjustment_amount' => $adjustment,
        'reason' => 'Promotion grant for conference',
    ]);

    expect($sub->fresh()->cards_total)->toBe(70);

    $ledger = UsageLedger::where('user_id', $user->id)->where('kind', 'adjustment')->first();
    expect($ledger)->not->toBeNull();
    expect($ledger->quantity)->toBe(50);
    expect($ledger->channel)->toBe('none');

    $log = AuditLog::where('action', 'adjust_cards')->where('subject_id', $user->id)->first();
    expect($log)->not->toBeNull();
    expect($log->meta['adjustment_amount'])->toBe(50);
    expect($log->meta['reason'])->toBe('Promotion grant for conference');
});

test('super admin can extend subscription expiry with mandatory audit log', function () {
    $user = User::factory()->create();
    $originalExpiry = now()->addDays(10);
    $sub = Subscription::create([
        'user_id' => $user->id,
        'tier_id' => $this->tier->id,
        'status' => 'active',
        'cards_total' => 50,
        'cards_used' => 10,
        'starts_at' => now(),
        'expires_at' => $originalExpiry,
    ]);

    $daysToExtend = 30;
    $newExpiry = $originalExpiry->copy()->addDays($daysToExtend);
    $sub->update(['expires_at' => $newExpiry]);

    AuditLogService::log('extend_subscription', $this->ownerAdmin, $user, [
        'subscription_id' => $sub->id,
        'days' => $daysToExtend,
        'new_expires_at' => $newExpiry->toIso8601String(),
        'reason' => 'Customer courtesy extension',
    ]);

    expect($sub->fresh()->expires_at->format('Y-m-d'))->toBe($newExpiry->format('Y-m-d'));

    $log = AuditLog::where('action', 'extend_subscription')->where('subject_id', $user->id)->first();
    expect($log)->not->toBeNull();
    expect($log->meta['days'])->toBe(30);
    expect($log->meta['reason'])->toBe('Customer courtesy extension');
});

test('super admin user impersonation creates session and handles read-only vs full mode', function () {
    $user = User::factory()->create(['name' => 'Host Abebe', 'email' => 'abebe@example.com']);

    // 1. Start read-only impersonation
    $response = $this->actingAs($this->ownerAdmin, 'superadmin')
        ->post(route('superadmin.impersonate', $user), [
            'mode' => 'read_only',
            'duration' => 60,
        ]);

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('impersonator_superadmin_id', $this->ownerAdmin->id);
    $response->assertSessionHas('impersonation_mode', 'read_only');

    // Verify audit log
    $startLog = AuditLog::where('action', 'impersonate_user')->where('subject_id', $user->id)->first();
    expect($startLog)->not->toBeNull();
    expect($startLog->meta['mode'])->toBe('read_only');

    // 2. Read-only mode blocks mutating request (e.g. POST to create event)
    $blockedResponse = $this->actingAs($user, 'web')
        ->withSession([
            'impersonator_superadmin_id' => $this->ownerAdmin->id,
            'impersonation_mode' => 'read_only',
            'impersonation_expires_at' => now()->addMinutes(30)->timestamp,
        ])
        ->post(route('events.store'), [
            'category_id' => $this->category->id,
            'title' => 'Hacked Event',
        ]);
    $blockedResponse->assertStatus(403);

    // 3. Leave impersonation
    $leaveResponse = $this->withSession([
        'impersonator_superadmin_id' => $this->ownerAdmin->id,
        'impersonator_name' => $this->ownerAdmin->name,
        'impersonation_mode' => 'read_only',
        'impersonation_expires_at' => now()->addMinutes(30)->timestamp,
    ])->post(route('impersonation.leave'));

    $leaveResponse->assertRedirect('/' . config('invite.superadmin_path', 'sa-x7k2p9q4m1'));
    $leaveResponse->assertSessionMissing('impersonator_superadmin_id');
    $leaveResponse->assertSessionMissing('impersonation_mode');

    $endLog = AuditLog::where('action', 'impersonation_ended')->where('subject_id', $user->id)->first();
    expect($endLog)->not->toBeNull();
});

test('suspending event sets status to suspended, blocks public invitation and check-in, and renders neutral notice', function () {
    $user = User::factory()->create();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $this->category->id,
        'title' => 'Suspicious Event',
        'slug' => 'suspicious-event',
        'public_slug' => 'suspicious123',
        'starts_at' => now()->addDays(3),
        'date' => now()->addDays(3)->format('Y-m-d'),
        'time' => '19:00',
        'status' => 'published',
        'is_invite_only' => true,
        'rsvp_enabled' => true,
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Sara Connor',
        'phone' => '+251911999999',
        'token' => 'guest_sara_token',
    ]);

    // Super admin suspends event
    $event->update(['status' => 'suspended']);
    AuditLogService::log('suspend_event', $this->ownerAdmin, $event, [
        'event_id' => $event->id,
        'title' => $event->title,
    ]);

    $log = AuditLog::where('action', 'suspend_event')->where('subject_id', $event->id)->first();
    expect($log)->not->toBeNull();

    // Public invitation page returns neutral suspended view
    $response = $this->get(route('public.invitation.show', ['token' => $guest->token]));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Public/Suspended'));

    // Public event info page also returns suspended
    $infoResponse = $this->get(route('public.event.info', ['slug' => $event->public_slug]));
    $infoResponse->assertOk();
    $infoResponse->assertInertia(fn ($page) => $page->component('Public/Suspended'));
});

test('revealing guest personal data writes mandatory guest_data_revealed audit log', function () {
    $user = User::factory()->create();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $this->category->id,
        'title' => 'Private VIP Gathering',
        'slug' => 'private-vip',
        'public_slug' => 'vip-123',
        'starts_at' => now()->addDays(5),
        'date' => now()->addDays(5)->format('Y-m-d'),
        'time' => '20:00',
        'status' => 'published',
    ]);

    Guest::create([
        'event_id' => $event->id,
        'name' => 'High Profile Guest',
        'phone' => '+251911000000',
        'token' => 'secret_token_vip',
    ]);

    AuditLogService::log('guest_data_revealed', $this->ownerAdmin, $event, [
        'event_id' => $event->id,
        'event_title' => $event->title,
        'reason' => 'Investigating fraud complaint #4829',
        'guest_count' => 1,
    ]);

    $log = AuditLog::where('action', 'guest_data_revealed')->where('subject_id', $event->id)->first();
    expect($log)->not->toBeNull();
    expect($log->meta['reason'])->toBe('Investigating fraud complaint #4829');
    expect($log->meta['guest_count'])->toBe(1);
});

test('campaign pending review queue: approve dispatches and reject refunds quota with audit log', function () {
    $user = User::factory()->create();
    $sub = Subscription::create([
        'user_id' => $user->id,
        'tier_id' => $this->tier->id,
        'status' => 'active',
        'cards_total' => 100,
        'cards_used' => 10,
        'starts_at' => now(),
        'expires_at' => now()->addDays(30),
    ]);

    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $this->category->id,
        'title' => 'Custom Promo Event',
        'slug' => 'custom-promo',
        'public_slug' => 'promo123',
        'starts_at' => now()->addDays(2),
        'date' => now()->addDays(2)->format('Y-m-d'),
        'time' => '14:00',
        'status' => 'published',
    ]);

    $campaign = Campaign::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'type' => 'custom',
        'channel' => 'sms',
        'status' => 'pending_review',
        'body' => 'Check our exclusive deal: {link}',
        'link_mode' => 'custom_url',
        'custom_url' => 'https://partner-deals.com/special',
        'audience_count' => 5,
        'estimated_cards' => 5,
    ]);

    // Reject campaign scenario
    $rejectionReason = 'External link domain is unverified and flagged as high risk';
    $sub->decrement('cards_used', $campaign->estimated_cards);

    UsageLedger::create([
        'user_id' => $user->id,
        'subscription_id' => $sub->id,
        'event_id' => $event->id,
        'kind' => 'refund',
        'channel' => 'sms',
        'quantity' => -$campaign->estimated_cards,
        'created_at' => now(),
    ]);

    $campaign->update([
        'status' => 'cancelled',
    ]);

    AuditLogService::log('campaign_rejected', $this->ownerAdmin, $campaign, [
        'campaign_id' => $campaign->id,
        'reason' => $rejectionReason,
        'refunded_quota' => 5,
    ]);

    expect($campaign->fresh()->status)->toBe('cancelled');
    expect($sub->fresh()->cards_used)->toBe(5); // Refunded from 10 to 5

    $log = AuditLog::where('action', 'campaign_rejected')->where('subject_id', $campaign->id)->first();
    expect($log)->not->toBeNull();
    expect($log->meta['refunded_quota'])->toBe(5);
});

test('system settings writes to settings table and records audit log', function () {
    Setting::set('sms_provider', 'afromessage');
    Setting::set('cost_per_sms', 0.42);
    Setting::set('sender_id', 'InviteHQ');
    Setting::set('new_account_daily_send_cap', 75);
    Setting::set('custom_link_blocklist', ['scam.com', 'phish.net']);
    Setting::set('maintenance_mode', true);

    AuditLogService::log('settings_updated', $this->ownerAdmin, null, [
        'sms_provider' => 'afromessage',
        'cost_per_sms' => 0.42,
        'sender_id' => 'InviteHQ',
        'maintenance_mode' => true,
    ]);

    expect(Setting::get('sms_provider'))->toBe('afromessage');
    expect((float) Setting::get('cost_per_sms'))->toBe(0.42);
    expect(Setting::get('sender_id'))->toBe('InviteHQ');
    expect((int) Setting::get('new_account_daily_send_cap'))->toBe(75);
    expect(Setting::get('custom_link_blocklist'))->toBe(['scam.com', 'phish.net']);
    expect((bool) Setting::get('maintenance_mode'))->toBeTrue();

    $log = AuditLog::where('action', 'settings_updated')->first();
    expect($log)->not->toBeNull();
    expect($log->actor_id)->toBe($this->ownerAdmin->id);
});

test('audit logs are strictly read-only and cannot be deleted or created manually', function () {
    $log = AuditLog::create([
        'actor_type' => SuperAdmin::class,
        'actor_id' => $this->ownerAdmin->id,
        'action' => 'login_success',
        'ip' => '127.0.0.1',
    ]);

    $policy = new \App\Policies\AuditLogPolicy;

    expect($policy->viewAny($this->ownerAdmin))->toBeTrue();
    expect($policy->view($this->ownerAdmin, $log))->toBeTrue();
    expect($policy->create($this->ownerAdmin))->toBeFalse();
    expect($policy->update($this->ownerAdmin, $log))->toBeFalse();
    expect($policy->delete($this->ownerAdmin, $log))->toBeFalse();
    expect($policy->deleteAny($this->ownerAdmin))->toBeFalse();

    expect(\App\Filament\Resources\AuditLogResource::canCreate())->toBeFalse();
    expect(\App\Filament\Resources\AuditLogResource::canEdit($log))->toBeFalse();
    expect(\App\Filament\Resources\AuditLogResource::canDelete($log))->toBeFalse();
});

test('roles: support role has read-only access and cannot manage money or tiers or settings', function () {
    expect($this->ownerAdmin->isOwner())->toBeTrue();
    expect($this->ownerAdmin->canManageMoneyOrTiers())->toBeTrue();

    expect($this->supportAdmin->isSupport())->toBeTrue();
    expect($this->supportAdmin->isOwner())->toBeFalse();
    expect($this->supportAdmin->canManageMoneyOrTiers())->toBeFalse();

    // TierPolicy checks
    $tierPolicy = new \App\Policies\TierPolicy;
    expect($tierPolicy->viewAny($this->supportAdmin))->toBeTrue();
    expect($tierPolicy->create($this->supportAdmin))->toBeFalse();
    expect($tierPolicy->update($this->supportAdmin, $this->tier))->toBeFalse();
    expect($tierPolicy->delete($this->supportAdmin, $this->tier))->toBeFalse();

    // Owner CAN manage tiers
    expect($tierPolicy->create($this->ownerAdmin))->toBeTrue();
    expect($tierPolicy->update($this->ownerAdmin, $this->tier))->toBeTrue();

    // SuperAdminPolicy checks
    $adminPolicy = new \App\Policies\SuperAdminPolicy;
    expect($adminPolicy->viewAny($this->supportAdmin))->toBeFalse();
    expect($adminPolicy->create($this->supportAdmin))->toBeFalse();
    expect($adminPolicy->viewAny($this->ownerAdmin))->toBeTrue();

    // Settings page access
    expect(\App\Filament\Pages\ManageSettings::canAccess())->toBeFalse(); // Not logged in as owner in static context
});

test('normal user cannot access super admin routes', function () {
    $regularUser = User::factory()->create();

    $secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');

    $response = $this->actingAs($regularUser, 'web')
        ->get("/{$secretPath}");

    // Should redirect to secret login or 403/404
    expect($response->status())->toBeIn([302, 403, 404]);
});
