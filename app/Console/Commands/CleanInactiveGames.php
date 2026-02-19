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

class CleanInactiveGames extends Command
{
    protected $signature = 'games:clean-inactive';
    protected $description = 'Remove inactive players and close stale games (5 min inactivity)';

    private const INACTIVITY_MINUTES = 5;

    public function handle(GameService $gameService): int
    {
        $cutoff = now()->subMinutes(self::INACTIVITY_MINUTES);
        $cleaned = 0;

        // 1. Close lobby rooms with no activity for 5 minutes
        $staleLobbies = Game::where('phase', GamePhase::LOBBY)
            ->where(function ($q) use ($cutoff) {
                $q->where('last_activity_at', '<', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('last_activity_at')
                         ->where('updated_at', '<', $cutoff);
                  });
            })
            ->get();

        foreach ($staleLobbies as $game) {
            $game->players()->delete();
            $game->delete();
            $cleaned++;
            $this->info("Lobby {$game->code} deleted (inactive).");
        }

        // 1b. Delete any non-game_over rooms that have zero players (orphaned)
        $orphaned = Game::whereNotIn('phase', [GamePhase::GAME_OVER])
            ->whereDoesntHave('players')
            ->where('updated_at', '<', now()->subMinutes(2))
            ->get();

        foreach ($orphaned as $game) {
            $game->delete();
            $cleaned++;
            $this->info("Orphaned room {$game->code} deleted (no players).");
        }

        // 2. In active games, kick players who haven't acted in 5 minutes
        $activePhases = [
            GamePhase::ACTION_SELECTION,
            GamePhase::AWAITING_CHALLENGE_ACTION,
            GamePhase::AWAITING_BLOCK,
            GamePhase::AWAITING_CHALLENGE_BLOCK,
            GamePhase::AWAITING_INFLUENCE_LOSS,
            GamePhase::AWAITING_EXCHANGE_RETURN,
            GamePhase::RESOLVING_CHALLENGE_ACTION,
            GamePhase::RESOLVING_CHALLENGE_BLOCK,
        ];

        $activeGames = Game::whereIn('phase', $activePhases)->get();

        foreach ($activeGames as $game) {
            // Check if the GAME itself has been inactive (no one did anything)
            $gameActivity = $game->last_activity_at ?? $game->updated_at;
            if ($gameActivity < $cutoff) {
                // Entire game is stale — force end it
                DB::transaction(function () use ($game) {
                    $game = Game::lockForUpdate()->find($game->id);
                    $game->load('players');

                    // Reveal all cards and set game over
                    foreach ($game->players as $player) {
                        if ($player->is_alive) {
                            $revealed = $player->revealed ?? [];
                            foreach ($player->influences as $card) {
                                $revealed[] = $card;
                            }
                            $player->influences = [];
                            $player->revealed = $revealed;
                            $player->is_alive = false;
                            $player->save();
                        }
                    }

                    $game->phase = GamePhase::GAME_OVER;
                    $game->winner_id = null;
                    $game->save();

                    $game->appendLog([
                        'type' => 'game_closed_inactivity',
                        'message' => 'Partida encerrada por inatividade.',
                    ]);

                    broadcast(new GameUpdated($game->fresh('players')));
                    foreach ($game->players as $player) {
                        broadcast(new PrivateStateUpdated($game, $player));
                    }
                });

                $cleaned++;
                $this->info("Game {$game->code} force-ended (inactive).");
            }
        }

        $this->info("Cleaned $cleaned games/lobbies.");
        return self::SUCCESS;
    }
}
