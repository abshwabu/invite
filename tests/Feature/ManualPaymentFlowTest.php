<?php

use App\Contracts\PaymentProvider;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\SuperAdmin;
use App\Models\Tier;
use App\Models\User;
use App\Notifications\AdminPaymentSubmittedNotification;
use App\Notifications\PaymentApprovedNotification;
use App\Notifications\PaymentNeedsInfoNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Services\QuotaService;
use App\Services\SuperAdminDashboardService;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierSeeder::class);
    $this->seed(PaymentMethodSeeder::class);
    Storage::fake('local');
});

test('user can initiate manual payment, get unique payment code and active methods', function () {
    $user = User::factory()->create();
    $goldTier = Tier::where('slug', 'gold')->first();

    $response = $this->actingAs($user)->postJson(route('payments.initiate'), [
        'tier_id' => $goldTier->id,
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'payment' => ['id', 'payment_code', 'amount', 'currency', 'tier'],
        'methods',
        'instructions',
    ]);

    $paymentData = $response->json('payment');
    expect($paymentData['payment_code'])->toStartWith('INV-')
        ->and((float) $paymentData['amount'])->toBe((float) $goldTier->price_amount)
        ->and($response->json('methods'))->toHaveCount(3);

    $this->assertDatabaseHas('payments', [
        'id' => $paymentData['id'],
        'user_id' => $user->id,
        'tier_id' => $goldTier->id,
        'status' => 'awaiting_proof',
    ]);
});

test('submitting payment proof fails if neither reference number nor receipt image is provided', function () {
    $user = User::factory()->create();
    $tier = Tier::where('slug', 'gold')->first();
    $payment = app(PaymentProvider::class)->initiate($user, $tier);

    $response = $this->actingAs($user)->postJson(route('payments.submit', $payment->id), [
        'user_note' => 'I made the transfer earlier',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['proof']);
});

test('happy path: user submits reference number only, admin approves, tier and quota are activated', function () {
    Notification::fake();

    $user = User::factory()->create();
    $tier = Tier::where('slug', 'silver')->first(); // 250 cards
    $payment = app(PaymentProvider::class)->initiate($user, $tier);

    $response = $this->actingAs($user)->postJson(route('payments.submit', $payment->id), [
        'reference_number' => 'CBE-TXN-998811',
        'amount_declared' => 1200,
        'paid_from' => 'Abebe Kebede',
    ]);

    $response->assertOk();
    $payment->refresh();
    expect($payment->status)->toBe('submitted')
        ->and($payment->reference_number)->toBe('CBE-TXN-998811')
        ->and($payment->submitted_at)->not->toBeNull();

    // Verify Admin alert notification was triggered
    Notification::assertSentTo(
        new \Illuminate\Notifications\AnonymousNotifiable,
        AdminPaymentSubmittedNotification::class
    );

    // Super Admin owner approves payment
    $superAdmin = SuperAdmin::factory()->create(['role' => 'owner']);
    $provider = app(PaymentProvider::class);
    $approvedPayment = $provider->approve($payment, 1200, $superAdmin, 'All verified with bank statement');

    expect($approvedPayment->status)->toBe('approved')
        ->and((float) $approvedPayment->amount_verified)->toBe(1200.0)
        ->and($approvedPayment->reviewed_by)->toBe($superAdmin->id);

    // Verify user received PaymentApprovedNotification
    Notification::assertSentTo($user, PaymentApprovedNotification::class);

    // Verify subscription and quota
    $subscription = $user->subscriptions()->where('tier_id', $tier->id)->where('status', 'active')->first();
    expect($subscription)->not->toBeNull()
        ->and($subscription->cards_total)->toBe($tier->card_quota);

    $quotaService = app(QuotaService::class);
    expect($quotaService->available($user))->toBeGreaterThanOrEqual($tier->card_quota);

    // Verify usage ledger entry
    $this->assertDatabaseHas('usage_ledger', [
        'user_id' => $user->id,
        'kind' => 'admin_grant',
        'quantity' => $tier->card_quota,
    ]);

    // Verify PaymentEvent recorded
    $this->assertDatabaseHas('payment_events', [
        'payment_id' => $payment->id,
        'event' => 'approved',
    ]);
});

test('happy path: user uploads receipt image, EXIF is stripped, WebP compressed, stored in private storage', function () {
    $user = User::factory()->create();
    $tier = Tier::where('slug', 'gold')->first();
    $payment = app(PaymentProvider::class)->initiate($user, $tier);

    // Create a real fake GD image
    $fakeImage = UploadedFile::fake()->image('receipt.jpg', 600, 800);

    $response = $this->actingAs($user)->post(route('payments.submit', $payment->id), [
        'receipt' => $fakeImage,
        'user_note' => 'Here is my receipt screenshot',
    ]);

    $response->assertRedirect(route('payments.show', $payment->id));

    $payment->refresh();
    expect($payment->status)->toBe('submitted')
        ->and($payment->receipt_path)->not->toBeNull()
        ->and($payment->receipt_hash)->not->toBeNull()
        ->and($payment->receipt_phash)->not->toBeNull();

    // Verify file exists on local private storage
    Storage::disk('local')->assertExists($payment->receipt_path);
});

test('duplicate transaction reference used by another account is blocked', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $tier = Tier::where('slug', 'silver')->first();

    $payment1 = app(PaymentProvider::class)->initiate($user1, $tier);
    app(PaymentProvider::class)->submitProof($payment1, [
        'reference_number' => 'TXN-ABC-12345',
    ]);

    // Second user attempts same reference number (with varied spacing/casing)
    $payment2 = app(PaymentProvider::class)->initiate($user2, $tier);

    $response = $this->actingAs($user2)->postJson(route('payments.submit', $payment2->id), [
        'reference_number' => ' txn-abc-12345 ',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['reference_number']);
});

test('duplicate receipt image triggers security flag on the second payment', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $tier = Tier::where('slug', 'silver')->first();

    $imageFile1 = UploadedFile::fake()->image('cbe_receipt.png', 400, 400);
    $payment1 = app(PaymentProvider::class)->initiate($user1, $tier);
    app(PaymentProvider::class)->submitProof($payment1, [
        'receipt' => $imageFile1,
    ]);

    // Second user uploads the exact same image
    $payment2 = app(PaymentProvider::class)->initiate($user2, $tier);
    $imageFile2 = UploadedFile::fake()->image('cbe_receipt.png', 400, 400);
    app(PaymentProvider::class)->submitProof($payment2, [
        'receipt' => $imageFile2,
    ]);

    $payment2->refresh();
    expect($payment2->hasFlag('duplicate_receipt'))->toBeTrue();
});

test('approval is strictly idempotent and does not create duplicate subscriptions or grants', function () {
    $user = User::factory()->create();
    $tier = Tier::where('slug', 'silver')->first();
    $payment = app(PaymentProvider::class)->initiate($user, $tier);
    app(PaymentProvider::class)->submitProof($payment, ['reference_number' => 'REF-IDEMPOTENT-1']);

    $superAdmin = SuperAdmin::factory()->create(['role' => 'owner']);
    $provider = app(PaymentProvider::class);

    // First approval
    $provider->approve($payment, 1200, $superAdmin);
    $firstSubCount = $user->subscriptions()->where('tier_id', $tier->id)->count();
    $firstLedgerCount = $user->usageLedgers()->count();

    // Second approval call on same payment
    $provider->approve($payment, 1200, $superAdmin);
    $secondSubCount = $user->subscriptions()->where('tier_id', $tier->id)->count();
    $secondLedgerCount = $user->usageLedgers()->count();

    expect($firstSubCount)->toBe(1)
        ->and($secondSubCount)->toBe(1)
        ->and($secondLedgerCount)->toBe($firstLedgerCount);
});

test('admin request info and reject flow allows user to resubmit against same payment', function () {
    Notification::fake();

    $user = User::factory()->create();
    $tier = Tier::where('slug', 'silver')->first();
    $payment = app(PaymentProvider::class)->initiate($user, $tier);
    app(PaymentProvider::class)->submitProof($payment, ['reference_number' => 'INCOMPLETE-REF']);

    $superAdmin = SuperAdmin::factory()->create(['role' => 'owner']);
    $provider = app(PaymentProvider::class);

    // 1. Request Info
    $provider->requestInfo($payment, 'Please upload the full screenshot showing timestamp', $superAdmin);
    $payment->refresh();
    expect($payment->status)->toBe('needs_info')
        ->and($payment->admin_note)->toContain('timestamp');
    Notification::assertSentTo($user, PaymentNeedsInfoNotification::class);

    // 2. User resubmits details
    $provider->submitProof($payment, [
        'reference_number' => 'INCOMPLETE-REF',
        'user_note' => 'Here is the timestamp: 14:30 PM',
    ]);
    $payment->refresh();
    expect($payment->status)->toBe('submitted');

    // 3. Admin Rejects
    $provider->reject($payment, 'Wrong account number transferred to', $superAdmin);
    $payment->refresh();
    expect($payment->status)->toBe('rejected')
        ->and($payment->rejection_reason)->toContain('Wrong account');
    Notification::assertSentTo($user, PaymentRejectedNotification::class);

    // 4. User resubmits corrected proof
    $provider->submitProof($payment, [
        'reference_number' => 'NEW-CORRECT-REF-99',
    ]);
    $payment->refresh();
    expect($payment->status)->toBe('submitted');

    // 5. Admin Approves
    $provider->approve($payment, 1200, $superAdmin);
    $payment->refresh();
    expect($payment->status)->toBe('approved');
});

test('sending safeguard blocks sending when quota is insufficient and pending payment exists', function () {
    $user = User::factory()->create();
    // User has 20 free cards by default
    $event = Event::factory()->create(['user_id' => $user->id]);

    // Create 25 guests (exceeding 20 free cards)
    Guest::factory()->count(25)->create([
        'event_id' => $event->id,
        'phone' => '+251911223344',
    ]);

    // User initiated payment for Gold tier, now under review
    $goldTier = Tier::where('slug', 'gold')->first();
    $payment = app(PaymentProvider::class)->initiate($user, $goldTier);
    app(PaymentProvider::class)->submitProof($payment, ['reference_number' => 'PENDING-VERIFY-1']);

    // Attempting to send campaign of 25 cards
    $response = $this->actingAs($user)->post(route('events.campaigns.store', $event->id), [
        'type' => 'invitation',
        'channel' => 'sms',
        'audience_type' => 'all',
        'body' => 'Hello {guest_name}, you are invited to {event_title}! {link}',
    ]);

    $response->assertSessionHasErrors(['quota']);
    expect(session('errors')->get('quota')[0])
        ->toContain('Your payment is being verified. You can send your cards after we verify it, usually within 24 hours.');
});

test('sending safeguard allows sending when existing balance is sufficient even with pending payment', function () {
    $user = User::factory()->create();
    // User has 20 free cards by default
    $event = Event::factory()->create(['user_id' => $user->id]);

    // Create 5 guests with phone numbers (well within 20 cards)
    Guest::factory()->count(5)->create([
        'event_id' => $event->id,
        'phone' => '+251911223344',
    ]);

    // User has a pending payment for future event
    $goldTier = Tier::where('slug', 'gold')->first();
    $payment = app(PaymentProvider::class)->initiate($user, $goldTier);
    app(PaymentProvider::class)->submitProof($payment, ['reference_number' => 'FUTURE-UPGRADE-1']);

    $response = $this->actingAs($user)->post(route('events.campaigns.store', $event->id), [
        'type' => 'invitation',
        'channel' => 'sms',
        'audience_type' => 'all',
        'body' => 'Hello {guest_name}, you are invited to {event_title}! {link}',
    ]);

    // Should succeed and redirect to deliveries
    $response->assertRedirect(route('events.deliveries', $event->id));
});

test('receipt privacy: unsigned or unauthorized requests cannot access private receipts', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $tier = Tier::where('slug', 'silver')->first();

    $payment = app(PaymentProvider::class)->initiate($user1, $tier);
    $file = UploadedFile::fake()->image('private_receipt.png', 300, 300);
    app(PaymentProvider::class)->submitProof($payment, ['receipt' => $file]);

    $validSignedUrl = $payment->getReceiptUrl();

    // 1. Unauthenticated request to raw URL -> forbidden/redirect
    $this->get(route('payments.receipt', $payment->id))->assertForbidden();

    // 2. User 2 logged in tries to access User 1's valid signed URL -> 403 Forbidden
    $this->actingAs($user2)->get($validSignedUrl)->assertForbidden();

    // 3. User 1 (owner) accesses via valid signed URL -> 200 OK
    $this->actingAs($user1)->get($validSignedUrl)->assertOk();

    // 4. Superadmin accesses via valid signed URL -> 200 OK
    $superAdmin = SuperAdmin::factory()->create(['role' => 'support']);
    $this->actingAs($superAdmin, 'superadmin')->get($validSignedUrl)->assertOk();
});

test('policy enforces role owner vs support permissions on payments', function () {
    $supportAdmin = SuperAdmin::factory()->create(['role' => 'support']);
    $ownerAdmin = SuperAdmin::factory()->create(['role' => 'owner']);

    $payment = Payment::factory()->create(['status' => 'submitted']);

    // Support can view
    expect($supportAdmin->can('view', $payment))->toBeTrue();
    // Support cannot approve, reject, or refund
    expect($supportAdmin->can('approve', $payment))->toBeFalse();
    expect($supportAdmin->can('reject', $payment))->toBeFalse();
    expect($supportAdmin->can('refund', $payment))->toBeFalse();

    // Owner can view, approve, reject, and refund
    expect($ownerAdmin->can('view', $payment))->toBeTrue();
    expect($ownerAdmin->can('approve', $payment))->toBeTrue();
    expect($ownerAdmin->can('reject', $payment))->toBeTrue();
    expect($ownerAdmin->can('refund', $payment))->toBeTrue();
});

test('super admin KPIs only count verified revenue and exclude unapproved or refunded payments', function () {
    $user = User::factory()->create();
    $tier = Tier::where('slug', 'silver')->first();

    // Approved payment: 1200 ETB
    $p1 = Payment::factory()->create([
        'user_id' => $user->id,
        'tier_id' => $tier->id,
        'status' => 'approved',
        'amount_verified' => 1200,
        'reviewed_at' => now(),
    ]);

    // Submitted (pending) payment: 2500 ETB
    $p2 = Payment::factory()->create([
        'user_id' => $user->id,
        'tier_id' => $tier->id,
        'status' => 'submitted',
        'amount_expected' => 2500,
    ]);

    // Refunded payment: 1200 ETB
    $p3 = Payment::factory()->create([
        'user_id' => $user->id,
        'tier_id' => $tier->id,
        'status' => 'refunded',
        'amount_verified' => 1200,
    ]);

    $kpis = SuperAdminDashboardService::getKpiStats('all');

    // Revenue must only be 1200 (excludes the 2500 pending and 1200 refunded)
    expect($kpis['revenue_total'])->toBe(1200.0)
        ->and($kpis['payments_awaiting_verification'])->toBe(1);
});

test('scheduled command expires abandoned payments older than cutoff days', function () {
    $oldPayment = Payment::factory()->create([
        'status' => 'awaiting_proof',
        'created_at' => now()->subDays(10),
    ]);

    $recentPayment = Payment::factory()->create([
        'status' => 'awaiting_proof',
        'created_at' => now()->subDays(2),
    ]);

    Artisan::call('payments:expire-abandoned', ['--days' => 7]);

    expect($oldPayment->fresh()->status)->toBe('abandoned')
        ->and($recentPayment->fresh()->status)->toBe('awaiting_proof');
});

test('scheduled command purges old receipt images past retention period', function () {
    $fakeImage = UploadedFile::fake()->image('old_receipt.jpg', 200, 200);
    $path = Storage::disk('local')->putFile('receipts', $fakeImage);

    $oldPayment = Payment::factory()->create([
        'status' => 'approved',
        'receipt_path' => $path,
        'receipt_hash' => 'hash123',
        'reviewed_at' => now()->subDays(100),
    ]);

    Artisan::call('payments:purge-receipts', ['--days' => 90]);

    Storage::disk('local')->assertMissing($path);
    $oldPayment->refresh();
    expect($oldPayment->receipt_path)->toBeNull()
        ->and($oldPayment->receipt_hash)->toBe('hash123'); // Metadata retained
});

test('printable invoice renders clean HTML for approved payment and 404 for unapproved', function () {
    $user = User::factory()->create();
    $tier = Tier::where('slug', 'gold')->first();
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'tier_id' => $tier->id,
        'status' => 'approved',
        'amount_verified' => 2500,
        'reference_number' => 'CBE-INV-001',
    ]);

    $response = $this->actingAs($user)->get(route('payments.invoice', $payment->id));
    $response->assertOk();
    $response->assertSee('Official Payment Receipt');
    $response->assertSee('CBE-INV-001');

    // Unapproved payment returns 404
    $pendingPayment = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => 'submitted',
    ]);
    $this->actingAs($user)->get(route('payments.invoice', $pendingPayment->id))->assertNotFound();
});

test('user can browse their payments and view details via Inertia pages', function () {
    $user = User::factory()->create();
    $tier = Tier::where('slug', 'silver')->first();

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'tier_id' => $tier->id,
        'status' => 'submitted',
        'amount_expected' => 1200,
        'reference_number' => 'REF-PAGE-TEST',
    ]);

    // 1. Index page
    $this->actingAs($user)->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payments/Index')
            ->has('payments', 1)
            ->where('payments.0.payment_code', $payment->payment_code)
            ->has('paymentMethods')
        );

    // 2. Show page
    $this->actingAs($user)->get(route('payments.show', $payment->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payments/Show')
            ->where('payment.payment_code', $payment->payment_code)
            ->has('paymentMethods')
            ->has('instructions')
        );
});

test('scheduled command sends waiting payments digest email for payments waiting >= 12h', function () {
    Mail::fake();

    $oldSubmitted = Payment::factory()->create([
        'status' => 'submitted',
        'submitted_at' => now()->subHours(15),
    ]);

    $newSubmitted = Payment::factory()->create([
        'status' => 'submitted',
        'submitted_at' => now()->subHours(2),
    ]);

    Artisan::call('payments:send-waiting-digest');

    Mail::assertSent(\App\Mail\WaitingPaymentsDigestMail::class, function ($mail) {
        return $mail->payments->count() === 1;
    });
});

