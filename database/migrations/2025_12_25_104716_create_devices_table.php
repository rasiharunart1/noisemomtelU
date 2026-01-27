<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_id')->unique();
            $table->string('name')->nullable();
            $table->string('mqtt_topic');
            $table->string('status')->default('offline');
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
            
            $table->index('device_id');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};