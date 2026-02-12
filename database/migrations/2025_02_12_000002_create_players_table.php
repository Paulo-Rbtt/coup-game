<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token', 64)->unique();         // session token for reconnection
            $table->integer('seat')->default(0);            // order in turn rotation
            $table->integer('coins')->default(2);
            $table->json('influences')->default('[]');      // hidden cards (array of character strings)
            $table->json('revealed')->default('[]');        // revealed (dead) cards
            $table->boolean('is_alive')->default(true);
            $table->boolean('is_host')->default(false);
            $table->timestamps();

            $table->unique(['game_id', 'seat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
