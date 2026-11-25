<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'filename',
        'path',
        'sd_filename',
        'duration_seconds',
        'file_size_bytes',
        'status',
        'recording_session_id',
        'uploaded_at',
        'on_sd',
        'sd_checked_at',
    ];

    protected $casts = [
        'on_sd' => 'boolean',
        'sd_checked_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function getUrlAttribute()
    {
        return asset('storage/' . $this->path);
    }

    public function isOnServer(): bool
    {
        return $this->status === 'completed' && !empty($this->path);
    }

    /**
     * The device moves files on its SD card (/rec/x.wav -> /rec/up/x.wav after
     * upload, firmware v2 kept them at /x.wav), so match on the file name in
     * every location the firmware uses.
     */
    public static function sdPathCandidates(string $sdPath): array
    {
        $name = basename($sdPath);

        return array_values(array_unique([
            $sdPath,
            '/' . $name,
            '/rec/' . $name,
            '/rec/up/' . $name,
        ]));
    }

    public function scopeForSdFile(Builder $query, string $deviceUuid, string $sdPath): Builder
    {
        return $query->where('device_id', $deviceUuid)
            ->whereIn('sd_filename', self::sdPathCandidates($sdPath));
    }
}
