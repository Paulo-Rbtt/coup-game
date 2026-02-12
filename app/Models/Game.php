<?php

namespace App\Models;

use App\Enums\Character;
use App\Enums\GamePhase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'code',
        'phase',
        'deck',
        'treasury',
        'current_player_index',
        'turn_state',
        'event_log',
        'turn_number',
        'winner_id',
        'min_players',
        'max_players',
    ];

    protected $casts = [
        'phase' => GamePhase::class,
        'deck' => 'array',
        'turn_state' => 'array',
        'event_log' => 'array',
        'treasury' => 'integer',
        'current_player_index' => 'integer',
        'turn_number' => 'integer',
        'winner_id' => 'integer',
    ];

    // ──────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────

    public function players(): HasMany
    {
        return $this->hasMany(Player::class)->orderBy('seat');
    }

    public function alivePlayers(): HasMany
    {
        return $this->hasMany(Player::class)->where('is_alive', true)->orderBy('seat');
    }

    public function winner()
    {
        return $this->belongsTo(Player::class, 'winner_id');
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Build initial deck: 3 copies of each character for 2–6 players.
     */
    public static function buildDeck(): array
    {
        $deck = [];
        foreach (Character::cases() as $char) {
            for ($i = 0; $i < 3; $i++) {
                $deck[] = $char->value;
            }
        }
        shuffle($deck);
        return $deck;
    }

    public function currentPlayer(): ?Player
    {
        $alive = $this->alivePlayers()->get();
        if ($alive->isEmpty()) {
            return null;
        }
        return $alive->values()->get($this->current_player_index % $alive->count());
    }

    public function advanceTurn(): void
    {
        $alive = $this->alivePlayers()->get();
        if ($alive->count() <= 1) {
            return;
        }
        $this->current_player_index = ($this->current_player_index + 1) % $alive->count();
        $this->turn_number++;
        $this->phase = GamePhase::ACTION_SELECTION;
        $this->turn_state = null;
        $this->save();
    }

    public function appendLog(array $entry): void
    {
        $log = $this->event_log ?? [];
        $entry['timestamp'] = now()->toISOString();
        $entry['turn'] = $this->turn_number;
        $log[] = $entry;
        $this->event_log = $log;
        $this->save();
    }

    /**
     * Draw N cards from the top of the deck.
     */
    public function drawCards(int $n): array
    {
        $deck = $this->deck;
        $drawn = array_splice($deck, 0, $n);
        $this->deck = $deck;
        $this->save();
        return $drawn;
    }

    /**
     * Return cards to deck and shuffle.
     */
    public function returnCardsToDeck(array $cards): void
    {
        $deck = $this->deck;
        foreach ($cards as $card) {
            $deck[] = $card;
        }
        shuffle($deck);
        $this->deck = $deck;
        $this->save();
    }

    /**
     * Public state visible to everyone.
     */
    public function publicState(): array
    {
        $currentPlayer = $this->currentPlayer();
        return [
            'id' => $this->id,
            'code' => $this->code,
            'phase' => $this->phase->value,
            'phase_label' => $this->phase->label(),
            'treasury' => $this->treasury,
            'turn_number' => $this->turn_number,
            'current_player_id' => $currentPlayer?->id,
            'current_player_name' => $currentPlayer?->name,
            'turn_state' => $this->sanitizedTurnState(),
            'event_log' => $this->event_log ?? [],
            'winner_id' => $this->winner_id,
            'deck_count' => count($this->deck ?? []),
            'players' => $this->players->map(fn(Player $p) => $p->publicState())->toArray(),
        ];
    }

    /**
     * Remove secret info from turn_state for broadcasting.
     */
    private function sanitizedTurnState(): ?array
    {
        $ts = $this->turn_state;
        if (!$ts) {
            return null;
        }
        // Remove exchange_options (secret cards) from public broadcast
        unset($ts['exchange_options']);
        return $ts;
    }
}
