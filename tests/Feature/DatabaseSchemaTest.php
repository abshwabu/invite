<?php

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('all required tables exist', function () {
    $tables = [
        'users',
        'super_admins',
        'audit_logs',
        'tiers',
        'subscriptions',
        'usage_ledger',
        'payments',
        'categories',
        'templates',
        'events',
        'event_members',
        'guests',
        'campaigns',
        'deliveries',
        'scanners',
        'checkin_logs',
        'settings',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Expected table [{$table}] to exist in the database.");
    }
});

test('tables have required columns', function () {
    expect(Schema::hasColumns('users', [
        'id', 'name', 'email', 'phone', 'password', 'locale', 'country', 'is_suspended', 'last_login_at', 'registered_ip', 'registration_source',
    ]))->toBeTrue();

    expect(Schema::hasColumns('super_admins', [
        'id', 'name', 'email', 'password', 'two_factor_secret', 'two_factor_confirmed_at', 'last_login_at', 'last_login_ip',
    ]))->toBeTrue();

    expect(Schema::hasColumns('audit_logs', [
        'id', 'actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'meta', 'ip', 'user_agent', 'created_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('tiers', [
        'id', 'slug', 'name_en', 'name_am', 'description_en', 'description_am', 'price_amount', 'currency', 'card_quota', 'validity_days', 'max_events', 'max_guests_per_event', 'max_scanners_per_event', 'sms_weight', 'email_weight', 'thank_you_consumes_quota', 'features', 'sort', 'is_active', 'is_public', 'is_default',
    ]))->toBeTrue();

    expect(Schema::hasColumns('subscriptions', [
        'id', 'user_id', 'tier_id', 'cards_total', 'cards_used', 'starts_at', 'expires_at', 'status', 'source', 'payment_id', 'note',
    ]))->toBeTrue();

    expect(Schema::hasColumns('usage_ledger', [
        'id', 'user_id', 'subscription_id', 'event_id', 'guest_id', 'campaign_id', 'kind', 'channel', 'quantity', 'created_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('payments', [
        'id', 'user_id', 'tier_id', 'amount', 'currency', 'provider', 'provider_ref', 'status', 'payload', 'paid_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('categories', [
        'id', 'slug', 'name_en', 'name_am', 'sort',
    ]))->toBeTrue();

    expect(Schema::hasColumns('templates', [
        'id', 'category_id', 'kind', 'name', 'preview_path', 'design_json', 'width', 'height', 'is_system', 'user_id', 'is_active', 'sort',
    ]))->toBeTrue();

    expect(Schema::hasColumns('events', [
        'id', 'user_id', 'category_id', 'title', 'description', 'starts_at', 'ends_at', 'timezone', 'venue_name', 'venue_address', 'map_url', 'host_names', 'is_invite_only', 'rsvp_enabled', 'plus_ones_allowed', 'design_json', 'template_id', 'thankyou_design_json', 'thankyou_template_id', 'status',
    ]))->toBeTrue();

    expect(Schema::hasColumns('event_members', [
        'id', 'event_id', 'user_id', 'role',
    ]))->toBeTrue();

    expect(Schema::hasColumns('guests', [
        'id', 'event_id', 'name', 'phone', 'email', 'token', 'preferred_channel', 'max_party_size', 'rsvp_status', 'rsvp_party_size', 'rsvp_note', 'opted_out', 'viewed_at', 'checked_in_at', 'checked_in_by',
    ]))->toBeTrue();

    expect(Schema::hasColumns('campaigns', [
        'id', 'event_id', 'user_id', 'type', 'channel', 'subject', 'body', 'link_mode', 'custom_url', 'audience', 'audience_count', 'scheduled_at', 'status', 'estimated_cards', 'created_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('deliveries', [
        'id', 'campaign_id', 'guest_id', 'channel', 'status', 'provider_message_id', 'error', 'attempts', 'sent_at', 'quota_charged',
    ]))->toBeTrue();

    expect(Schema::hasColumns('scanners', [
        'id', 'event_id', 'name', 'pin_hash', 'token', 'revoked_at', 'failed_attempts', 'locked_until', 'last_used_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('checkin_logs', [
        'id', 'event_id', 'guest_id', 'scanner_id', 'result', 'scanned_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('settings', [
        'id', 'key', 'value',
    ]))->toBeTrue();
});

test('required database indexes exist', function () {
    $hasIndex = function (string $table, array $columns) {
        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $index) {
            if ($index['columns'] === $columns) {
                return true;
            }
        }
        return false;
    };

    expect($hasIndex('guests', ['event_id']))->toBeTrue('Index on guests(event_id) missing');
    expect($hasIndex('guests', ['token']))->toBeTrue('Index on guests(token) missing');
    expect($hasIndex('deliveries', ['guest_id', 'status']))->toBeTrue('Index on deliveries(guest_id, status) missing');
    expect($hasIndex('deliveries', ['campaign_id']))->toBeTrue('Index on deliveries(campaign_id) missing');
    expect($hasIndex('checkin_logs', ['event_id']))->toBeTrue('Index on checkin_logs(event_id) missing');
    expect($hasIndex('usage_ledger', ['user_id', 'created_at']))->toBeTrue('Index on usage_ledger(user_id, created_at) missing');
    expect($hasIndex('subscriptions', ['user_id', 'status']))->toBeTrue('Index on subscriptions(user_id, status) missing');
});

test('sqlite configuration pragmas are set', function () {
    if (DB::getDriverName() === 'sqlite') {
        $journalMode = DB::selectOne('PRAGMA journal_mode');
        expect(strtolower($journalMode->journal_mode))->toBe('wal');

        $busyTimeout = DB::selectOne('PRAGMA busy_timeout');
        expect((int) $busyTimeout->timeout)->toBe(5000);

        $foreignKeys = DB::selectOne('PRAGMA foreign_keys');
        expect((int) $foreignKeys->foreign_keys)->toBe(1);

        $synchronous = DB::selectOne('PRAGMA synchronous');
        expect((int) $synchronous->synchronous)->toBe(1); // 1 = NORMAL
    }
});

test('invite configuration is properly loaded', function () {
    expect(config('invite.superadmin_path'))->not()->toBeEmpty();
    expect(config('invite.short_link_domain'))->not()->toBeEmpty();
    expect(config('invite.sms.default_provider'))->toBe('afromessage');
    expect(config('invite.sms.default_country_code'))->toBe('+251');
    expect(config('invite.guest_mode.default_max_party_size'))->toBe(1);
    expect(config('invite.guest_mode.token_length'))->toBe(32);
});

test('category seeder seeds all 10 required categories', function () {
    $this->artisan('db:seed', ['--class' => 'CategorySeeder'])->assertSuccessful();

    $expectedSlugs = [
        'wedding',
        'funeral',
        'birthday',
        'graduation',
        'baby-shower',
        'religious',
        'corporate',
        'anniversary',
        'housewarming',
        'other',
    ];

    foreach ($expectedSlugs as $slug) {
        $category = Category::where('slug', $slug)->first();
        expect($category)->not()->toBeNull("Category with slug [{$slug}] was not seeded");
        expect($category->name_en)->not()->toBeEmpty();
        expect($category->name_am)->not()->toBeEmpty();
    }
});
