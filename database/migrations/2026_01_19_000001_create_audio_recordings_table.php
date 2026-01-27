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
        Schema::dropIfExists('audio_recordings');
        Schema::create('audio_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('device_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('path');
            $table->integer('duration_seconds')->nullable();
            $table->bigInteger('file_size_bytes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio_recordings');
    }
};
