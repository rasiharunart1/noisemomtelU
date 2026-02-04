<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class FftLog extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'device_id',
        'rms',
        'db_spl',
        'peak_frequency',
        'total_energy',
        'band_low',
        'band_mid',
        'band_high',
        'created_at',
    ];
    protected $casts = [
        'created_at' => 'datetime',
    ];
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}