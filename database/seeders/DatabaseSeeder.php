<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Setting;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DeviceSeeder::class,
        ]);

        Setting::set('mqtt_host', env('MQTT_HOST'));
        Setting::set('mqtt_port', env('MQTT_PORT', 8883));
        Setting::set('mqtt_username', env('MQTT_USERNAME'));
        Setting::set('mqtt_topic_pattern', env('MQTT_TOPIC_PATTERN', 'audio/+/data'));
        Setting::set('fft_logging_interval', env('FFT_LOGGING_INTERVAL', 10));
    }
}