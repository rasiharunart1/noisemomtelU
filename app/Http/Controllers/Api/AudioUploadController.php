<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AudioRecording;
use App\Models\Device;
use App\Services\DeviceCommandService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AudioUploadController extends Controller
{
    /**
     * Handle the incoming audio recording upload from ESP32.
     *
     * The device keeps its own copy on the SD card. The server copy is
     * stored under storage/app/public/recordings/{device_id}/.
     */
    public function upload(Request $request, DeviceCommandService $commands)
    {
        Log::info('Audio upload request received', [
            'device_id' => $request->input('device_id'),
            'sd_filename' => $request->input('sd_filename'),
            'has_file' => $request->hasFile('audio'),
        ]);

        $validator = Validator::make($request->all(), [
            'device_id'   => 'required|exists:devices,device_id',
            'token'       => 'required|string',
            'audio'       => 'required|file|mimes:wav,mp3,bin,aac|max:512000', // 500MB max (long SD recordings)
            'duration'    => 'nullable|integer|min:0',
            'sd_filename' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            Log::warning('Audio upload validation failed', [
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $device = Device::where('device_id', $request->device_id)->first();

        if (!hash_equals((string) $device->token, (string) $request->token)) {
            Log::warning('Invalid device token', ['device_id' => $request->device_id]);
            return response()->json([
                'success' => false,
                'error' => 'Invalid device token'
            ], 403);
        }

        $file = $request->file('audio');
        if (!$file->isValid()) {
            Log::error('Uploaded file is not valid', [
                'device_id' => $device->device_id,
                'error' => $file->getError()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'File upload failed'
            ], 400);
        }

        // PHP strips directories from the multipart filename, so the device
        // sends its full SD card path separately. Older firmware only sends
        // the filename (stored at the SD root).
        $sdFilename = $request->input('sd_filename')
            ?: '/' . ltrim($file->getClientOriginalName(), '/');
        if (!str_starts_with($sdFilename, '/')) {
            $sdFilename = '/' . $sdFilename;
        }

        $existing = AudioRecording::forSdFile($device->id, $sdFilename)->first();

        $directory = 'recordings/' . $device->device_id;
        $filename = $this->serverFilename($directory, $sdFilename, $existing);

        try {
            $path = $file->storeAs($directory, $filename, 'public');
            if ($path === false) {
                throw new \RuntimeException('storeAs returned false');
            }

            $attributes = [
                'filename'         => $filename,
                'path'             => $path,
                'duration_seconds' => $request->filled('duration')
                    ? (int) $request->input('duration')
                    : $existing?->duration_seconds,
                'file_size_bytes'  => $file->getSize(),
                'status'           => 'completed',
                'uploaded_at'      => now()->timestamp,
                // The device keeps its copy (moved to /rec/up) unless the
                // delete_file command below removes it
                'sd_filename'      => $sdFilename,
                'on_sd'            => true,
                'sd_checked_at'    => now(),
            ];

            if ($existing) {
                // Replace the stored file of a previous upload, if any
                if ($existing->path && $existing->path !== $path) {
                    Storage::disk('public')->delete($existing->path);
                }
                $existing->update($attributes);
                $recording = $existing;
            } else {
                $recording = AudioRecording::create($attributes + [
                    'device_id' => $device->id,
                ]);
            }

            $device->update(['last_seen' => now()]);

            Log::info('Audio uploaded successfully', [
                'device_id' => $device->device_id,
                'filename' => $filename,
                'size' => $file->getSize(),
                'recording_id' => $recording->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save audio recording', [
                'device_id' => $device->device_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to save recording'
            ], 500);
        }

        // By default the recording stays on the SD card as a second copy.
        // Only free SD space when the admin enabled it in Settings.
        if (DeviceCommandService::deleteFromSdAfterUpload()) {
            try {
                $commands->send($device->device_id, 'delete_file', ['filename' => $sdFilename]);
            } catch (\Exception $e) {
                Log::warning('Failed to send delete_file command (file stays on SD card)', [
                    'device_id' => $device->device_id,
                    'filename' => $sdFilename,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Audio uploaded successfully',
            'id'       => $recording->id,
            'filename' => $filename,
            'size'     => $file->getSize()
        ], 201);
    }

    /**
     * Name the server copy after the SD file (e.g. ESP32-XXX_20261125_143000.wav)
     * without overwriting a file that belongs to another recording.
     */
    private function serverFilename(string $directory, string $sdFilename, ?AudioRecording $existing): string
    {
        $base = pathinfo($sdFilename, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9_\-]/', '_', $base) ?: 'rec';
        $filename = $base . '.wav';

        $disk = Storage::disk('public');
        $ownPath = $existing?->path;
        if ($disk->exists("{$directory}/{$filename}") && $ownPath !== "{$directory}/{$filename}") {
            $filename = $base . '_' . time() . '.wav';
        }

        return $filename;
    }
}
