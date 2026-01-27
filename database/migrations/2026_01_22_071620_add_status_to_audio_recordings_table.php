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
        Schema::table('audio_recordings', function (Blueprint $table) {
            $table->enum('status', ['uploading', 'completed', 'failed'])
                  ->default('completed')
                  ->after('file_size_bytes');
            $table->string('recording_session_id')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio_recordings', function (Blueprint $table) {
            $table->dropColumn(['status', 'recording_session_id']);
        });
    }
};
