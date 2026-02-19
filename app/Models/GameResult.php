<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameResult extends Model
{
    protected $fillable = [
        'game_id',
        'player_id',
        'player_name',
        'seat',
        'coins',
        'influences',
        'revealed',
        'is_winner',
        'is_alive',
        'placement',
        'total_players',
        'total_turns',
        'full_event_log',
    ];

    protected $casts = [
        'influences' => 'array',
        'revealed' => 'array',
        'full_event_log' => 'array',
        'coins' => 'integer',
        'seat' => 'integer',
        'is_winner' => 'boolean',
        'is_alive' => 'boolean',
        'placement' => 'integer',
        'total_players' => 'integer',
        'total_turns' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
