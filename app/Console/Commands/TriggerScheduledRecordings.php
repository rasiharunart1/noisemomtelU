<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RecordingSchedule;
use App\Services\DeviceCommandService;
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
        if (!$schedule->device || $schedule->device->status === 'offline') {
            $this->warn("Device {$schedule->device_id} is offline or missing. Skipping.");
            return;
        }
        if ($schedule->device->status === 'recording') {
            // The firmware ignores start_recording while recording; retry next minute
            $this->warn("Device {$schedule->device->device_id} is still recording. Skipping this run.");
            return;
        }

        try {
            app(DeviceCommandService::class)->send($schedule->device->device_id, 'start_recording', [
                'duration' => $schedule->duration_seconds,
                'schedule_id' => $schedule->id,
            ]);

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
