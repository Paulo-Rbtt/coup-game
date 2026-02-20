<?php

namespace App\Console\Commands;

use App\Enums\GamePhase;
use App\Events\GameUpdated;
use App\Events\PrivateStateUpdated;
use App\Models\Game;
use App\Models\Player;
use App\Services\GameService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckTurnTimeouts extends Command
{
    protected $signature = 'games:check-timeouts';
    protected $description = 'Auto-act for players who exceed the 60s turn deadline';

    public function handle(GameService $gameService): int
    {
        $now = now();

        $expiredGames = Game::whereNotNull('turn_deadline')
            ->where('turn_deadline', '<=', $now)
            ->whereNotIn('phase', [GamePhase::LOBBY, GamePhase::GAME_OVER])
            ->get();

        foreach ($expiredGames as $game) {
            try {
                $this->handleTimeout($game, $gameService);
            } catch (\Throwable $e) {
                Log::error("Timeout handler failed for game {$game->id}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("Game {$game->code}: {$e->getMessage()}");
            }
        }

        if ($expiredGames->count() > 0) {
            $this->info("Processed {$expiredGames->count()} expired turn(s).");
        }

        return self::SUCCESS;
    }

    private function handleTimeout(Game $game, GameService $gameService): void
    {
        // Re-fetch with lock to check if still expired (avoid race with player action)
        $game = DB::transaction(function () use ($game) {
            $g = Game::lockForUpdate()->find($game->id);
            if (!$g || !$g->turn_deadline || $g->turn_deadline > now()) {
                return null; // Already handled or not expired
            }
            return $g;
        });

        if (!$game) return;

        $game->load('players');

        switch ($game->phase) {
            case GamePhase::ACTION_SELECTION:
                $this->autoIncome($game, $gameService);
                break;

            case GamePhase::AWAITING_CHALLENGE_ACTION:
            case GamePhase::AWAITING_BLOCK:
            case GamePhase::AWAITING_CHALLENGE_BLOCK:
                $this->autoPassAll($game, $gameService);
                break;

            case GamePhase::AWAITING_INFLUENCE_LOSS:
                $this->autoLoseInfluence($game, $gameService);
                break;

            case GamePhase::AWAITING_EXCHANGE_RETURN:
                $this->autoExchange($game, $gameService);
                break;

            default:
                // Clear stale deadline
                $game->turn_deadline = null;
                $game->timestamps = false;
                $game->saveQuietly();
                break;
        }
    }

    /**
     * Auto-take income for the current player.
     */
    private function autoIncome(Game $game, GameService $gameService): void
    {
        $player = $game->currentPlayer();
        if (!$player || !$player->is_alive) {
            // Skip dead player, advance turn
            $game->advanceTurn($player?->seat);
            $game->appendLog([
                'type' => 'turn_start',
                'player_id' => $game->currentPlayer()?->id,
                'player_name' => $game->currentPlayer()?->name,
                'turn' => $game->turn_number,
            ]);
            $this->broadcastAll($game);
            return;
        }

        $game->appendLog([
            'type' => 'auto_action',
            'action' => 'income',
            'actor_id' => $player->id,
            'actor_name' => $player->name,
            'reason' => 'timeout',
        ]);

        // Force the income action through the normal service
        $gameService->declareAction($game, $player, 'income');

        $this->info("Game {$game->code}: Auto-income for {$player->name} (timeout).");
    }

    /**
     * Auto-pass all eligible reactors.
     */
    private function autoPassAll(Game $game, GameService $gameService): void
    {
        $ts = $game->turn_state;
        if (!$ts) return;

        $passed = $ts['passed_players'] ?? [];

        // Get all alive non-spectator players
        $alive = $game->alivePlayers()->get();

        // Find who still needs to pass based on phase
        foreach ($alive as $player) {
            if (in_array($player->id, $passed)) continue;

            // Check if this player can react
            $canReact = false;
            if ($game->phase === GamePhase::AWAITING_CHALLENGE_ACTION) {
                $canReact = $player->id !== $ts['actor_id'];
            } elseif ($game->phase === GamePhase::AWAITING_BLOCK) {
                $action = $ts['action'];
                if ($action === 'foreign_aid') {
                    $canReact = $player->id !== $ts['actor_id'];
                } else {
                    $canReact = $player->id === $ts['target_id'];
                }
            } elseif ($game->phase === GamePhase::AWAITING_CHALLENGE_BLOCK) {
                $canReact = $player->id !== ($ts['blocker_id'] ?? null);
            }

            if ($canReact) {
                try {
                    $gameService->pass($game, $player);
                    // Re-fetch game since pass may have changed phase
                    $game = Game::find($game->id);
                    if (!$game || $game->turn_deadline === null || $game->phase === GamePhase::GAME_OVER) {
                        break;
                    }
                    $game->load('players');
                    $ts = $game->turn_state ?? [];
                    $passed = $ts['passed_players'] ?? [];
                } catch (\Throwable $e) {
                    // Pass might fail if phase changed mid-iteration, that's fine
                    Log::warning("Auto-pass failed for player {$player->id}: {$e->getMessage()}");
                    break;
                }
            }
        }

        $this->info("Game {$game->code}: Auto-passed all pending reactors (timeout).");
    }

    /**
     * Auto-lose a random influence.
     */
    private function autoLoseInfluence(Game $game, GameService $gameService): void
    {
        $ts = $game->turn_state;
        if (!$ts) return;

        $playerId = $ts['awaiting_influence_loss_from'] ?? null;
        if (!$playerId) return;

        $player = Player::find($playerId);
        if (!$player || !$player->is_alive) return;

        $influences = $player->influences ?? [];
        if (empty($influences)) return;

        // Pick a random influence to lose
        $card = $influences[array_rand($influences)];

        $game->appendLog([
            'type' => 'auto_action',
            'action' => 'lose_influence',
            'actor_id' => $player->id,
            'actor_name' => $player->name,
            'reason' => 'timeout',
        ]);

        $gameService->chooseInfluenceLoss($game, $player, $card);

        $this->info("Game {$game->code}: Auto-lose influence for {$player->name} (timeout).");
    }

    /**
     * Auto-keep first N cards during exchange.
     */
    private function autoExchange(Game $game, GameService $gameService): void
    {
        $ts = $game->turn_state;
        if (!$ts) return;

        $actorId = $ts['actor_id'] ?? null;
        if (!$actorId) return;

        $player = Player::find($actorId);
        if (!$player) return;

        $options = $ts['exchange_options'] ?? [];
        $keepCount = $ts['exchange_keep_count'] ?? $player->influenceCount();

        if (empty($options) || $keepCount <= 0) return;

        // Keep the first N options (which includes the player's current cards)
        $keepCards = array_slice($options, 0, $keepCount);

        $game->appendLog([
            'type' => 'auto_action',
            'action' => 'exchange',
            'actor_id' => $player->id,
            'actor_name' => $player->name,
            'reason' => 'timeout',
        ]);

        $gameService->chooseExchangeCards($game, $player, $keepCards);

        $this->info("Game {$game->code}: Auto-exchange for {$player->name} (timeout).");
    }

    private function broadcastAll(Game $game): void
    {
        $game = $game->fresh('players');
        broadcast(new GameUpdated($game));
        foreach ($game->players as $player) {
            broadcast(new PrivateStateUpdated($game, $player));
        }
    }
}
