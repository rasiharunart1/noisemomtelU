<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support on-device (SD card) recordings with on-demand upload.
     */
    public function up(): void
    {
        Schema::table('audio_recordings', function (Blueprint $table) {
            $table->string('sd_filename')->nullable()->after('path');
            $table->unsignedInteger('uploaded_at')->nullable()->after('status');
        });

        // Widen the status enum to include on-device lifecycle states
        DB::statement("ALTER TABLE audio_recordings DROP CONSTRAINT IF EXISTS audio_recordings_status_check");
        DB::statement("ALTER TABLE audio_recordings ADD CONSTRAINT audio_recordings_status_check CHECK (status IN ('uploading', 'completed', 'failed', 'on_device', 'requested', 'upload_failed'))");

        // Prevent duplicate rows when the device re-uploads the same SD file
        Schema::table('audio_recordings', function (Blueprint $table) {
            $table->unique(['device_id', 'sd_filename']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio_recordings', function (Blueprint $table) {
            $table->dropUnique(['device_id', 'sd_filename']);
        });

        DB::statement("ALTER TABLE audio_recordings DROP CONSTRAINT IF EXISTS audio_recordings_status_check");
        DB::statement("ALTER TABLE audio_recordings ADD CONSTRAINT audio_recordings_status_check CHECK (status IN ('uploading', 'completed', 'failed'))");

        Schema::table('audio_recordings', function (Blueprint $table) {
            $table->dropColumn(['sd_filename', 'uploaded_at']);
        });
    }
};
