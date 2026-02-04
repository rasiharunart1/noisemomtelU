<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
use App\Models\Setting;

Schedule::command('schedules:trigger-recordings')->everyMinute();

// Auto-archive logs (configurable time)
try {
    Schedule::command('logs:archive-daily')
        ->dailyAt(Setting::get('log_auto_archive_time', '00:00'))
        ->when(function () {
            return (bool) Setting::get('log_auto_archive_enabled', false);
        });
} catch (\Exception $e) {
    // Settings table doesn't exist yet (during fresh migration)
    // Skip scheduling, will work after migrations complete
}
