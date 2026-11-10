<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\OtpCode;
use App\Models\Template;
use App\Models\User;
use App\Services\PhoneNormalizer;
use App\Services\Sms\LogSmsGateway;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    LogSmsGateway::clearSent();
});

test('phone normalizer correctly normalizes Ethiopian mobile numbers', function () {
    expect(PhoneNormalizer::normalize('0911223344'))->toBe('+251911223344');
    expect(PhoneNormalizer::normalize('911223344'))->toBe('+251911223344');
    expect(PhoneNormalizer::normalize('251911223344'))->toBe('+251911223344');
    expect(PhoneNormalizer::normalize('+251911223344'))->toBe('+251911223344');
    expect(PhoneNormalizer::normalize('09 11 22 33 44'))->toBe('+251911223344');
    expect(PhoneNormalizer::normalize('+251-911-223344'))->toBe('+251911223344');

    // Safaricom Ethiopia (+251 7...)
    expect(PhoneNormalizer::normalize('0711223344'))->toBe('+251711223344');
    expect(PhoneNormalizer::normalize('711223344'))->toBe('+251711223344');
    expect(PhoneNormalizer::normalize('+251711223344'))->toBe('+251711223344');

    // Invalid numbers
    expect(PhoneNormalizer::normalize('0111223344'))->toBeNull(); // Addis landline
    expect(PhoneNormalizer::normalize('12345'))->toBeNull();
    expect(PhoneNormalizer::normalize('+14155552671'))->toBeNull(); // US number
    expect(PhoneNormalizer::normalize(''))->toBeNull();
});

test('guest can request OTP and receives SMS message', function () {
    $response = $this->postJson(route('login.otp.request'), [
        'phone' => '0912345678',
    ]);

    $response->assertSuccessful();
    $response->assertJson(['phone' => '+251912345678']);

    $this->assertDatabaseHas('otp_codes', [
        'phone' => '+251912345678',
    ]);

    $lastOtp = OtpCode::where('phone', '+251912345678')->latest('id')->first();
    expect($lastOtp)->not()->toBeNull();
    expect($lastOtp->expires_at->isFuture())->toBeTrue();

    // Verify SMS was dispatched via Log driver
    $sent = LogSmsGateway::lastMessageFor('+251912345678');
    expect($sent)->not()->toBeNull();
    expect($sent)->toContain('Your ' . config('app.name', 'Teri') . ' verification code is');
});

test('otp requests are throttled to 3 per hour per phone', function () {
    $phone = '0911002233';

    // 3 allowed requests
    for ($i = 0; $i < 3; $i++) {
        $this->postJson(route('login.otp.request'), ['phone' => $phone])
            ->assertSuccessful();
    }

    // 4th request should fail validation
    $response = $this->postJson(route('login.otp.request'), ['phone' => $phone]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors('phone');
});

test('user can verify OTP and log in', function () {
    $phone = '+251911556677';
    $code = '123456';

    OtpCode::create([
        'phone' => $phone,
        'code_hash' => Hash::make($code),
        'expires_at' => now()->addMinutes(10),
        'attempts' => 0,
    ]);

    $response = $this->post(route('login.otp.verify'), [
        'phone' => $phone,
        'code' => $code,
    ]);

    $response->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();

    $user = User::where('phone', $phone)->first();
    expect($user)->not()->toBeNull();
    expect($user->country)->toBe('ET');
    expect($user->last_login_at)->not()->toBeNull();

    $otp = OtpCode::where('phone', $phone)->first();
    expect($otp->verified_at)->not()->toBeNull();
});

test('invalid OTP code is rejected', function () {
    $phone = '+251911889900';

    OtpCode::create([
        'phone' => $phone,
        'code_hash' => Hash::make('654321'),
        'expires_at' => now()->addMinutes(10),
        'attempts' => 0,
    ]);

    $response = $this->post(route('login.otp.verify'), [
        'phone' => $phone,
        'code' => '000000',
    ]);

    $response->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('suspended users are blocked from logging in with email and password', function () {
    $user = User::factory()->create([
        'email' => 'suspended@example.com',
        'password' => Hash::make('password123'),
        'is_suspended' => true,
    ]);

    $response = $this->post(route('login'), [
        'email' => 'suspended@example.com',
        'password' => 'password123',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('suspended users are blocked from logging in via OTP', function () {
    $phone = '+251911998877';

    $user = User::factory()->create([
        'phone' => $phone,
        'is_suspended' => true,
    ]);

    OtpCode::create([
        'phone' => $phone,
        'code_hash' => Hash::make('112233'),
        'expires_at' => now()->addMinutes(10),
        'attempts' => 0,
    ]);

    $response = $this->post(route('login.otp.verify'), [
        'phone' => $phone,
        'code' => '112233',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('registration records IP, country, source, and last_login_at', function () {
    $response = $this->post(route('register'), [
        'name' => 'Almaz Ayana',
        'email' => 'almaz@example.com',
        'phone' => '0912334455',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'utm_source' => 'telegram_ad',
    ]);

    $response->assertRedirect(route('dashboard'));

    $user = User::where('email', 'almaz@example.com')->first();
    expect($user)->not()->toBeNull();
    expect($user->phone)->toBe('+251912334455');
    expect($user->country)->toBe('ET');
    expect($user->registered_ip)->not()->toBeEmpty();
    expect($user->registration_source)->toBe('telegram_ad');
    expect($user->last_login_at)->not()->toBeNull();
});

test('unauthenticated visitor can view template gallery and open editor as guest', function () {
    $category = Category::where('slug', 'wedding')->first() ?? Category::factory()->create(['slug' => 'wedding']);
    $template = Template::factory()->create(['category_id' => $category->id]);

    $this->get(route('templates.index'))
        ->assertSuccessful();

    $this->get(route('editor.show', ['template' => $template->id]))
        ->assertSuccessful();
});

test('guest draft is automatically imported on signup and visible in dashboard', function () {
    $category = Category::where('slug', 'wedding')->first() ?? Category::factory()->create(['slug' => 'wedding']);
    $template = Template::factory()->create(['category_id' => $category->id]);

    $guestDraft = [
        'template_id' => $template->id,
        'category_id' => $category->id,
        'title' => 'Tadesse & Aster Wedding Invitation',
        'venue_name' => 'Skylight Hotel',
        'venue_address' => 'Bole Airport Road, Addis Ababa',
        'host_names' => 'Tadesse & Aster',
        'starts_at' => '2027-02-14T15:00:00',
        'design_json' => [
            'theme' => 'gold',
            'heading' => 'የጋብቻ ጥሪ',
            'backgroundColor' => '#1A1A1A',
        ],
    ];

    $response = $this->post(route('register'), [
        'name' => 'Tadesse Bekele',
        'email' => 'tadesse@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'draft' => $guestDraft,
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = User::where('email', 'tadesse@example.com')->first();

    $event = Event::where('user_id', $user->id)->first();
    expect($event)->not()->toBeNull();
    expect($event->title)->toBe('Tadesse & Aster Wedding Invitation');
    expect($event->venue_name)->toBe('Skylight Hotel');
    expect($event->status)->toBe('draft');

    // Dashboard returns the event
    $dashboardResponse = $this->get(route('dashboard'));
    $dashboardResponse->assertSuccessful();
});

test('authenticated user can import draft via events import-draft endpoint', function () {
    $user = User::factory()->create();
    $category = Category::first() ?? Category::factory()->create();

    $draft = [
        'category_id' => $category->id,
        'title' => 'Birthday Bash for Selam',
        'venue_name' => 'Bole Sky Bar',
        'host_names' => 'Selamawit',
        'design_json' => [
            'heading' => 'Birthday Party',
            'theme' => 'party',
        ],
    ];

    $response = $this->actingAs($user)->post(route('events.import-draft'), [
        'draft' => $draft,
    ]);

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('events', [
        'user_id' => $user->id,
        'title' => 'Birthday Bash for Selam',
        'venue_name' => 'Bole Sky Bar',
    ]);
});

test('language switcher persists locale to user and cookie', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($user)->post(route('locale.update'), [
        'locale' => 'am',
    ]);

    $response->assertRedirect();
    $response->assertCookie('locale', 'am');

    $user->refresh();
    expect($user->locale)->toBe('am');
});

test('user profile can be updated with phone and locale', function () {
    $user = User::factory()->create([
        'phone' => null,
        'locale' => 'en',
    ]);

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Updated Name',
        'email' => $user->email,
        'phone' => '0911223344',
        'locale' => 'am',
    ]);

    $response->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->name)->toBe('Updated Name');
    expect($user->phone)->toBe('+251911223344');
    expect($user->locale)->toBe('am');
});

test('deleting user account cascades and removes user events', function () {
    $user = User::factory()->create([
        'password' => Hash::make('secretpass'),
    ]);

    $event = Event::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->delete(route('profile.destroy'), [
        'password' => 'secretpass',
    ]);

    $response->assertRedirect('/');
    $this->assertGuest();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});
