<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EditorController;
use App\Http\Controllers\EventDraftController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TemplateController;
use App\Models\Category;
use App\Models\Template;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $categories = Category::with([
        'templates' => fn ($query) => $query->where('is_active', true)->where('is_system', true)->orderBy('sort'),
    ])->orderBy('sort')->get();

    return Inertia::render('Welcome', [
        'categories' => $categories,
        'featuredTemplates' => Template::where('is_active', true)->where('is_system', true)->latest()->take(6)->get(),
    ]);
})->name('home');

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

    // Template custom upload (gated by upload_templates)
    Route::post('/templates/upload', [TemplateController::class, 'upload'])->name('templates.upload');

    // Import guest draft into real event
    Route::post('/events/import-draft', [EventDraftController::class, 'import'])->name('events.import-draft');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
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
    });
});

require __DIR__.'/auth.php';
