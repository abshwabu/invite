<?php

use App\Http\Controllers\SmsWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/sms/{provider}', [SmsWebhookController::class, 'handle'])
    ->name('api.webhooks.sms');
