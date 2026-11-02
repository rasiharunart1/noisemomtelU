<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\AudioRecording;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class AudioUploadController extends Controller
{
    /**
     * Handle the incoming audio recording upload from ESP32.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        // Log incoming request for debugging
        \Log::info('Audio upload request received', [
            'device_id' => $request->input('device_id'),
            'has_file' => $request->hasFile('audio'),
            'content_type' => $request->header('Content-Type')
        ]);

        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:devices,device_id',
            'token'     => 'required|string',
            'audio'     => 'required|file|mimes:wav,mp3,bin,aac|max:512000', // 500MB max (long SD recordings)
            'duration'  => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            \Log::warning('Audio upload validation failed', [
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 400);
        }

        $device = Device::where('device_id', $request->device_id)->first();

        // Security check: Verify token
        // Note: tokens are stored encrypted in the model
        if ($device->token !== $request->token) {
            \Log::warning('Invalid device token', [
                'device_id' => $request->device_id
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Invalid device token'
            ], 403);
        }

        if ($request->hasFile('audio')) {
            $file = $request->file('audio');
            
            // Validate file is readable
            if (!$file->isValid()) {
                \Log::error('Uploaded file is not valid', [
                    'device_id' => $device->device_id,
                    'error' => $file->getError()
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'File upload failed'
                ], 400);
            }

            // The ESP32 reports the SD card path (e.g. /rec_ESP32-XXX_123.wav)
            // as the original filename. Use it to match an existing on-device
            // record so a re-upload updates instead of duplicating.
            $sdFilename = $file->getClientOriginalName();
            $existing = AudioRecording::where('device_id', $device->id)
                ->where('sd_filename', $sdFilename)
                ->first();

            $filename = 'rec_' . $device->device_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            try {
                // Store in public disk
                $path = $file->storeAs('recordings', $filename, 'public');

                if ($existing) {
                    // Replace the stored file of the previous attempt, if any
                    if ($existing->path && $existing->path !== $path) {
                        Storage::disk('public')->delete($existing->path);
                    }
                    $existing->update([
                        'filename'         => $filename,
                        'path'             => $path,
                        'duration_seconds' => $request->input('duration', $existing->duration_seconds),
                        'file_size_bytes'  => $file->getSize(),
                        'status'           => 'completed',
                        'uploaded_at'      => now()->timestamp,
                    ]);
                    $recording = $existing;
                } else {
                    // Save to database
                    $recording = AudioRecording::create([
                        'device_id'        => $device->id,
                        'filename'         => $filename,
                        'path'             => $path,
                        'sd_filename'      => $sdFilename,
                        'duration_seconds' => $request->input('duration'),
                        'file_size_bytes'  => $file->getSize(),
                        'status'           => 'completed',
                        'uploaded_at'      => now()->timestamp,
                    ]);
                }

                \Log::info('Audio uploaded successfully', [
                    'device_id' => $device->device_id,
                    'filename' => $filename,
                    'size' => $file->getSize(),
                    'recording_id' => $recording->id
                ]);

                // Tell the device to free up SD card space now that the
                // recording is safely stored on the server.
                $this->sendDeleteFileCommand($device, $sdFilename);

                return response()->json([
                    'success' => true,
                    'message' => 'Audio uploaded successfully',
                    'id'      => $recording->id,
                    'filename' => $filename,
                    'size'    => $file->getSize()
                ], 201);

            } catch (\Exception $e) {
                \Log::error('Failed to save audio recording', [
                    'device_id' => $device->device_id,
                    'error' => $e->getMessage()
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to save recording'
                ], 500);
            }
        }

        \Log::warning('No audio file found in request', [
            'device_id' => $request->device_id
        ]);
        return response()->json([
            'success' => false,
            'error' => 'No audio file found'
        ], 400);
    }

    /**
     * Ask the device to delete the uploaded file from its SD card.
     * Best-effort: failures are logged but never fail the upload response.
     */
    private function sendDeleteFileCommand(Device $device, string $sdFilename): void
    {
        try {
            $host = Setting::get('mqtt_host', env('MQTT_HOST'));
            $port = (int) Setting::get('mqtt_port', env('MQTT_PORT', 8883));
            $username = Setting::get('mqtt_username', env('MQTT_USERNAME'));
            $password = Setting::get('mqtt_password', env('MQTT_PASSWORD'));

            $clientId = 'laravel_upload_' . uniqid();
            $mqtt = new MqttClient($host, $port, $clientId);

            $connectionSettings = (new ConnectionSettings)
                ->setUsername($username)
                ->setPassword($password)
                ->setUseTls(true)
                ->setTlsSelfSignedAllowed(true)
                ->setTlsVerifyPeer(false)
                ->setTlsVerifyPeerName(false);

            $mqtt->connect($connectionSettings, true);

            $payload = json_encode([
                'action'    => 'delete_file',
                'timestamp' => now()->timestamp,
                'device_id' => $device->device_id,
                'filename'  => $sdFilename,
            ]);

            $mqtt->publish("audio/{$device->device_id}/command", $payload, 0);
            $mqtt->disconnect();

            \Log::info('delete_file command sent', [
                'device_id' => $device->device_id,
                'filename' => $sdFilename
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to send delete_file command (file stays on SD card)', [
                'device_id' => $device->device_id,
                'filename' => $sdFilename,
                'error' => $e->getMessage()
            ]);
        }
    }
}
