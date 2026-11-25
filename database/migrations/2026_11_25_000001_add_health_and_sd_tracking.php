<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - devices.health: last health report from the firmware (audio/{id}/health)
     * - audio_recordings.on_sd: whether the device still has its SD copy
     *   (null = unknown, e.g. recordings made before firmware v3.1)
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->json('health')->nullable();
            $table->timestamp('health_updated_at')->nullable();
        });

        Schema::table('audio_recordings', function (Blueprint $table) {
            $table->boolean('on_sd')->nullable();
            $table->timestamp('sd_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audio_recordings', function (Blueprint $table) {
            $table->dropColumn(['on_sd', 'sd_checked_at']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['health', 'health_updated_at']);
        });
    }
};
