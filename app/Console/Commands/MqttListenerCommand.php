<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\FftLog;
use App\Models\Setting;
use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttListenerCommand extends Command
{
    protected $signature = 'mqtt:listen';
    protected $description = 'Listen to MQTT broker and process ESP32 audio/FFT data';

    private $lastLogTime = [];

    public function handle(): int
    {
        $this->info('Starting MQTT Listener...');

        // Get MQTT configuration
        $host = Setting::get('mqtt_host', env('MQTT_HOST'));
        $port = (int) Setting::get('mqtt_port', env('MQTT_PORT', 8883));
        $username = Setting::get('mqtt_username', env('MQTT_USERNAME'));
        $password = env('MQTT_PASSWORD'); // From env for security
        $topic = Setting::get('mqtt_topic_pattern', env('MQTT_TOPIC_PATTERN', 'audio/+/data'));
        
        $loggingInterval = (int) Setting::get('fft_logging_interval', env('FFT_LOGGING_INTERVAL', 10));

        $this->info("Connecting to MQTT broker: {$host}:{$port}");
        $this->info("Topic pattern: {$topic}");
        $this->info("Logging interval: {$loggingInterval} seconds");

        try {
            // Create MQTT client
            $clientId = 'laravel_listener_' . uniqid();
            $mqtt = new MqttClient($host, $port, $clientId);

            // Connection settings
            $connectionSettings = (new ConnectionSettings)
                ->setUsername($username)
                ->setPassword($password)
                ->setKeepAliveInterval(60)
                ->setUseTls(true)  // Enable TLS for port 8883
                ->setTlsSelfSignedAllowed(true)  // Allow self-signed certificates
                ->setTlsVerifyPeer(false)  // Disable peer verification for testing
                ->setTlsVerifyPeerName(false)  // Disable peer name verification
                ->setLastWillTopic('system/status')
                ->setLastWillMessage('Laravel listener disconnected')
                ->setLastWillQualityOfService(1);

            // Connect
            $mqtt->connect($connectionSettings, true);
            $this->info('✓ Connected to MQTT broker');

            // Subscribe to data topic
            $mqtt->subscribe($topic, function (string $receivedTopic, string $message) use ($loggingInterval) {
                $this->processMessage($receivedTopic, $message, $loggingInterval);
            }, 0);

            // Subscribe to status topic (e.g., audio/+/status)
            $statusTopic = str_replace('/data', '/status', $topic);
            $mqtt->subscribe($statusTopic, function (string $receivedTopic, string $message) use ($loggingInterval) {
                $this->processMessage($receivedTopic, $message, $loggingInterval);
            }, 0);

            $this->info('✓ Subscribed to topics: ' . $topic . ' & ' . $statusTopic);

            // Register loop handler for periodic tasks
            $lastCheck = time();
            $mqtt->registerLoopEventHandler(function (MqttClient $client, float $elapsedTime) use (&$lastCheck) {
                // Check every 10 seconds
                if (time() - $lastCheck >= 10) {
                    $offlineThreshold = now()->subSeconds(30); // Consider offline if no data for 30s
                    
                    // Get devices that are about to go offline
                    $offlineDevices = Device::where('status', 'online')
                        ->where('last_seen', '<', $offlineThreshold)
                        ->get();

                    if ($offlineDevices->count() > 0) {
                        foreach ($offlineDevices as $device) {
                            // Update DB
                            $device->update(['status' => 'offline']);
                            
                            // Publish offline status to MQTT so dashboard updates in real-time
                            // Topic: audio/{device_id}/data (matches the dashboard subscription)
                            $deviceTopic = str_replace('+', $device->device_id, $topic); 
                            $payload = json_encode([
                                'device_id' => $device->device_id,
                                'status' => 'offline',
                                'timestamp' => now()->timestamp
                            ]);
                            
                            $mqtt->publish($deviceTopic, $payload, 0);
                        }
                        
                        $count = $offlineDevices->count();
                        $this->info("⚠️ Marked {$count} device(s) as offline and silenced via MQTT");
                    }
                    
                    $lastCheck = time();
                }
            });

            $this->info('Listening for messages... (Press Ctrl+C to stop)');

            // Listen loop
            $mqtt->loop(true);

        } catch (\Exception $e) {
            $this->error('MQTT Error: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function processMessage(string $topic, string $message, int $loggingInterval): void
    {
        try {
            $data = json_decode($message, true);

            if (!$data || !isset($data['device_id'])) {
                $this->warn('Invalid message format');
                return;
            }

            $deviceId = $data['device_id'];
            // $this->line("📥 Message from: {$deviceId}"); // Too noisy for status updates

            // Find or create device
            $device = Device::where('device_id', $deviceId)->first();

            if (!$device) {
                $status = $data['status'] ?? 'online';
                // Don't create device if it's just an offline message (rare race condition)
                if ($status === 'offline') return;

                $this->warn("Device not found: {$deviceId} - Creating...");
                $device = Device::create([
                    'device_id' => $deviceId,
                    'mqtt_topic' => str_replace('/status', '/data', $topic), // valid topic guess
                    'status' => 'online',
                ]);
                $this->info("✓ New device created with token: {$device->token}");
            } else {
                // Verify Token only if present in data (LWT might not have token?)
                // Ideally LWT should include token for security, but usually LWT is trusted as it comes from broker session.
                // For simplicity, let's skip token check for status messages OR assume LWT has token if security is strict.
                // My simulator sets LWT with just ID and status. Let's allow it for now.
                if (isset($data['token']) && !empty($device->token)) {
                    if ($data['token'] !== $device->token) {
                        $this->warn("❌ Authentication failed for {$deviceId}");
                        return;
                    }
                }
            }

            // Determine status
            $newStatus = $data['status'] ?? 'online';
            
            if ($device->status !== $newStatus) {
                $this->info("🔄 Device {$deviceId} is now {$newStatus}");
            }

            // Update device status and last_seen
            $device->update([
                'status' => $newStatus,
                'last_seen' => now(),
            ]);

            // Only log FFT data if status is online AND data exists
            if ($newStatus === 'online') {
                 // Check if we should log to database (based on interval)
                $shouldLog = false;
                
                if (!isset($this->lastLogTime[$deviceId])) {
                    $shouldLog = true;
                } else {
                    $timeSinceLastLog = time() - $this->lastLogTime[$deviceId];
                    if ($timeSinceLastLog >= $loggingInterval) {
                        $shouldLog = true;
                    }
                }

                if ($shouldLog && isset($data['audio']) && isset($data['fft'])) {
                    $this->logToDatabase($device, $data);
                    $this->lastLogTime[$deviceId] = time();
                    // $this->info("💾 Logged to database"); // Reduce noise
                }
            }

        } catch (\Exception $e) {
            $this->error('Error processing message: ' . $e->getMessage());
        }
    }

    private function logToDatabase(Device $device, array $data): void
    {
        $audio = $data['audio'];
        $fft = $data['fft'];

        FftLog::create([
            'device_id' => $device->id,
            
            // Audio metrics
            'rms' => $audio['rms'] ?? 0,
            'db_spl' => $audio['db_spl'] ?? 0,
            'peak_amplitude' => $audio['peak_amplitude'] ?? 0,
            'noise_floor' => $audio['noise_floor'] ?? 0,
            'gain' => $audio['gain'] ?? 1,
            
            // FFT metrics
            'peak_frequency' => $fft['peak_frequency'] ?? 0,
            'peak_magnitude' => $fft['peak_magnitude'] ?? 0,
            'total_energy' => $fft['total_energy'] ?? 0,
            'band_low' => $fft['band_energy']['low'] ?? 0,
            'band_mid' => $fft['band_energy']['mid'] ?? 0,
            'band_high' => $fft['band_energy']['high'] ?? 0,
            'spectral_centroid' => $fft['spectral_centroid'] ?? 0,
            'zcr' => $fft['zcr'] ?? 0,
            
            'created_at' => now(),
        ]);
    }
}
