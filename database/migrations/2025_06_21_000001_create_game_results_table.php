<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('player_id')->nullable();
            $table->string('player_name');
            $table->integer('seat')->default(0);
            $table->integer('coins')->default(0);
            $table->json('influences')->default('[]');      // remaining hidden cards at game end
            $table->json('revealed')->default('[]');         // revealed (dead) cards
            $table->boolean('is_winner')->default(false);
            $table->boolean('is_alive')->default(false);
            $table->integer('placement')->default(0);        // 1 = winner, 2 = second, etc.
            $table->integer('total_players')->default(0);
            $table->integer('total_turns')->default(0);
            $table->json('full_event_log')->default('[]');   // complete event log (not truncated)
            $table->timestamps();

            $table->index('player_name');
            $table->index('is_winner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_results');
    }
};
