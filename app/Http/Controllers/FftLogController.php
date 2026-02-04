<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\FftLog;
use Illuminate\Http\Request;

class FftLogController extends Controller
{
    /**
     * Display FFT logs with filters
     */
    public function index(Request $request)
    {
        $query = FftLog::with('device');

        // Filter by device
        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

        // Filter by peak frequency range
        if ($request->filled('min_frequency')) {
            $query->where('peak_frequency', '>=', $request->min_frequency);
        }

        if ($request->filled('max_frequency')) {
            $query->where('peak_frequency', '<=', $request->max_frequency);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate(50);

        $devices = Device::orderBy('device_id')->get();

        return view('logs.fft', compact('logs', 'devices'));
    }

    /**
     * Export logs as CSV
     */
    public function export(Request $request)
    {
        $query = FftLog::with('device');

        // Apply same filters as index
        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        $filename = 'fft_logs_' . now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            
            // CSV Header
            fputcsv($file, [
                'Timestamp',
                'Device ID',
                'RMS',
                'Peak Amplitude',
                'Noise Floor',
                'Gain',
                'Peak Frequency',
                'Peak Magnitude',
                'Total Energy',
                'Band Low',
                'Band Mid',
                'Band High',
                'Spectral Centroid',
                'ZCR'
            ]);

            // CSV Data
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->created_at,
                    $log->device->device_id ?? 'Unknown',
                    $log->rms,
                    $log->peak_amplitude,
                    $log->noise_floor,
                    $log->gain,
                    $log->peak_frequency,
                    $log->peak_magnitude,
                    $log->total_energy,
                    $log->band_low,
                    $log->band_mid,
                    $log->band_high,
                    $log->spectral_centroid,
                    $log->zcr,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * API endpoint for chart data
     */
    public function chartData(Request $request, Device $device)
    {
        $hours = $request->input('hours', 1);
        
        $logs = $device->fftLogs()
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'labels' => $logs->pluck('created_at')->map(fn($date) => $date->format('H:i:s')),
            'rms' => $logs->pluck('rms'),
            'peak_frequency' => $logs->pluck('peak_frequency'),
            'total_energy' => $logs->pluck('total_energy'),
            'spectral_centroid' => $logs->pluck('spectral_centroid'),
        ]);
    }

    /**
     * Delete all FFT logs
     */
    public function destroyAll()
    {
        FftLog::truncate();

        return redirect()->route('logs.fft')
            ->with('success', 'All FFT logs have been cleared successfully.');
    }
}
