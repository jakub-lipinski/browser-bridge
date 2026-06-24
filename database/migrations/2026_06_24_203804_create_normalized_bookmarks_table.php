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
        Schema::create('normalized_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('parent_external_id')->nullable();
            $table->string('type');
            $table->string('title')->nullable();
            $table->text('url')->nullable();
            $table->json('path_json')->nullable();
            $table->timestamp('date_added')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'external_id'], 'normalized_bookmarks_device_external_unique');
            $table->index(['device_id', 'type']);
            $table->index(['device_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('normalized_bookmarks');
    }
};
