<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasUuids;

    protected $fillable = [
        'device_id',
        'token',
        'name',
        'mqtt_topic',
        'status',
        'last_seen',
        'latitude',
        'longitude',
        'gain',
        'max_db_spl_threshold',
    ];

    protected $casts = [
        'last_seen' => 'datetime',
        'token' => 'encrypted', // Encrypt token in database for security
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($device) {
            if (empty($device->token)) {
                $device->token = \Illuminate\Support\Str::random(32);
            }
        });
    }

    public function fftLogs(): HasMany
    {
        return $this->hasMany(FftLog::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(AudioRecording::class);
    }

    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    public function scopeOffline($query)
    {
        return $query->where('status', 'offline');
    }

    /**
     * Check if device is currently exceeding threshold
     */
    public function isExceedingThreshold()
    {
        $latestLog = $this->fftLogs()->latest('created_at')->first();
        
        if (!$latestLog || !$latestLog->db_spl) {
            return false;
        }
        
        return $latestLog->db_spl >= $this->max_db_spl_threshold;
    }

    /**
     * Get latest dB SPL value
     */
    public function getLatestDbSpl()
    {
        $latestLog = $this->fftLogs()->latest('created_at')->first();
        return $latestLog ? $latestLog->db_spl : null;
    }

    /**
     * Scope for devices exceeding threshold
     */
    public function scopeExceedingThreshold($query)
    {
        return $query->whereHas('fftLogs', function($q) {
            $q->whereRaw('db_spl >= devices.max_db_spl_threshold')
              ->latest('created_at')
              ->limit(1);
        });
    }
}