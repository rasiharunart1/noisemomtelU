<?php

namespace Tests\Feature;

use App\Models\AudioRecording;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioUploadTest extends TestCase
{
    use RefreshDatabase;

    private function makeDevice(): Device
    {
        return Device::create([
            'device_id' => 'ESP32-TEST01',
            'name' => 'Test',
            'mqtt_topic' => 'audio/ESP32-TEST01/data',
            'status' => 'online',
        ]);
    }

    private function wav(string $name): UploadedFile
    {
        // 44-byte header + 1 second of silence
        $data = str_repeat("\0", 32000);
        $header = 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVE'
            . 'fmt ' . pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16)
            . 'data' . pack('V', strlen($data));

        return UploadedFile::fake()->createWithContent($name, $header . $data);
    }

    public function test_upload_matches_the_on_device_record_by_full_sd_path(): void
    {
        Storage::fake('public');
        $device = $this->makeDevice();

        $onDevice = AudioRecording::create([
            'device_id' => $device->id,
            'filename' => 'ESP32-TEST01_20261125_143000.wav',
            'path' => '',
            'sd_filename' => '/rec/ESP32-TEST01_20261125_143000.wav',
            'duration_seconds' => 1,
            'file_size_bytes' => 32044,
            'status' => 'on_device',
        ]);

        $response = $this->post('/api/upload-audio', [
            'device_id' => $device->device_id,
            'token' => $device->token,
            'duration' => 1,
            'sd_filename' => '/rec/ESP32-TEST01_20261125_143000.wav',
            'audio' => $this->wav('ESP32-TEST01_20261125_143000.wav'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();

        $this->assertSame(1, AudioRecording::count(), 'upload must not create a duplicate row');
        $onDevice->refresh();
        $this->assertSame('completed', $onDevice->status);
        $this->assertSame('recordings/ESP32-TEST01/ESP32-TEST01_20261125_143000.wav', $onDevice->path);
        Storage::disk('public')->assertExists($onDevice->path);
    }

    public function test_upload_without_prior_record_creates_one(): void
    {
        Storage::fake('public');
        $device = $this->makeDevice();

        $this->post('/api/upload-audio', [
            'device_id' => $device->device_id,
            'token' => $device->token,
            'sd_filename' => '/rec/ESP32-TEST01_20261125_150000.wav',
            'audio' => $this->wav('ESP32-TEST01_20261125_150000.wav'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $recording = AudioRecording::firstOrFail();
        $this->assertSame('/rec/ESP32-TEST01_20261125_150000.wav', $recording->sd_filename);
        $this->assertSame('completed', $recording->status);
    }

    /** Split a WAV the way the firmware does and post the chunks. */
    private function postChunk(Device $device, string $bytes, int $offset, int $index, int $total, int $totalSize)
    {
        return $this->post('/api/upload-audio', [
            'device_id' => $device->device_id,
            'token' => $device->token,
            'duration' => 1,
            'sd_filename' => '/rec/ESP32-TEST01_20261125_160000.wav',
            'total_size' => $totalSize,
            'total_chunks' => $total,
            'chunk_index' => $index,
            'chunk_offset' => $offset,
            'audio' => UploadedFile::fake()->createWithContent('ESP32-TEST01_20261125_160000.wav', $bytes),
        ], ['Accept' => 'application/json']);
    }

    public function test_chunked_upload_is_reassembled(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $device = $this->makeDevice();

        $wav = $this->wav('x.wav')->getContent();
        $parts = str_split($wav, 10000); // 4 chunks
        $total = count($parts);

        $offset = 0;
        foreach ($parts as $i => $part) {
            $response = $this->postChunk($device, $part, $offset, $i, $total, strlen($wav));
            if ($i === 1) {
                // The device retries a chunk (e.g. response lost): same offset again
                $response = $this->postChunk($device, $part, $offset, $i, $total, strlen($wav));
            }
            $i < $total - 1 ? $response->assertOk() : $response->assertCreated();
            $offset += strlen($part);
        }

        $recording = AudioRecording::firstOrFail();
        $this->assertSame('completed', $recording->status);
        $this->assertSame(strlen($wav), $recording->file_size_bytes);
        $this->assertSame($wav, Storage::disk('public')->get($recording->path));
        $this->assertEmpty(Storage::disk('local')->files('chunks/ESP32-TEST01'), 'partial file removed');
    }

    public function test_chunk_out_of_order_is_rejected_with_409(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $device = $this->makeDevice();
        $wav = $this->wav('x.wav')->getContent();

        $this->postChunk($device, substr($wav, 20000, 10000), 20000, 2, 4, strlen($wav))
            ->assertStatus(409)
            ->assertJson(['expected_offset' => 0]);
        $this->assertSame(0, AudioRecording::count());
    }

    public function test_first_chunk_must_be_a_wav(): void
    {
        Storage::fake('local');
        $device = $this->makeDevice();

        $this->postChunk($device, str_repeat('x', 1000), 0, 0, 2, 2000)->assertStatus(422);
    }

    public function test_upload_with_wrong_token_is_rejected(): void
    {
        Storage::fake('public');
        $device = $this->makeDevice();

        $this->post('/api/upload-audio', [
            'device_id' => $device->device_id,
            'token' => 'wrong',
            'sd_filename' => '/rec/x.wav',
            'audio' => $this->wav('x.wav'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertSame(0, AudioRecording::count());
    }
}
