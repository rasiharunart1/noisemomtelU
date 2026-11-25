<?php

namespace App\Services;

use App\Models\Setting;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

/**
 * Publishes commands to a device on audio/{device_id}/command.
 */
class DeviceCommandService
{
    /**
     * @throws \Exception when the broker cannot be reached
     */
    public function send(string $deviceId, string $action, array $data = []): void
    {
        $host = Setting::get('mqtt_host', config('mqtt.host'));
        $port = (int) Setting::get('mqtt_port', config('mqtt.port', 8883));
        $username = Setting::get('mqtt_username', config('mqtt.username'));
        $password = Setting::get('mqtt_password', config('mqtt.password'));

        $mqtt = new MqttClient($host, $port, 'laravel_cmd_' . uniqid());

        $connectionSettings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password)
            ->setUseTls(true)
            ->setTlsSelfSignedAllowed(true)
            ->setTlsVerifyPeer(false)
            ->setTlsVerifyPeerName(false);

        $mqtt->connect($connectionSettings, true);

        $payload = json_encode(array_merge([
            'action' => $action,
            'timestamp' => now()->timestamp,
            'device_id' => $deviceId,
        ], $data));

        // QoS 1: broker menyimpan perintah untuk device yang MQTT-nya sedang dijeda (upload)
        $mqtt->publish("audio/{$deviceId}/command", $payload, 1);
        // Tunggu PUBACK dari broker (maks 5 detik) sebelum memutus koneksi
        $mqtt->loop(true, true, 5);
        $mqtt->disconnect();
    }

    /**
     * Whether the server should tell the device to delete a recording from
     * its SD card after it has been stored on the server. Default: keep it.
     */
    public static function deleteFromSdAfterUpload(): bool
    {
        return (bool) Setting::get('recording_delete_after_upload', false);
    }
}
