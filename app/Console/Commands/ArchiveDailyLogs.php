<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FftLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ArchiveDailyLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:archive-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export all FFT logs to CSV and clear the database table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('ArchiveDailyLogs: Command started.');
        $this->info('Starting daily log archival process...');

        // 1. Check if there are logs to archive
        $count = FftLog::count();
        Log::info("ArchiveDailyLogs: Found {$count} logs.");
        
        if ($count === 0) {
            $this->info('No logs found to archive.');
            Log::info('ArchiveDailyLogs: No logs found, exiting.');
            return;
        }

        $this->info("Found {$count} logs. preparing export...");

        // 2. Generate Filename
        $date = Carbon::now()->format('Y-m-d_His');
        $filename = "logs_archive_{$date}.csv";
        $path = "exports/logs/{$filename}";

        // 3. Create CSV Content (Memory efficient chunking could be better for massive datasets, 
        // but for now simple collection output is fine if not millions of rows)
        
        // Ensure directory exists
        if (!Storage::exists('exports/logs')) {
            Storage::makeDirectory('exports/logs');
        }

        // Use Storage::path() to ensure we write to the correct disk location (e.g. storage/app/private)
        $absPath = Storage::path($path);
        
        $handle = fopen($absPath, 'w');

        // Headers
        fputcsv($handle, [
            'ID', 'Device ID', 'dB SPL', 'RMS', 'Peak Freq', 
            'Band Low', 'Band Mid', 'Band High', 'Creation Time'
        ]);

        // Process in chunks to avoid memory limit
        FftLog::orderBy('created_at')->chunk(1000, function ($logs) use ($handle) {
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->device_id,
                    $log->db_spl,
                    $log->rms,
                    $log->peak_frequency,
                    $log->band_low,
                    $log->band_mid,
                    $log->band_high,
                    $log->created_at
                ]);
            }
        });

        fclose($handle);

        $this->info("Logs exported successfully to: {$path}");

        // 4. Clear Database
        // Use truncate for speed if it's the whole table, or delete()
        // Here we use query builder delete to be safe or Model truncate
        FftLog::truncate();

        $this->info('Database table fft_logs has been truncated.');
    }
}
