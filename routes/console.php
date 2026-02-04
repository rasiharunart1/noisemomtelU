<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
use App\Models\Setting;

Schedule::command('schedules:trigger-recordings')->everyMinute();

Schedule::command('logs:archive-daily')
    ->dailyAt(Setting::get('log_auto_archive_time', '00:00'))
    ->when(function () {
        return (bool) Setting::get('log_auto_archive_enabled', false);
    });
