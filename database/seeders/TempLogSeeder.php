<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FftLog;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

class TempLogSeeder extends Seeder
{
    public function run()
    {
        Log::info('TempLogSeeder: Starting...');
        // Ensure a device exists
        $device = Device::first();
        if (!$device) {
             $device = Device::create([
                'device_id' => 'DEV_TEST_001',
                'name' => 'Test Device',
                'description' => 'Created for testing',
                'status' => 'offline', 
                'last_active' => now()
             ]);
        }

        FftLog::create([
            'device_id' => $device->id,
            'rms' => 0.707,
            'db_spl' => 65.5,
            'peak_frequency' => 1000.0,
            'created_at' => now(),
            'updated_at' => now(),
            'band_low' => 0.1,
            'band_mid' => 0.5,
            'band_high' => 0.2
        ]);
        
        $this->command->info('Temp log created.');
    }
}
