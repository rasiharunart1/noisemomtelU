<?php

namespace App\Http\Controllers;

use App\Models\Device;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with all devices
     */
    public function index()
    {
        $devices = Device::orderBy('device_id')->get();
        
        // Get devices with location for map
        $devicesWithLocation = Device::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('fftLogs')
            ->get()
            ->map(function ($device) {
                return [
                    'id' => $device->id,
                    'device_id' => $device->device_id,
                    'name' => $device->name,
                    'latitude' => $device->latitude,
                    'longitude' => $device->longitude,
                    'status' => $device->status,
                    'threshold' => $device->max_db_spl_threshold,
                    'latest_db_spl' => $device->getLatestDbSpl(),
                    'exceeding_threshold' => $device->isExceedingThreshold(),
                ];
            });
        
        // Count devices exceeding threshold
        $exceedingCount = $devices->filter(fn($d) => $d->isExceedingThreshold())->count();
        
        return view('dashboard', [
            'devices' => $devices,
            'devicesWithLocation' => $devicesWithLocation,
            'exceedingCount' => $exceedingCount,
            'mqttHost' => config('mqtt.host', env('MQTT_HOST')),
            'mqttTopic' => config('mqtt.topic_pattern', env('MQTT_TOPIC_PATTERN', 'audio/+/data')),
        ]);
    }

    /**
     * API endpoint for getting device status
     */
    public function getDeviceStatus()
    {
        $devices = Device::select('id', 'device_id', 'status', 'last_seen')
            ->get()
            ->map(function ($device) {
                return [
                    'device_id' => $device->device_id,
                    'status' => $device->status,
                    'last_seen' => $device->last_seen?->diffForHumans(),
                ];
            });

        return response()->json($devices);
    }
}
