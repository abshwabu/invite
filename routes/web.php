<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EditorController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventDashboardController;
use App\Http\Controllers\EventDesignController;
use App\Http\Controllers\EventDraftController;
use App\Http\Controllers\EventMemberController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicInvitationController;
use App\Http\Controllers\PublicThankYouController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\ThankYouController;
use App\Http\Controllers\UsageController;
use App\Models\Category;
use App\Models\Template;
use App\Models\Tier;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $categories = Category::withCount(['templates' => fn ($q) => $q->where('kind', 'invitation')->where('is_active', true)])
        ->orderBy('sort')
        ->get(['id', 'slug', 'name_en', 'name_am']);

    $heroTemplates = [
        'wedding' => Template::where('kind', 'invitation')->whereHas('category', fn ($q) => $q->where('slug', 'wedding'))->first(),
        'funeral' => Template::where('kind', 'invitation')->whereHas('category', fn ($q) => $q->where('slug', 'funeral'))->first(),
        'birthday' => Template::where('kind', 'invitation')->whereHas('category', fn ($q) => $q->where('slug', 'birthday'))->first(),
        'eid' => Template::where('kind', 'invitation')->whereJsonContains('tags', 'eid')->first() ?? Template::where('kind', 'invitation')->whereHas('category', fn ($q) => $q->where('slug', 'religious'))->first(),
        'meskel' => Template::where('kind', 'invitation')->whereJsonContains('tags', 'meskel')->first() ?? Template::where('kind', 'invitation')->whereHas('category', fn ($q) => $q->where('slug', 'religious'))->skip(4)->first(),
        'graduation' => Template::where('kind', 'invitation')->whereHas('category', fn ($q) => $q->where('slug', 'graduation'))->first(),
    ];

    $shelfTemplates = Template::with('category:id,slug,name_en,name_am')
        ->where('kind', 'invitation')
        ->where('is_active', true)
        ->orderBy('sort')
        ->get([
            'id',
            'category_id',
            'name',
            'preview_path',
            'preview_path_am',
            'palette',
            'spec',
            'tags',
            'design_json',
        ]);

    $tiers = Tier::where('is_active', true)
        ->where('is_public', true)
        ->orderBy('sort')
        ->get();

    return Inertia::render('Welcome', [
        'appName' => config('app.name', 'Invite'),
        'categories' => $categories,
        'heroTemplates' => $heroTemplates,
        'shelfTemplates' => $shelfTemplates,
        'tiers' => $tiers,
    ]);
})->name('home');

Route::get('/sitemap.xml', function () {
    $baseUrl = url('/');
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    
    // Home
    $xml .= "    <url>\n";
    $xml .= "        <loc>{$baseUrl}</loc>\n";
    $xml .= "        <xhtml:link rel=\"alternate\" hreflang=\"en\" href=\"{$baseUrl}\" />\n";
    $xml .= "        <xhtml:link rel=\"alternate\" hreflang=\"am\" href=\"{$baseUrl}?lang=am\" />\n";
    $xml .= "        <changefreq>daily</changefreq>\n";
    $xml .= "        <priority>1.0</priority>\n";
    $xml .= "    </url>\n";

    // Templates
    $xml .= "    <url>\n";
    $xml .= "        <loc>{$baseUrl}/templates</loc>\n";
    $xml .= "        <changefreq>weekly</changefreq>\n";
    $xml .= "        <priority>0.8</priority>\n";
    $xml .= "    </url>\n";

    // Guest Editor
    $xml .= "    <url>\n";
    $xml .= "        <loc>{$baseUrl}/editor</loc>\n";
    $xml .= "        <changefreq>weekly</changefreq>\n";
    $xml .= "        <priority>0.8</priority>\n";
    $xml .= "    </url>\n";

    $xml .= '</urlset>';

    return response($xml, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

// Template gallery (accessible by guests)
Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');

// Editor (accessible by guests and users)
Route::get('/editor/{template?}', [EditorController::class, 'show'])->name('editor.show');

// Authenticated user routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Plan & Quota management
    Route::get('/plan', [PlanController::class, 'index'])->name('plan.index');
    Route::post('/plan/upgrade-request', [PlanController::class, 'upgradeRequest'])->name('plan.upgrade-request');

    // Event CRUD and Wizard
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::patch('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::post('/events/{event}/duplicate', [EventController::class, 'duplicate'])->name('events.duplicate');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    // Guest Management
    Route::get('/events/{event}/guests', [GuestController::class, 'index'])->name('events.guests.index');
    Route::post('/events/{event}/guests', [GuestController::class, 'store'])->name('events.guests.store');
    Route::post('/events/{event}/guests/bulk-paste', [GuestController::class, 'bulkPaste'])->name('events.guests.bulk-paste');
    Route::post('/events/{event}/guests/import', [GuestController::class, 'import'])->name('events.guests.import');
    Route::patch('/events/{event}/guests/{guest}', [GuestController::class, 'update'])->name('events.guests.update');
    Route::delete('/events/{event}/guests/{guest}', [GuestController::class, 'destroy'])->name('events.guests.destroy');
    Route::post('/events/{event}/guests/bulk-delete', [GuestController::class, 'bulkDelete'])->name('events.guests.bulk-delete');
    Route::post('/events/{event}/guests/bulk-channel', [GuestController::class, 'bulkChannel'])->name('events.guests.bulk-channel');
    Route::get('/events/{event}/guests/export', [GuestController::class, 'export'])->name('events.guests.export');

    // Co-host members
    Route::post('/events/{event}/members', [EventMemberController::class, 'store'])->name('events.members.store');
    Route::delete('/events/{event}/members/{user}', [EventMemberController::class, 'destroy'])->name('events.members.destroy');

    // Event Card Editor routes
    Route::get('/events/{event}/design', [EventDesignController::class, 'showInvitation'])->name('events.design');
    Route::patch('/events/{event}/design', [EventDesignController::class, 'updateInvitation'])->name('events.design.update');
    Route::get('/events/{event}/thank-you-design', [EventDesignController::class, 'showThankYou'])->name('events.thank-you-design');
    Route::patch('/events/{event}/thank-you-design', [EventDesignController::class, 'updateThankYou'])->name('events.thank-you-design.update');
    Route::post('/editor/upload-image', [EventDesignController::class, 'uploadImage'])->name('editor.upload-image');

    // Template custom upload (gated by upload_templates)
    Route::post('/templates/upload', [TemplateController::class, 'upload'])->name('templates.upload');

    // Import guest draft into real event
    Route::post('/events/import-draft', [EventDraftController::class, 'import'])->name('events.import-draft');

    // Campaign & Delivery pipeline
    Route::get('/events/{event}/campaigns', [CampaignController::class, 'index'])->name('events.campaigns.index');
    Route::get('/events/{event}/campaigns/create', [CampaignController::class, 'create'])->name('events.campaigns.create');
    Route::get('/events/{event}/messages/create', [CampaignController::class, 'create'])->name('events.messages.create');
    Route::get('/events/{event}/send', [CampaignController::class, 'create'])->name('events.send');
    Route::get('/events/{event}/campaigns/audience-stats', [CampaignController::class, 'audienceStats'])->name('events.campaigns.audience-stats');
    Route::post('/events/{event}/campaigns/test-send', [CampaignController::class, 'testSend'])->name('events.campaigns.test-send');
    Route::post('/events/{event}/campaigns', [CampaignController::class, 'store'])->name('events.campaigns.store');
    Route::post('/events/{event}/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('events.campaigns.cancel');
    Route::get('/events/{event}/deliveries', [CampaignController::class, 'deliveries'])->name('events.deliveries');
    Route::post('/events/{event}/deliveries/{delivery}/resend', [CampaignController::class, 'resendDelivery'])->name('events.deliveries.resend');
    Route::post('/events/{event}/campaigns/{campaign}/resend-failed', [CampaignController::class, 'resendFailed'])->name('events.campaigns.resend-failed');
    Route::post('/events/{event}/campaigns/{campaign}/resend-pending', [CampaignController::class, 'resendPending'])->name('events.campaigns.resend-pending');

    // Event Scanners & Check-in Management
    Route::get('/events/{event}/scanners', [ScannerController::class, 'index'])->name('events.scanners.index');
    Route::post('/events/{event}/scanners', [ScannerController::class, 'store'])->name('events.scanners.store');
    Route::post('/events/{event}/scanners/{scanner}/revoke', [ScannerController::class, 'revoke'])->name('events.scanners.revoke');
    Route::post('/events/{event}/guests/{guest}/manual-checkin', [ScannerController::class, 'manualCheckin'])->name('events.guests.manual-checkin');
    Route::post('/events/{event}/guests/{guest}/undo-checkin', [ScannerController::class, 'undoCheckin'])->name('events.guests.undo-checkin');

    // Thank-You Card Setup & Compose
    Route::get('/events/{event}/thank-you', [ThankYouController::class, 'show'])->name('events.thank-you');

    // Event Overview Dashboard & Live Arrival Polling
    Route::get('/events/{event}', [EventDashboardController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/overview', [EventDashboardController::class, 'show'])->name('events.overview');
    Route::get('/events/{event}/live-checkins', [EventDashboardController::class, 'liveCheckins'])->name('events.live-checkins');
    Route::post('/events/{event}/close', [EventDashboardController::class, 'closeEvent'])->name('events.close');
    Route::post('/events/{event}/reopen', [EventDashboardController::class, 'reopenEvent'])->name('events.reopen');

    // Reminders
    Route::get('/events/{event}/reminders', [ReminderController::class, 'index'])->name('events.reminders.index');
    Route::post('/events/{event}/reminders', [ReminderController::class, 'store'])->name('events.reminders.store');
    Route::delete('/events/{event}/reminders/{reminder}', [ReminderController::class, 'destroy'])->name('events.reminders.destroy');

    // Exports & Print Views
    Route::get('/events/{event}/export/csv', [ExportController::class, 'guestsCsv'])->name('events.export.csv');
    Route::get('/events/{event}/export/attendance-sheet', [ExportController::class, 'attendanceSheet'])->name('events.export.attendance');
    Route::get('/events/{event}/export/door-list', [ExportController::class, 'doorList'])->name('events.export.door-list');

    // Host Notification Center
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-all-read', [NotificationCenterController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/{id}/read', [NotificationCenterController::class, 'markAsRead'])->name('notifications.read');
    Route::delete('/notifications/{id}', [NotificationCenterController::class, 'destroy'])->name('notifications.destroy');

    // "My Usage" Analytics
    Route::get('/usage', [UsageController::class, 'index'])->name('usage.index');

    // Impersonation leave route (accessible by current session)
    Route::post('/impersonation/leave', [\App\Http\Controllers\ImpersonationController::class, 'leave'])->name('impersonation.leave');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Public Short Links (e.g. for SMS and invitations)
Route::get('/s/{code}', [ShortLinkController::class, 'redirect'])->name('shortlink.redirect');

// Scanner PWA Interface
Route::get('/scan/{token}', [ScanController::class, 'show'])->name('scanner.show');
Route::post('/scan/{token}/auth', [ScanController::class, 'authenticate'])->name('scanner.auth');
Route::post('/scan/{token}/logout', [ScanController::class, 'logout'])->name('scanner.logout');

// Check-in API routes (Session + Token enabled)
Route::post('/api/scan', [ScanController::class, 'scan'])->name('api.scan');
Route::get('/api/scan/guests', [ScanController::class, 'searchGuests'])->name('api.scan.guests');
Route::get('/api/scan/offline-manifest', [ScanController::class, 'offlineManifest'])->name('api.scan.offline-manifest');
Route::post('/api/scan/sync', [ScanController::class, 'sync'])->name('api.scan.sync');

// Public Guest Invitation & Event Pages (Rate limited: 60 requests per minute)
Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/i/{token}', [PublicInvitationController::class, 'show'])->name('public.invitation.show');
    Route::get('/t/{token}', [PublicThankYouController::class, 'show'])->name('public.thank-you.show');
    Route::post('/i/{token}/rsvp', [PublicInvitationController::class, 'rsvp'])->name('public.invitation.rsvp');
    Route::post('/i/{token}/opt-out', [PublicInvitationController::class, 'optOut'])->name('public.invitation.opt-out');
    Route::get('/i/{token}/calendar.ics', [PublicInvitationController::class, 'calendarIcs'])->name('public.invitation.ics');
    Route::get('/i/{token}/qr-download', [PublicInvitationController::class, 'qrDownload'])->name('public.invitation.qr-download');
    Route::get('/i/{token}/og-image.png', [PublicInvitationController::class, 'ogImage'])->name('public.invitation.og-image');
    Route::get('/e/{slug}', [PublicInvitationController::class, 'eventInfo'])->name('public.event.info');
});

// Explicit 404 for standard /admin paths to keep superadmin path secret
Route::any('/admin/{any?}', function () {
    abort(404);
})->where('any', '.*');

// Super Admin Authentication & 2FA Routes (mounted at secret path)
Route::prefix(config('invite.superadmin_path', 'sa-x7k2p9q4m1'))->group(function () {
    Route::middleware([
        \App\Http\Middleware\SuperAdminIpAllowlist::class,
        \App\Http\Middleware\SuperAdminSecurityHeaders::class,
    ])->group(function () {
        Route::get('/login', [\App\Http\Controllers\SuperAdmin\AuthController::class, 'showLoginForm'])->name('superadmin.login');
        Route::post('/login', [\App\Http\Controllers\SuperAdmin\AuthController::class, 'login']);

        Route::get('/two-factor-setup', [\App\Http\Controllers\SuperAdmin\AuthController::class, 'showTwoFactorSetup'])->name('superadmin.two-factor.setup');
        Route::post('/two-factor-setup', [\App\Http\Controllers\SuperAdmin\AuthController::class, 'confirmTwoFactorSetup']);

        Route::get('/two-factor-challenge', [\App\Http\Controllers\SuperAdmin\AuthController::class, 'showTwoFactorChallenge'])->name('superadmin.two-factor.challenge');
        Route::post('/two-factor-challenge', [\App\Http\Controllers\SuperAdmin\AuthController::class, 'verifyTwoFactorChallenge']);

        Route::post('/logout', [\App\Http\Controllers\SuperAdmin\AuthController::class, 'logout'])->name('superadmin.logout');
        Route::post('/auth/logout', [\App\Http\Controllers\SuperAdmin\AuthController::class, 'logout'])->name('filament.superadmin.auth.logout');
        Route::post('/impersonate/{user}', [\App\Http\Controllers\ImpersonationController::class, 'impersonate'])->name('superadmin.impersonate');
    });
});

require __DIR__.'/auth.php';
