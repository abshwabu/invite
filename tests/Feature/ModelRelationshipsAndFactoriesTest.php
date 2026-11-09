<?php

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\CheckinLog;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Scanner;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SuperAdmin;
use App\Models\Template;
use App\Models\Tier;
use App\Models\UsageLedger;
use App\Models\User;

test('all factories create valid database rows', function () {
    expect(User::factory()->create())->toBeInstanceOf(User::class);
    expect(SuperAdmin::factory()->create())->toBeInstanceOf(SuperAdmin::class);
    expect(AuditLog::factory()->create())->toBeInstanceOf(AuditLog::class);
    expect(Tier::factory()->create())->toBeInstanceOf(Tier::class);
    expect(Payment::factory()->create())->toBeInstanceOf(Payment::class);
    expect(Subscription::factory()->create())->toBeInstanceOf(Subscription::class);
    expect(Category::factory()->create())->toBeInstanceOf(Category::class);
    expect(Template::factory()->create())->toBeInstanceOf(Template::class);
    expect(Event::factory()->create())->toBeInstanceOf(Event::class);
    expect(EventMember::factory()->create())->toBeInstanceOf(EventMember::class);
    expect(Guest::factory()->create())->toBeInstanceOf(Guest::class);
    expect(Campaign::factory()->create())->toBeInstanceOf(Campaign::class);
    expect(Delivery::factory()->create())->toBeInstanceOf(Delivery::class);
    expect(Scanner::factory()->create())->toBeInstanceOf(Scanner::class);
    expect(CheckinLog::factory()->create())->toBeInstanceOf(CheckinLog::class);
    expect(UsageLedger::factory()->create())->toBeInstanceOf(UsageLedger::class);
    expect(Setting::factory()->create())->toBeInstanceOf(Setting::class);
});

test('user relationships work correctly', function () {
    $user = User::factory()->create();
    $tier = Tier::factory()->create();

    $payment = Payment::factory()->create(['user_id' => $user->id, 'tier_id' => $tier->id]);
    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'tier_id' => $tier->id,
        'payment_id' => $payment->id,
        'status' => 'active',
    ]);

    $event = Event::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['user_id' => $user->id, 'event_id' => $event->id]);
    $template = Template::factory()->create(['user_id' => $user->id]);
    $ledger = UsageLedger::factory()->create(['user_id' => $user->id, 'subscription_id' => $subscription->id]);

    $otherEvent = Event::factory()->create();
    EventMember::factory()->create([
        'event_id' => $otherEvent->id,
        'user_id' => $user->id,
        'role' => 'editor',
    ]);

    expect($user->subscriptions)->toHaveCount(1);
    expect($user->activeSubscription->id)->toBe($subscription->id);
    expect($user->payments)->toHaveCount(1);
    expect($user->events)->toHaveCount(1);
    expect($user->campaigns)->toHaveCount(1);
    expect($user->templates)->toHaveCount(1);
    expect($user->usageLedgers)->toHaveCount(1);
    expect($user->sharedEvents)->toHaveCount(1);
    expect($user->sharedEvents->first()->pivot->role)->toBe('editor');
});

test('subscription and payment relationships work correctly', function () {
    $tier = Tier::factory()->create();
    $user = User::factory()->create();
    $payment = Payment::factory()->create(['user_id' => $user->id, 'tier_id' => $tier->id]);
    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'tier_id' => $tier->id,
        'payment_id' => $payment->id,
    ]);

    expect($payment->subscription->id)->toBe($subscription->id);
    expect($subscription->payment->id)->toBe($payment->id);
    expect($subscription->tier->id)->toBe($tier->id);
    expect($subscription->user->id)->toBe($user->id);
    expect($tier->subscriptions)->toHaveCount(1);
    expect($tier->payments)->toHaveCount(1);
});

test('event relationships work correctly', function () {
    $category = Category::factory()->create();
    $template = Template::factory()->create(['category_id' => $category->id]);
    $thankyouTemplate = Template::factory()->create(['category_id' => $category->id, 'kind' => 'thank_you']);

    $event = Event::factory()->create([
        'category_id' => $category->id,
        'template_id' => $template->id,
        'thankyou_template_id' => $thankyouTemplate->id,
    ]);

    $scanner = Scanner::factory()->create(['event_id' => $event->id]);
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $campaign = Campaign::factory()->create(['event_id' => $event->id]);
    $checkin = CheckinLog::factory()->create([
        'event_id' => $event->id,
        'guest_id' => $guest->id,
        'scanner_id' => $scanner->id,
        'result' => 'success',
    ]);
    $ledger = UsageLedger::factory()->create([
        'event_id' => $event->id,
        'guest_id' => $guest->id,
    ]);

    expect($event->category->id)->toBe($category->id);
    expect($event->template->id)->toBe($template->id);
    expect($event->thankyouTemplate->id)->toBe($thankyouTemplate->id);
    expect($event->scanners)->toHaveCount(1);
    expect($event->guests)->toHaveCount(1);
    expect($event->campaigns)->toHaveCount(1);
    expect($event->checkinLogs)->toHaveCount(1);
    expect($event->usageLedgers)->toHaveCount(1);
});

test('guest, campaign and delivery relationships work correctly', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $campaign = Campaign::factory()->create(['event_id' => $event->id]);

    $delivery = Delivery::factory()->create([
        'campaign_id' => $campaign->id,
        'guest_id' => $guest->id,
        'channel' => 'sms',
        'status' => 'delivered',
    ]);

    expect($guest->deliveries)->toHaveCount(1);
    expect($guest->deliveries->first()->id)->toBe($delivery->id);
    expect($campaign->deliveries)->toHaveCount(1);
    expect($campaign->deliveries->first()->id)->toBe($delivery->id);
    expect($delivery->guest->id)->toBe($guest->id);
    expect($delivery->campaign->id)->toBe($campaign->id);
});

test('audit log polymorphic relations work', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $log = AuditLog::create([
        'actor_type' => User::class,
        'actor_id' => $user->id,
        'action' => 'created_event',
        'subject_type' => Event::class,
        'subject_id' => $event->id,
        'meta' => ['source' => 'web'],
        'ip' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'created_at' => now(),
    ]);

    expect($log->actor)->toBeInstanceOf(User::class);
    expect($log->actor->id)->toBe($user->id);
    expect($log->subject)->toBeInstanceOf(Event::class);
    expect($log->subject->id)->toBe($event->id);
});
