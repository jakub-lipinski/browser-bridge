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
        Schema::create('bookmark_sync_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('source_device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('target_device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('mode')->default('safe_folder');
            $table->string('direction')->default('source_to_target');
            $table->string('target_scope')->default('browserbridge_folder');
            $table->string('selected_target_folder_id')->nullable();
            $table->boolean('auto_sync_enabled')->default(false);
            $table->unsignedInteger('auto_sync_interval_minutes')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['source_device_id', 'target_device_id']);
            $table->index(['mode', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmark_sync_profiles');
    }
};
