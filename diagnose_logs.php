<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Device;
use App\Models\FftLog;
use App\Models\Setting;

echo "--- Devices ---\n";
foreach (Device::all() as $device) {
    echo "ID: {$device->device_id}, Token: {$device->token}, Status: {$device->status}\n";
}

echo "\n--- FFT Logs Count ---\n";
echo FftLog::count() . "\n";

echo "\n--- MQTT Settings ---\n";
echo "Host: " . Setting::get('mqtt_host') . "\n";
echo "Topic: " . Setting::get('mqtt_topic_pattern') . "\n";
echo "Interval: " . Setting::get('fft_logging_interval') . "\n";
