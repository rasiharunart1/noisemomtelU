<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\FftLogController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Devices - Full CRUD
    Route::resource('devices', DeviceController::class);
    Route::post('/devices/{device}/gain', [DeviceController::class, 'updateGain'])->name('devices.gain');
    Route::post('/devices/{device}/threshold', [DeviceController::class, 'updateThreshold'])->name('devices.threshold');
    Route::post('/devices/{device}/record/start', [DeviceController::class, 'startRecording'])->name('devices.record.start');
    Route::post('/devices/{device}/record/stop', [DeviceController::class, 'stopRecording'])->name('devices.record.stop');
    Route::post('/devices/{device}/wifi/reset', [DeviceController::class, 'resetWifi'])->name('devices.wifi.reset');
    Route::post('/devices/{device}/wifi/update', [DeviceController::class, 'updateWifi'])->name('devices.wifi.update');
    
    // Audio Recordings Download
    Route::get('/recordings/{recording}/download', [DeviceController::class, 'downloadRecording'])->name('recordings.download');
    Route::post('/recordings/bulk-download', [DeviceController::class, 'bulkDownload'])->name('recordings.bulk-download');
    
    // FFT Logs
    Route::get('/logs/fft', [FftLogController::class, 'index'])->name('logs.fft');
    Route::get('/logs/fft/export', [FftLogController::class, 'export'])->name('logs.fft.export');
    
    // Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('settings');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/check-connection', [SettingController::class, 'testConnection'])->name('settings.check_connection');
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// API Routes (authenticated)
Route::middleware('auth:sanctum')->prefix('api')->group(function () {
    Route::get('/device-status', [DashboardController::class, 'getDeviceStatus']);
    Route::get('/devices/{device}/realtime', [DeviceController::class, 'realtimeData']);
    Route::get('/devices/{device}/chart-data', [FftLogController::class, 'chartData']);
    Route::get('/settings/current', [SettingController::class, 'getSettings']);
});

require __DIR__.'/auth.php';

