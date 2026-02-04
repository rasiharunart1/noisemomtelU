<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Display settings page
     */
    public function index()
    {
        $settings = [
            'mqtt_host' => Setting::get('mqtt_host', env('MQTT_HOST')),
            'mqtt_port' => Setting::get('mqtt_port', env('MQTT_PORT', 8883)),
            'mqtt_username' => Setting::get('mqtt_username', env('MQTT_USERNAME')),
            'mqtt_topic_pattern' => Setting::get('mqtt_topic_pattern', env('MQTT_TOPIC_PATTERN', 'audio/+/data')),
            'fft_logging_interval' => Setting::get('fft_logging_interval', env('FFT_LOGGING_INTERVAL', 10)),
            'log_auto_archive_enabled' => Setting::get('log_auto_archive_enabled', '0'),
            'log_auto_archive_time' => Setting::get('log_auto_archive_time', '00:00'),
            'device_offline_threshold' => Setting::get('device_offline_threshold', '30'),
        ];

        return view('settings', compact('settings'));
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'mqtt_host' => 'required|string',
            'mqtt_port' => 'required|integer|min:1|max:65535',
            'mqtt_username' => 'nullable|string',
            'mqtt_password' => 'nullable|string',
            'mqtt_topic_pattern' => 'required|string',
            'fft_logging_interval' => 'required|integer|min:1|max:300',
            'log_auto_archive_enabled' => 'nullable|boolean',
            'log_auto_archive_time' => 'nullable|date_format:H:i',
            'device_offline_threshold' => 'nullable|integer|min:10|max:300',
        ]);

        foreach ($validated as $key => $value) {
            if ($value !== null && $value !== '') {
                Setting::set($key, $value);
            }
        }

        return redirect()->route('settings')
            ->with('success', 'Settings updated successfully. Restart MQTT listener to apply changes.');
    }

    /**
     * API endpoint to get current settings
     */
    public function getSettings()
    {
        return response()->json([
            'mqtt_host' => Setting::get('mqtt_host', env('MQTT_HOST')),
            'mqtt_port' => Setting::get('mqtt_port', env('MQTT_PORT', 8883)),
            'mqtt_topic_pattern' => Setting::get('mqtt_topic_pattern', env('MQTT_TOPIC_PATTERN', 'audio/+/data')),
            'fft_logging_interval' => Setting::get('fft_logging_interval', env('FFT_LOGGING_INTERVAL', 10)),
            'log_auto_archive_enabled' => (bool) Setting::get('log_auto_archive_enabled', false),
            'log_auto_archive_time' => Setting::get('log_auto_archive_time', '00:00'),
            'device_offline_threshold' => (int) Setting::get('device_offline_threshold', 30),
        ]);
    }
    /**
     * Test MQTT Connection
     */
    public function testConnection(Request $request)
    {
        // Use values from request if provided (for testing before save), otherwise from DB/Env
        $host = $request->input('mqtt_host') ?? Setting::get('mqtt_host', env('MQTT_HOST'));
        $port = $request->input('mqtt_port') ?? Setting::get('mqtt_port', env('MQTT_PORT', 8883));
        $username = $request->input('mqtt_username') ?? Setting::get('mqtt_username', env('MQTT_USERNAME'));
        $password = $request->input('mqtt_password') ?? Setting::get('mqtt_password', env('MQTT_PASSWORD'));

        try {
            $clientId = 'laravel_tester_' . uniqid();
            $mqtt = new \PhpMqtt\Client\MqttClient($host, (int)$port, $clientId);

            $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings)
                ->setUsername($username)
                ->setPassword($password)
                ->setUseTls(true) // HiveMQ Cloud requires TLS
                ->setTlsSelfSignedAllowed(true)
                ->setTlsVerifyPeer(false);

            $mqtt->connect($connectionSettings, true);
            $mqtt->disconnect();

            return response()->json([
                'success' => true,
                'message' => "Successfully connected to {$host}:{$port}"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
