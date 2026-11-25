<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AudioRecording;
use App\Models\Device;
use App\Services\DeviceCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AudioUploadController extends Controller
{
    /** Largest recording accepted (4 h of 16 kHz/16-bit mono is ~460 MB). */
    private const MAX_TOTAL_BYTES = 520 * 1024 * 1024;

    /**
     * Handle the incoming audio recording upload from ESP32.
     *
     * Small files arrive in one request. Large files arrive in chunks
     * (total_chunks > 1) because Cloudflare rejects bodies over 100 MB; the
     * chunks are appended to storage/app/private/chunks/ and moved into
     * place when the last one arrives.
     *
     * The device keeps its own copy on the SD card. The server copy is
     * stored under storage/app/public/recordings/{device_id}/.
     */
    public function upload(Request $request, DeviceCommandService $commands)
    {
        $totalChunks = max(1, (int) $request->input('total_chunks', 1));
        $chunked = $totalChunks > 1;

        Log::info('Audio upload request received', [
            'device_id' => $request->input('device_id'),
            'sd_filename' => $request->input('sd_filename'),
            'chunk' => $chunked ? ($request->input('chunk_index') + 1) . "/{$totalChunks}" : null,
            'has_file' => $request->hasFile('audio'),
        ]);

        $rules = [
            'device_id'   => 'required|exists:devices,device_id',
            'token'       => 'required|string',
            'duration'    => 'nullable|integer|min:0',
            'sd_filename' => 'nullable|string|max:255',
        ];
        if ($chunked) {
            // Chunks after the first have no WAV header, so no mime check
            $rules += [
                'audio'        => 'required|file|max:51200', // 50 MB per chunk
                'total_chunks' => 'required|integer|min:2|max:2000',
                'chunk_index'  => 'required|integer|min:0|lt:total_chunks',
                'chunk_offset' => 'required|integer|min:0',
                'total_size'   => 'required|integer|min:45|max:' . self::MAX_TOTAL_BYTES,
            ];
        } else {
            $rules['audio'] = 'required|file|mimes:wav,mp3,bin,aac|max:512000'; // 500MB max
        }

        $validator = Validator::make($request->all(), $rules);
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

        $duration = $request->filled('duration') ? (int) $request->input('duration') : null;

        if ($chunked) {
            return $this->receiveChunk($request, $device, $file, $sdFilename, $duration, $commands);
        }

        $existing = AudioRecording::forSdFile($device->id, $sdFilename)->first();
        $directory = 'recordings/' . $device->device_id;
        $filename = $this->serverFilename($directory, $sdFilename, $existing);

        try {
            $path = $file->storeAs($directory, $filename, 'public');
            if ($path === false) {
                throw new \RuntimeException('storeAs returned false');
            }
        } catch (\Exception $e) {
            return $this->storeFailed($device, $e);
        }

        return $this->finishUpload($device, $existing, $sdFilename, $filename, $path, $file->getSize(), $duration, $commands);
    }

    /**
     * Append one chunk to the partial file. Chunks must arrive in order;
     * chunk_offset 0 (re)starts the upload, so a device that retries from
     * the beginning always works.
     */
    private function receiveChunk(Request $request, Device $device, UploadedFile $file, string $sdFilename, ?int $duration, DeviceCommandService $commands): JsonResponse
    {
        $offset = (int) $request->input('chunk_offset');
        $totalSize = (int) $request->input('total_size');
        $index = (int) $request->input('chunk_index');
        $totalChunks = (int) $request->input('total_chunks');

        $local = Storage::disk('local');
        $chunkDir = 'chunks/' . $device->device_id;
        $local->makeDirectory($chunkDir);
        $uploadId = sha1($sdFilename . '|' . $totalSize);
        $partPath = $local->path("{$chunkDir}/{$uploadId}.part");

        if ($offset === 0) {
            $this->cleanupStaleChunks($local->path($chunkDir));

            $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 12);
            if (substr($head, 0, 4) !== 'RIFF' || substr($head, 8, 4) !== 'WAVE') {
                return response()->json(['success' => false, 'error' => 'First chunk is not a WAV file'], 422);
            }
        }

        clearstatcache(true, $partPath);
        $current = is_file($partPath) ? filesize($partPath) : 0;
        if ($offset > $current) {
            // A chunk went missing; the device restarts from the beginning
            return response()->json([
                'success' => false,
                'error' => 'Chunk out of order',
                'expected_offset' => $current,
            ], 409);
        }

        $out = fopen($partPath, 'c');
        $in = fopen($file->getRealPath(), 'rb');
        if (!$out || !$in) {
            return $this->storeFailed($device, new \RuntimeException('Cannot open chunk files'));
        }
        // Writing at $offset also handles a retried chunk (overwrites it)
        ftruncate($out, $offset);
        fseek($out, $offset);
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        $received = $offset + $file->getSize();

        if ($received > $totalSize) {
            @unlink($partPath);
            return response()->json(['success' => false, 'error' => 'More data than total_size'], 422);
        }

        if ($received < $totalSize) {
            return response()->json([
                'success' => true,
                'chunk' => $index + 1,
                'total_chunks' => $totalChunks,
                'next_offset' => $received,
            ], 200);
        }

        // Last chunk: move the complete WAV into public storage
        $existing = AudioRecording::forSdFile($device->id, $sdFilename)->first();
        $directory = 'recordings/' . $device->device_id;
        $filename = $this->serverFilename($directory, $sdFilename, $existing);
        $path = "{$directory}/{$filename}";

        try {
            $public = Storage::disk('public');
            $public->makeDirectory($directory);
            $target = $public->path($path);
            if (!@rename($partPath, $target)) {
                if (!copy($partPath, $target)) {
                    throw new \RuntimeException('Cannot move assembled file');
                }
                @unlink($partPath);
            }
        } catch (\Exception $e) {
            return $this->storeFailed($device, $e);
        }

        return $this->finishUpload($device, $existing, $sdFilename, $filename, $path, $totalSize, $duration, $commands);
    }

    private function finishUpload(Device $device, ?AudioRecording $existing, string $sdFilename, string $filename, string $path, int $size, ?int $duration, DeviceCommandService $commands): JsonResponse
    {
        try {
            $attributes = [
                'filename'         => $filename,
                'path'             => $path,
                'duration_seconds' => $duration ?? $existing?->duration_seconds,
                'file_size_bytes'  => $size,
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
                'size' => $size,
                'recording_id' => $recording->id
            ]);
        } catch (\Exception $e) {
            return $this->storeFailed($device, $e);
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
            'size'     => $size
        ], 201);
    }

    private function storeFailed(Device $device, \Exception $e): JsonResponse
    {
        Log::error('Failed to save audio recording', [
            'device_id' => $device->device_id,
            'error' => $e->getMessage()
        ]);
        return response()->json([
            'success' => false,
            'error' => 'Failed to save recording'
        ], 500);
    }

    /** Remove partial uploads that were abandoned more than a day ago. */
    private function cleanupStaleChunks(string $dir): void
    {
        foreach (glob($dir . '/*.part') ?: [] as $part) {
            if (filemtime($part) < time() - 86400) {
                @unlink($part);
            }
        }
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
