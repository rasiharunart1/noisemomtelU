<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\FftLog;
use App\Models\Setting;
use App\Models\AudioRecording;
use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttListenerCommand extends Command
{
    protected $signature = 'mqtt:listen';
    protected $description = 'Listen to MQTT broker and process ESP32 audio/FFT data';

    private $lastLogTime = [];

    private ?MqttClient $mqtt = null;

    /** In-progress SD file listings: device_id => ['list_id' => int, 'seen' => [basename => true]] */
    private array $fileSync = [];

    public function handle(): int
    {
        $this->info('Starting MQTT Listener...');

        // Get MQTT configuration
        $host = Setting::get('mqtt_host', config('mqtt.host'));
        $port = (int) Setting::get('mqtt_port', config('mqtt.port', 8883));
        $username = Setting::get('mqtt_username', config('mqtt.username'));
        $password = Setting::get('mqtt_password', config('mqtt.password')); // From DB or config/env
        $topic = Setting::get('mqtt_topic_pattern', config('mqtt.topic_pattern', 'audio/+/data'));
        
        $loggingInterval = (int) Setting::get('fft_logging_interval', config('mqtt.fft_logging_interval', 10));

        if (empty($host)) {
            $this->error('ERROR: MQTT_HOST is not defined in .env or Settings!');
            return self::FAILURE;
        }

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
            $this->mqtt = $mqtt;
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

            // Subscribe to recording status topic (e.g., audio/+/recording_status)
            $recordingTopic = str_replace('/data', '/recording_status', $topic);
            $mqtt->subscribe($recordingTopic, function (string $receivedTopic, string $message) {
                $this->processRecordingStatus($receivedTopic, $message);
            }, 0);

            // Device health reports (retained, every 60 s)
            $healthTopic = str_replace('/data', '/health', $topic);
            $mqtt->subscribe($healthTopic, function (string $receivedTopic, string $message) {
                $this->processHealth($message);
            }, 0);

            // SD card file listings (answer to the list_files command)
            $filesTopic = str_replace('/data', '/files', $topic);
            $mqtt->subscribe($filesTopic, function (string $receivedTopic, string $message) {
                $this->processFileList($message);
            }, 0);

            $this->info("✓ Subscribed to topics: {$topic}, {$statusTopic}, {$recordingTopic}, {$healthTopic}, {$filesTopic}");

            // Register loop handler for periodic tasks
            $lastCheck = time();
            $mqtt->registerLoopEventHandler(function (MqttClient $client, float $elapsedTime) use (&$lastCheck, $topic) {
                // Check every 10 seconds
                if (time() - $lastCheck >= 10) {
                    $offlineThresholdSeconds = (int) Setting::get('device_offline_threshold', 30);
                    $offlineThreshold = now()->subSeconds($offlineThresholdSeconds);
                    
                    // Get devices that are about to go offline
                    // ('recording' devices can die too, e.g. power loss)
                    $offlineDevices = Device::whereIn('status', ['online', 'recording'])
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
                            
                            $client->publish($deviceTopic, $payload, 0);
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

            // Device came back: re-apply the gain from the web, in case it
            // was changed while the device was offline
            if ($device->status === 'offline' && $newStatus !== 'offline') {
                $this->syncGain($device);
            }

            // Update device status and last_seen
            $device->update([
                'status' => $newStatus,
                'last_seen' => now(),
            ]);

            // Log FFT data whenever the device is alive (also while recording)
            if ($newStatus !== 'offline') {
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

    private function processRecordingStatus(string $topic, string $message): void
    {
        try {
            $data = json_decode($message, true);

            if (!$data || !isset($data['device_id'])) {
                return;
            }

            $device = Device::where('device_id', $data['device_id'])->first();
            if (!$device) {
                return;
            }

            $status = $data['status'] ?? null;
            $sdFilename = $data['filename'] ?? null;
            $error = $data['error'] ?? null;

            // Keep the device marked online while it records/uploads
            $device->update(['last_seen' => now()]);

            // MQTT messages can arrive after the HTTP upload already finished
            // (the device uploads right after recording). Never move a
            // recording that is stored on the server back to an earlier state.
            $rows = fn () => AudioRecording::forSdFile($device->id, (string) $sdFilename);
            $notOnServer = fn () => $rows()->where('status', '!=', 'completed');

            if ($status === 'completed' && $sdFilename) {
                // Recording finished and saved on the device's SD card.
                // The device uploads it automatically afterwards.
                $recording = $rows()->first() ?? new AudioRecording([
                    'device_id' => $device->id,
                    'sd_filename' => $sdFilename,
                ]);

                if ($recording->status !== 'completed') {
                    $recording->fill([
                        'filename' => basename($sdFilename),
                        'path' => '',
                        'duration_seconds' => isset($data['duration']) ? (int) $data['duration'] : null,
                        'file_size_bytes' => (int) ($data['size'] ?? 0),
                        'status' => 'on_device',
                        'uploaded_at' => null,
                    ]);
                }
                $recording->fill(['on_sd' => true, 'sd_checked_at' => now()])->save();
                $this->info("🎙 Device {$device->device_id} finished recording {$sdFilename} (saved on SD card)");
            } elseif ($status === 'uploading' && $sdFilename) {
                $notOnServer()->update(['status' => 'uploading']);
            } elseif ($status === 'upload_completed' && $sdFilename) {
                // The upload endpoint already stored the file; the device
                // keeps its copy in /rec/up.
                $rows()->update(['on_sd' => true, 'sd_checked_at' => now()]);
                if ($notOnServer()->exists()) {
                    $this->warn("Device reported upload of {$sdFilename} but the server has no stored file yet");
                }
            } elseif ($status === 'upload_failed' && $sdFilename) {
                $notOnServer()->update(['status' => 'upload_failed']);
                if ($error === 'not_found') {
                    $rows()->update(['on_sd' => false, 'sd_checked_at' => now()]);
                }
            } elseif ($status === 'file_deleted' && $sdFilename) {
                $rows()->update(['on_sd' => false, 'sd_checked_at' => now()]);
                $this->info("🗑 {$device->device_id} deleted {$sdFilename} from SD" . ($error ? " ({$error})" : ''));
            } elseif ($status === 'upload_all_done') {
                $this->info("⬆ {$device->device_id} upload_all finished: " . ($data['uploaded'] ?? 0) . ' uploaded, '
                    . ($data['failed'] ?? 0) . ' failed'
                    . (!empty($data['aborted']) ? ' (aborted: ' . ($data['error'] ?? '?') . ')' : ''));
            } elseif (in_array($status, ['sd_full', 'sd_error'], true)) {
                $this->warn("⚠️ Device {$device->device_id} reported {$status}" . ($sdFilename ? " ({$sdFilename})" : ''));
            }

        } catch (\Exception $e) {
            $this->error('Error processing recording status: ' . $e->getMessage());
        }
    }

    private function processHealth(string $message): void
    {
        try {
            $data = json_decode($message, true);
            if (!$data || !isset($data['device_id'])) {
                return;
            }

            $device = Device::where('device_id', $data['device_id'])->first();
            if (!$device) {
                return;
            }

            // Health is retained on the broker, so it may be an old report
            // replayed on subscribe. It does not prove the device is online.
            unset($data['device_id']);
            $device->update([
                'health' => $data,
                'health_updated_at' => now(),
            ]);

            $sd = $data['sd'] ?? [];
            if (isset($sd['ok']) && !$sd['ok']) {
                $this->warn("⚠️ {$device->device_id}: SD card not available");
            } elseif (isset($sd['free_mb'], $sd['total_mb']) && $sd['total_mb'] > 0 && $sd['free_mb'] / $sd['total_mb'] < 0.05) {
                $this->warn("⚠️ {$device->device_id}: SD card almost full ({$sd['free_mb']} MB free)");
            }
        } catch (\Exception $e) {
            $this->error('Error processing health: ' . $e->getMessage());
        }
    }

    /**
     * Sync the recordings table with the files actually on the SD card.
     * The device sends the list in pages; when the last page arrives every
     * recording that was not listed is marked as no longer on the SD card.
     */
    private function processFileList(string $message): void
    {
        try {
            $data = json_decode($message, true);
            if (!$data || !isset($data['device_id'], $data['list_id'])) {
                return;
            }

            $device = Device::where('device_id', $data['device_id'])->first();
            if (!$device) {
                return;
            }

            $key = $device->device_id;
            $page = (int) ($data['page'] ?? 0);
            $sync = $this->fileSync[$key] ?? null;
            if (!$sync || $sync['list_id'] !== $data['list_id']) {
                // A listing is only complete if we saw it from page 0
                $sync = ['list_id' => $data['list_id'], 'seen' => [], 'next_page' => 0, 'complete' => $page === 0];
            }
            if ($page !== $sync['next_page']) {
                $sync['complete'] = false; // a page was lost
            }
            $sync['next_page'] = $page + 1;

            if (!empty($data['error'])) {
                $this->warn("⚠️ {$key}: file listing failed ({$data['error']})");
                unset($this->fileSync[$key]);
                return;
            }

            $created = 0;
            foreach ($data['files'] ?? [] as $file) {
                $path = $file['n'] ?? null;
                if (!$path) {
                    continue;
                }
                $sync['seen'][basename($path)] = true;

                $recording = AudioRecording::forSdFile($device->id, $path)->first();
                if ($recording) {
                    $recording->update(['on_sd' => true, 'sd_checked_at' => now()]);
                    continue;
                }
                if (!empty($file['r'])) {
                    continue; // still being recorded; registered on "completed"
                }

                // A recording the server never heard about (e.g. MQTT was down
                // when it finished)
                $size = (int) ($file['s'] ?? 0);
                AudioRecording::create([
                    'device_id' => $device->id,
                    'filename' => basename($path),
                    'path' => '',
                    'sd_filename' => $path,
                    'duration_seconds' => $size > 44 ? intdiv($size - 44, 32000) : 0,
                    'file_size_bytes' => $size,
                    'status' => 'on_device',
                    'on_sd' => true,
                    'sd_checked_at' => now(),
                ]);
                $created++;
            }
            if ($created > 0) {
                $this->info("📂 {$key}: registered {$created} recording(s) found on the SD card");
            }

            if (empty($data['last'])) {
                $this->fileSync[$key] = $sync;
                return;
            }

            unset($this->fileSync[$key]);
            if (!$sync['complete']) {
                $this->warn("⚠️ {$key}: SD listing incomplete, not marking missing files");
                return;
            }

            $seen = $sync['seen'];
            $missing = AudioRecording::where('device_id', $device->id)
                ->whereNotNull('sd_filename')
                ->where(fn ($q) => $q->whereNull('on_sd')->orWhere('on_sd', true))
                ->get()
                ->reject(fn ($r) => isset($seen[basename($r->sd_filename)]));

            foreach ($missing as $recording) {
                $recording->update(['on_sd' => false, 'sd_checked_at' => now()]);
            }
            $this->info("📂 {$key}: SD sync done, " . count($seen) . " file(s) on card, {$missing->count()} no longer on card");
        } catch (\Exception $e) {
            $this->error('Error processing file list: ' . $e->getMessage());
        }
    }

    private function syncGain(Device $device): void
    {
        if (!$this->mqtt || $device->gain === null) {
            return;
        }
        try {
            $this->mqtt->publish(
                "audio/{$device->device_id}/control",
                json_encode(['action' => 'set_gain', 'value' => (float) $device->gain]),
                0
            );
        } catch (\Exception $e) {
            $this->warn("Could not sync gain to {$device->device_id}: " . $e->getMessage());
        }
    }

    private function logToDatabase(Device $device, array $data): void
    {
        $audio = $data['audio'];
        $fft = $data['fft'];

        FftLog::create([
            'device_id' => $device->id,
            
            // Audio metrics
            'rms' => $this->sanitizeValue($audio['rms'] ?? 0, 10, 6),
            'db_spl' => (float) ($audio['db_spl'] ?? 0),
            
            // FFT metrics
            'peak_frequency' => $this->sanitizeValue($fft['peak_frequency'] ?? 0, 12, 4),
            'total_energy' => $this->sanitizeValue($fft['total_energy'] ?? 0, 16, 4),
            'band_low' => $this->sanitizeValue($fft['band_energy']['low'] ?? 0, 14, 4),
            'band_mid' => $this->sanitizeValue($fft['band_energy']['mid'] ?? 0, 14, 4),
            'band_high' => $this->sanitizeValue($fft['band_energy']['high'] ?? 0, 14, 4),
            
            'created_at' => now(),
        ]);
    }

    private function sanitizeValue(mixed $value, int $precision, int $scale): float
    {
        $floatValue = (float) ($value ?? 0);
        $maxBeforeDecimal = $precision - $scale;
        $max = pow(10, $maxBeforeDecimal) - 1 + (1 - pow(10, -$scale));
        
        if ($floatValue > $max) return $max;
        if ($floatValue < -$max) return -$max;
        
        return $floatValue;
    }
}
