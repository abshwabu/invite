<?php

use App\Jobs\ExpireSubscriptionsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ExpireSubscriptionsJob)->hourly();
Schedule::command('campaigns:dispatch-scheduled')->everyMinute();
Schedule::command('reminders:dispatch')->everyMinute();
Schedule::command('events:close-ended')->hourly();
Schedule::command('subscriptions:check-expiring')->daily();
Schedule::command('payments:expire-abandoned')->daily();
Schedule::command('payments:purge-receipts')->daily();
Schedule::command('payments:send-waiting-digest')->dailyAt('09:00');
