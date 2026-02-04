<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RecordingSchedule;
use App\Models\Setting;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Carbon;

class TriggerScheduledRecordings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedules:trigger-recordings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger scheduled audio recordings for devices';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        // 1. Mark expired schedules as completed
        RecordingSchedule::where('status', '!=', 'completed')
            ->where('status', '!=', 'cancelled')
            ->where('end_time', '<', $now)
            ->update(['status' => 'completed']);

        // 2. Fetch active schedules
        // Start time has passed AND End time has not passed
        $schedules = RecordingSchedule::where(function($q) {
                $q->where('status', 'pending')
                  ->orWhere('status', 'active');
            })
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->with('device')
            ->get();

        $this->info("Found {$schedules->count()} active schedules.");

        foreach ($schedules as $schedule) {
            // Check interval
            if ($schedule->last_run_at) {
                $nextRun = $schedule->last_run_at->copy()->addMinutes($schedule->interval_minutes);
                if ($now->lessThan($nextRun)) {
                    continue; // Not time yet
                }
            }

            // Time to run!
            $this->triggerRecording($schedule);
        }
    }

    private function triggerRecording(RecordingSchedule $schedule)
    {
        if (!$schedule->device || $schedule->device->status !== 'online') {
            $this->warn("Device {$schedule->device_id} is offline or missing. Skipping.");
            return;
        }

        try {
            // MQTT Config
            $host = Setting::get('mqtt_host', env('MQTT_HOST'));
            $port = (int) Setting::get('mqtt_port', env('MQTT_PORT', 8883));
            $username = Setting::get('mqtt_username', env('MQTT_USERNAME'));
            $password = Setting::get('mqtt_password', env('MQTT_PASSWORD'));

            $clientId = 'laravel_scheduler_' . uniqid();
            $mqtt = new MqttClient($host, $port, $clientId);

            $connectionSettings = (new ConnectionSettings)
                ->setUsername($username)
                ->setPassword($password)
                ->setUseTls(true)
                ->setTlsSelfSignedAllowed(true)
                ->setTlsVerifyPeer(false)
                ->setTlsVerifyPeerName(false);

            $mqtt->connect($connectionSettings, true);

            // Command Payload
            $topic = "audio/{$schedule->device->device_id}/command";
            $payload = json_encode([
                'action' => 'start_recording',
                'duration' => $schedule->duration_seconds,
                'schedule_id' => $schedule->id,
                'timestamp' => now()->timestamp
            ]);

            $mqtt->publish($topic, $payload, 0);
            $mqtt->disconnect();

            // Update Schedule
            $schedule->update([
                'last_run_at' => now(),
                'status' => 'active'
            ]);

            $this->info("Triggered recording for schedule {$schedule->id} (Device: {$schedule->device->device_id})");

        } catch (\Exception $e) {
            $this->error("Failed to trigger schedule {$schedule->id}: " . $e->getMessage());
        }
    }
}
