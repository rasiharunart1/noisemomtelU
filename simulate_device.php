<?php

require __DIR__ . '/vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Defines
$host = $_ENV['MQTT_HOST'];
$port = $_ENV['MQTT_PORT'];
$username = $_ENV['MQTT_USERNAME'];
$password = $_ENV['MQTT_PASSWORD'];

echo "----------------------------------------\n";
echo " ESP32 Device Simulator\n";
echo "----------------------------------------\n";
echo "MQTT Broker: $host:$port\n";
echo "\n";

// Interactive Input
$deviceId = readline("Enter Device ID (e.g., ESP32-XXXX): ");
if (empty($deviceId)) {
    die("Device ID is required.\n");
}

$token = readline("Enter Device Token (from Dashboard): ");
if (empty($token)) {
    die("Device Token is required.\n");
}

$topic = "audio/{$deviceId}/data";
echo "\nTarget Topic: $topic\n";

try {
    $clientId = 'simulator_' . uniqid();
    $mqtt = new MqttClient($host, $port, $clientId);

    $connectionSettings = (new ConnectionSettings)
        ->setUsername($username)
        ->setPassword($password)
        ->setUseTls(true)
        ->setTlsSelfSignedAllowed(true)
        ->setTlsVerifyPeer(false)
        ->setLastWillTopic("audio/{$deviceId}/status")
        ->setLastWillMessage(json_encode(['status' => 'offline', 'device_id' => $deviceId]))
        ->setLastWillQualityOfService(1);

    echo "Connecting to Broker...\n";
    $mqtt->connect($connectionSettings, true);
    echo "Connected!\n";

    // Send online status
    $mqtt->publish("audio/{$deviceId}/status", json_encode(['status' => 'online', 'device_id' => $deviceId]), 0);

    echo "Sending data... (Press Ctrl+C to stop)\n\n";

    while (true) {
        // Generate random data
        $data = [
            'device_id' => $deviceId,
            'token' => $token,
            'audio' => [
                'rms' => rand(10, 100) / 10,
                'peak_amplitude' => rand(50, 100) / 10,
                'noise_floor' => rand(1, 5),
                'gain' => 1.0
            ],
            'fft' => [
                'peak_frequency' => rand(100, 5000),
                'peak_magnitude' => rand(20, 90),
                'total_energy' => rand(500, 1000),
                'band_energy' => [
                    'low' => rand(100, 300),
                    'mid' => rand(100, 300),
                    'high' => rand(100, 300)
                ],
                'spectral_centroid' => rand(500, 3000),
                'zcr' => rand(0, 100) / 100,
                'spectrum' => (function() {
                    $spectrum = [];
                    // Simulate frequency curve: Bass high, Mids varies, Highs rolloff
                    // 64 bins
                    for ($i = 0; $i < 64; $i++) {
                        // Base curve (1/x decay) + Noise
                        $base = 100 / (1 + ($i * 0.1)); 
                        
                        // Add some peaks at random frequencies (simulating tones)
                        $peak = 0;
                        if ($i > 5 && $i < 15) $peak = rand(0, 30); // Low-mids
                        if ($i > 25 && $i < 30) $peak = rand(0, 20); // High-mids

                        // Fluctuation
                        $val = $base + $peak + rand(-5, 5);
                        
                        // Clamp 0-100
                        $spectrum[] = max(0, min(100, (int)$val));
                    }
                    return $spectrum;
                })()
            ]
        ];

        $payload = json_encode($data);
        $mqtt->publish($topic, $payload, 0);
        
        echo "Sent data: RMS=" . $data['audio']['rms'] . ", PeakFreq=" . $data['fft']['peak_frequency'] . "Hz\n";

        sleep(5); // Send every 5 seconds
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
