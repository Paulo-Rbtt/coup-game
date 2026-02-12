<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('code', 6)->unique();          // room code
            $table->string('phase')->default('lobby');     // GamePhase enum
            $table->json('deck')->default('[]');           // court deck (array of character strings)
            $table->integer('treasury')->default(0);       // central treasury coins
            $table->integer('current_player_index')->default(0);
            $table->json('turn_state')->nullable();        // current action/challenge/block context
            $table->json('event_log')->default('[]');      // immutable log
            $table->integer('turn_number')->default(0);
            $table->unsignedBigInteger('winner_id')->nullable();
            $table->integer('min_players')->default(2);
            $table->integer('max_players')->default(6);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
