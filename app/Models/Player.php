<?php

namespace App\Models;

use App\Enums\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Player extends Model
{
    protected $fillable = [
        'game_id',
        'name',
        'token',
        'seat',
        'coins',
        'influences',
        'revealed',
        'is_alive',
        'is_host',
        'is_ready',
        'last_activity_at',
    ];

    protected $casts = [
        'influences' => 'array',
        'revealed' => 'array',
        'coins' => 'integer',
        'seat' => 'integer',
        'is_alive' => 'boolean',
        'is_host' => 'boolean',
        'is_ready' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    // ──────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    public function influenceCount(): int
    {
        return count($this->influences ?? []);
    }

    public function hasCharacter(Character $character): bool
    {
        return in_array($character->value, $this->influences ?? []);
    }

    /**
     * Lose an influence by choice. The card moves from influences to revealed.
     */
    public function loseInfluence(string $characterValue): bool
    {
        $influences = $this->influences;
        $index = array_search($characterValue, $influences);
        if ($index === false) {
            return false;
        }

        array_splice($influences, $index, 1);
        $this->influences = $influences;

        $revealed = $this->revealed ?? [];
        $revealed[] = $characterValue;
        $this->revealed = $revealed;

        if (count($this->influences) === 0) {
            $this->is_alive = false;
        }

        $this->save();
        return true;
    }

    /**
     * Exile: return all coins to treasury.
     */
    public function exile(): void
    {
        $game = $this->game;
        $game->treasury += $this->coins;
        $game->save();

        $this->coins = 0;
        $this->is_alive = false;
        $this->save();
    }

    /**
     * Prove a character during challenge: reveal the card, return to deck, draw replacement.
     */
    public function proveCharacter(Character $character): bool
    {
        if (!$this->hasCharacter($character)) {
            return false;
        }

        $game = $this->game;

        // Remove the card from influences
        $influences = $this->influences;
        $index = array_search($character->value, $influences);
        array_splice($influences, $index, 1);

        // Return to deck and shuffle
        $game->returnCardsToDeck([$character->value]);

        // Draw 1 replacement
        $newCards = $game->drawCards(1);
        $influences = array_merge($influences, $newCards);
        $this->influences = array_values($influences);
        $this->save();

        return true;
    }

    /**
     * Public state (no hidden card identities).
     */
    public function publicState(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'seat' => $this->seat,
            'coins' => $this->coins,
            'influence_count' => $this->influenceCount(),
            'revealed' => $this->revealed ?? [],
            'is_alive' => $this->is_alive,
            'is_host' => $this->is_host,
            'is_ready' => $this->is_ready,
        ];
    }

    /**
     * Private state (includes hidden card identities, sent only to the owner).
     */
    public function privateState(): array
    {
        return array_merge($this->publicState(), [
            'influences' => $this->influences ?? [],
            'token' => $this->token,
        ]);
    }
}
