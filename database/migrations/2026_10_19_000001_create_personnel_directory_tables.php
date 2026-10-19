<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_directory_entries', function (Blueprint $table) {
            $table->string('xgh', 120)->primary();
            $table->string('dzyx')->nullable();
            $table->string('yddh')->nullable();
        });

        Schema::create('personnel_directory_syncs', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('row_count');
            $table->timestamp('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_directory_syncs');
        Schema::dropIfExists('personnel_directory_entries');
    }
};
