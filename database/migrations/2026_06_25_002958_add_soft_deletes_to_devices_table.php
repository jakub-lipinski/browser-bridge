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
        Schema::table('devices', function (Blueprint $table) {
            $table->softDeletes();
            $table->boolean('is_active')->default(true)->after('last_seen_at');
            $table->timestamp('disconnected_at')->nullable()->after('is_active');
            $table->string('removal_reason')->nullable()->after('disconnected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['is_active', 'disconnected_at', 'removal_reason']);
        });
    }
};
