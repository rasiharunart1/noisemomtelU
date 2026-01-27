<?php
namespace Database\Seeders;
use App\Models\Device;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        Device::create([
            'id' => Str::uuid(),
            'device_id' => 'ESP32-04',
            'name' => 'purwokerto',
            'mqtt_topic' => 'audio/ESP32-04/data',
            'status' => 'offline',
        ]);
        
    }
}