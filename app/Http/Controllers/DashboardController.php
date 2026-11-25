<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\FftLog;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with all devices
     */
    public function index()
    {
        $devices = Device::orderBy('device_id')->get();

        // Latest dB SPL per device in one query (instead of loading every
        // FFT log of every device)
        $latestDb = FftLog::whereIn('id', FftLog::selectRaw('MAX(id)')->groupBy('device_id'))
            ->pluck('db_spl', 'device_id');

        $isExceeding = function (Device $device) use ($latestDb) {
            $db = $latestDb[$device->id] ?? null;
            return $db !== null && $device->max_db_spl_threshold !== null
                && (float) $db >= (float) $device->max_db_spl_threshold;
        };

        $devicesWithLocation = $devices
            ->filter(fn ($d) => $d->latitude !== null && $d->longitude !== null)
            ->values()
            ->map(fn ($device) => [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'name' => $device->name,
                'latitude' => $device->latitude,
                'longitude' => $device->longitude,
                'status' => $device->status,
                'threshold' => $device->max_db_spl_threshold,
                'latest_db_spl' => $latestDb[$device->id] ?? null,
                'exceeding_threshold' => $isExceeding($device),
            ]);

        $exceedingCount = $devices->filter($isExceeding)->count();

        return view('dashboard', [
            'devices' => $devices,
            'devicesWithLocation' => $devicesWithLocation,
            'exceedingCount' => $exceedingCount,
            'mqttHost' => config('mqtt.host'),
            'mqttTopic' => config('mqtt.topic_pattern', 'audio/+/data'),
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
