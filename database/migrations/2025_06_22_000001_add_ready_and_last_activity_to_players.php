<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->boolean('is_ready')->default(false)->after('is_host');
            $table->timestamp('last_activity_at')->nullable()->after('is_ready');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('max_players');
            $table->timestamp('started_at')->nullable()->after('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['is_ready', 'last_activity_at']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['last_activity_at', 'started_at']);
        });
    }
};
