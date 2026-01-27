<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fft_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_id');
            
            // Audio metrics
            $table->decimal('rms', 10, 6);
            $table->decimal('peak_amplitude', 10, 6);
            $table->decimal('noise_floor', 10, 6);
            $table->decimal('gain', 10, 4);
            
            // FFT metrics
            $table->decimal('peak_frequency', 12, 4);
            $table->decimal('peak_magnitude', 12, 4);
            $table->decimal('total_energy', 16, 4);
            $table->decimal('band_low', 14, 4);
            $table->decimal('band_mid', 14, 4);
            $table->decimal('band_high', 14, 4);
            $table->decimal('spectral_centroid', 12, 4);
            $table->decimal('zcr', 8, 6);
            
            $table->timestamp('created_at');
            
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->index(['device_id', 'created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('fft_logs');
    }
};