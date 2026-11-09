<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EditorController;
use App\Http\Controllers\EventDraftController;
use App\Http\Controllers\LocaleController;
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

    // Import guest draft into real event
    Route::post('/events/import-draft', [EventDraftController::class, 'import'])->name('events.import-draft');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
