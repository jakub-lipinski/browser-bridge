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
        Schema::table('history_items', function (Blueprint $table) {
            $table->unique(['device_id', 'url', 'visited_at'], 'history_items_device_url_visited_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('history_items', function (Blueprint $table) {
            $table->dropUnique('history_items_device_url_visited_unique');
        });
    }
};
