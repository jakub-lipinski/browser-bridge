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
        Schema::create('tab_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('target_device_id')->constrained('devices')->cascadeOnDelete();
            $table->text('url')->nullable();
            $table->string('title')->nullable();
            $table->text('encrypted_payload')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['target_device_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tab_commands');
    }
};
