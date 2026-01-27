<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\AudioRecording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
            'audio'     => 'required|file|mimes:wav,mp3,bin,aac|max:10240', // 10MB Max
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

            $filename = 'rec_' . $device->device_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            try {
                // Store in public disk
                $path = $file->storeAs('recordings', $filename, 'public');

                // Save to database
                $recording = AudioRecording::create([
                    'device_id'        => $device->id,
                    'filename'         => $filename,
                    'path'             => $path,
                    'duration_seconds' => $request->input('duration'),
                    'file_size_bytes'  => $file->getSize(),
                    'status'           => 'completed',
                ]);

                \Log::info('Audio uploaded successfully', [
                    'device_id' => $device->device_id,
                    'filename' => $filename,
                    'size' => $file->getSize(),
                    'recording_id' => $recording->id
                ]);

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
}
