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
        if (Schema::hasColumn('fft_logs', 'db_spl')) {
            Schema::table('fft_logs', function (Blueprint $table) {
                $table->decimal('db_spl', 8, 2)->nullable()->change();
            });

            return;
        }

        Schema::table('fft_logs', function (Blueprint $table) {
            $table->decimal('db_spl', 8, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('fft_logs', 'db_spl')) {
            return;
        }

        Schema::table('fft_logs', function (Blueprint $table) {
            $table->float('db_spl')->nullable()->change();
        });
    }
};
