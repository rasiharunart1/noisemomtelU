<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'filename',
        'path',
        'duration_seconds',
        'file_size_bytes',
        'status',
        'recording_session_id',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function getUrlAttribute()
    {
        return asset('storage/' . $this->path);
    }
}
