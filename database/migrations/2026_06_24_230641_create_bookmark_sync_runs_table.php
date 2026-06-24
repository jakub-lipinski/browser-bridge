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
        Schema::create('bookmark_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('bookmark_sync_profiles')->cascadeOnDelete();
            $table->foreignId('source_device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('target_device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('mode');
            $table->string('status')->default('preview');
            $table->unsignedInteger('added_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('moved_count')->default(0);
            $table->unsignedInteger('deleted_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('preview_json')->nullable();
            $table->json('result_json')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'status']);
            $table->index(['source_device_id', 'target_device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmark_sync_runs');
    }
};
