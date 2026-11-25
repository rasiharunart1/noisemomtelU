<?php

namespace Tests\Feature;

use App\Console\Commands\MqttListenerCommand;
use App\Models\AudioRecording;
use App\Models\Device;
use App\Models\User;
use App\Services\DeviceCommandService;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class DeviceSdSyncTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->device = Device::create([
            'device_id' => 'ESP32-SYNC01',
            'name' => 'Sync test',
            'mqtt_topic' => 'audio/ESP32-SYNC01/data',
            'status' => 'online',
        ]);
    }

    private ?MqttListenerCommand $command = null;

    /** Call a private message handler of one long-running listener instance. */
    private function listener(string $method, array $payload): void
    {
        if (!$this->command) {
            $this->command = new MqttListenerCommand();
            $this->command->setLaravel($this->app);
            $this->command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput()));
        }
        $command = $this->command;

        $ref = new \ReflectionMethod($command, $method);
        $args = $method === 'processRecordingStatus'
            ? ['audio/ESP32-SYNC01/recording_status', json_encode($payload)]
            : [json_encode($payload)];
        $ref->invokeArgs($command, $args);
    }

    private function recording(array $attrs): AudioRecording
    {
        return AudioRecording::create($attrs + [
            'device_id' => $this->device->id,
            'path' => '',
            'file_size_bytes' => 32044,
            'status' => 'on_device',
        ]);
    }

    public function test_completed_registers_recording_on_sd(): void
    {
        $this->listener('processRecordingStatus', [
            'device_id' => 'ESP32-SYNC01', 'status' => 'completed',
            'filename' => '/rec/ESP32-SYNC01_20261125_100000.wav', 'duration' => 60, 'size' => 1920044,
        ]);

        $rec = AudioRecording::firstOrFail();
        $this->assertSame('on_device', $rec->status);
        $this->assertTrue($rec->on_sd);
        $this->assertSame('ESP32-SYNC01_20261125_100000.wav', $rec->filename);
    }

    public function test_late_status_messages_do_not_downgrade_uploaded_recording(): void
    {
        $rec = $this->recording([
            'filename' => 'a.wav', 'sd_filename' => '/rec/a.wav',
            'status' => 'completed', 'path' => 'recordings/ESP32-SYNC01/a.wav',
        ]);

        // "completed" and "uploading" can arrive after the HTTP upload
        $this->listener('processRecordingStatus', ['device_id' => 'ESP32-SYNC01', 'status' => 'completed', 'filename' => '/rec/a.wav']);
        $this->listener('processRecordingStatus', ['device_id' => 'ESP32-SYNC01', 'status' => 'uploading', 'filename' => '/rec/a.wav']);

        $rec->refresh();
        $this->assertSame('completed', $rec->status);
        $this->assertSame('recordings/ESP32-SYNC01/a.wav', $rec->path);
    }

    public function test_file_deleted_matches_moved_file_by_name(): void
    {
        // Laravel knows /rec/b.wav; after upload the device moved it to /rec/up/b.wav
        $rec = $this->recording(['filename' => 'b.wav', 'sd_filename' => '/rec/b.wav', 'on_sd' => true]);

        $this->listener('processRecordingStatus', ['device_id' => 'ESP32-SYNC01', 'status' => 'file_deleted', 'filename' => '/rec/up/b.wav']);

        $this->assertFalse($rec->refresh()->on_sd);
    }

    public function test_file_list_sync_marks_missing_and_registers_unknown_files(): void
    {
        $kept = $this->recording(['filename' => 'kept.wav', 'sd_filename' => '/rec/kept.wav']);
        $gone = $this->recording(['filename' => 'gone.wav', 'sd_filename' => '/rec/gone.wav', 'on_sd' => true]);

        $this->listener('processFileList', [
            'device_id' => 'ESP32-SYNC01', 'list_id' => 42, 'page' => 0, 'last' => false,
            'files' => [['n' => '/rec/up/kept.wav', 's' => 32044, 'u' => 1]],
        ]);
        $this->listener('processFileList', [
            'device_id' => 'ESP32-SYNC01', 'list_id' => 42, 'page' => 1, 'last' => true,
            'files' => [
                ['n' => '/rec/new.wav', 's' => 64044, 'u' => 0],
                ['n' => '/rec/live.wav', 's' => 1000, 'u' => 0, 'r' => 1],
            ],
        ]);

        $this->assertTrue($kept->refresh()->on_sd);
        $this->assertFalse($gone->refresh()->on_sd);

        $new = AudioRecording::where('sd_filename', '/rec/new.wav')->firstOrFail();
        $this->assertSame('on_device', $new->status);
        $this->assertSame(2, $new->duration_seconds);
        $this->assertNull(AudioRecording::where('sd_filename', '/rec/live.wav')->first(), 'file still recording is not registered');
    }

    public function test_incomplete_file_list_does_not_mark_files_missing(): void
    {
        $rec = $this->recording(['filename' => 'c.wav', 'sd_filename' => '/rec/c.wav', 'on_sd' => true]);

        // page 0 was lost
        $this->listener('processFileList', [
            'device_id' => 'ESP32-SYNC01', 'list_id' => 7, 'page' => 1, 'last' => true, 'files' => [],
        ]);

        $this->assertTrue($rec->refresh()->on_sd);
    }

    public function test_health_report_is_stored(): void
    {
        $this->listener('processHealth', [
            'device_id' => 'ESP32-SYNC01', 'fw' => '3.1.0', 'uptime' => 3600,
            'sd' => ['ok' => true, 'total_mb' => 30000, 'free_mb' => 20000, 'pending_files' => 2, 'pending_mb' => 100],
        ]);

        $this->device->refresh();
        $this->assertSame('3.1.0', $this->device->health['fw']);
        $this->assertSame(2, $this->device->health['sd']['pending_files']);
        $this->assertNotNull($this->device->health_updated_at);
    }

    public function test_command_endpoint_sends_upload_all_with_force(): void
    {
        $this->mock(DeviceCommandService::class, function ($mock) {
            $mock->shouldReceive('send')->once()->with('ESP32-SYNC01', 'upload_all', ['force' => true]);
        });

        $this->actingAs(User::factory()->create())
            ->postJson(route('devices.command', $this->device), ['action' => 'upload_all', 'force' => true])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_command_endpoint_rejects_unknown_action(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('devices.command', $this->device), ['action' => 'format_sd'])
            ->assertStatus(422);
    }

    public function test_device_page_and_dashboard_render_with_health(): void
    {
        $this->device->update([
            'status' => 'recording',
            'health' => ['fw' => '3.1.0', 'sd' => ['ok' => true, 'free_mb' => 2048, 'pending_files' => 1], 'wifi' => ['rssi' => -65]],
            'health_updated_at' => now(),
        ]);
        $this->recording(['filename' => 'd.wav', 'sd_filename' => '/rec/d.wav', 'on_sd' => true]);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('devices.show', $this->device))
            ->assertOk()
            ->assertSee('Device Health')
            ->assertSee('SD &#10003;', false)
            ->assertDontSee($this->device->token ? '"token":"' . $this->device->token . '"' : 'x', false);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('2.0 GB free');

        $this->actingAs($user)->get(route('devices.index'))
            ->assertOk()
            ->assertDontSee($this->device->token, false);
    }

    public function test_schedule_times_are_entered_in_local_time(): void
    {
        config(['app.display_timezone' => 'Asia/Jakarta']);
        $start = now('Asia/Jakarta')->addDay()->setTime(8, 0);

        $this->actingAs(User::factory()->create())
            ->post(route('devices.schedules.store', $this->device), [
                'start_time' => $start->format('Y-m-d\TH:i'),
                'end_time' => $start->copy()->addHours(2)->format('Y-m-d\TH:i'),
                'interval_minutes' => 30,
                'duration_seconds' => 60,
            ])->assertSessionHasNoErrors();

        $schedule = $this->device->schedules()->firstOrFail();
        // 08:00 WIB is 01:00 UTC
        $this->assertSame('01:00', $schedule->start_time->format('H:i'));
    }

    public function test_schedule_interval_must_cover_duration(): void
    {
        $start = now()->addDay();

        $this->actingAs(User::factory()->create())
            ->post(route('devices.schedules.store', $this->device), [
                'start_time' => $start->format('Y-m-d\TH:i'),
                'end_time' => $start->copy()->addHour()->format('Y-m-d\TH:i'),
                'interval_minutes' => 1,
                'duration_seconds' => 300,
            ])->assertSessionHasErrors('interval_minutes');
    }
}
