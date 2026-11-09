<?php

use App\Http\Controllers\ScanController;
use App\Http\Controllers\SmsWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/sms/{provider}', [SmsWebhookController::class, 'handle'])
    ->name('api.webhooks.sms');

Route::post('/scan', [ScanController::class, 'scan']);
Route::get('/scan/guests', [ScanController::class, 'searchGuests']);
Route::get('/scan/offline-manifest', [ScanController::class, 'offlineManifest']);
Route::post('/scan/sync', [ScanController::class, 'sync']);
