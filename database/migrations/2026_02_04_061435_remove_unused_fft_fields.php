<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fft_logs', function (Blueprint $table) {
            $table->dropColumn([
                'peak_amplitude',
                'noise_floor',
                'gain',
                'peak_magnitude',
                'spectral_centroid',
                'zcr'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fft_logs', function (Blueprint $table) {
            $table->decimal('peak_amplitude', 10, 6)->nullable();
            $table->decimal('noise_floor', 10, 6)->nullable();
            $table->decimal('gain', 10, 4)->default(1);
            $table->decimal('peak_magnitude', 12, 4)->nullable();
            $table->decimal('spectral_centroid', 12, 4)->nullable();
            $table->decimal('zcr', 8, 6)->nullable();
        });
    }
};
