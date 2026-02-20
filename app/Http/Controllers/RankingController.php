<?php

namespace App\Http\Controllers;

use App\Enums\GamePhase;
use App\Models\Game;
use App\Models\GameResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    /**
     * GET /api/rankings — Global ranking table by player name.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $rankings = GameResult::select(
                    'player_name',
                    DB::raw('COUNT(*) as games_played'),
                    DB::raw('SUM(CASE WHEN is_winner = true THEN 1 ELSE 0 END) as wins'),
                    DB::raw('ROUND(AVG(placement)::numeric, 1) as avg_placement'),
                )
                ->groupBy('player_name')
                ->orderByDesc('wins')
                ->orderBy('avg_placement')
                ->orderByDesc('games_played')
                ->limit(100)
                ->get();

            // For each player, get their winning cards (influences from winning games)
            $rankings = $rankings->map(function ($rank) {
                $winningCards = GameResult::where('player_name', $rank->player_name)
                    ->where('is_winner', true)
                    ->pluck('influences')
                    ->flatten()
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->toArray();

                return [
                    'player_name' => $rank->player_name,
                    'games_played' => (int) $rank->games_played,
                    'wins' => (int) $rank->wins,
                    'win_rate' => $rank->games_played > 0
                        ? round(($rank->wins / $rank->games_played) * 100, 1)
                        : 0,
                    'avg_placement' => (float) $rank->avg_placement,
                    'winning_cards' => $winningCards,
                ];
            });

            return response()->json($rankings);
        } catch (\Throwable $e) {
            // If game_results table doesn't exist yet, return empty
            return response()->json([]);
        }
    }

    /**
     * GET /api/history — List completed games.
     */
    public function history(Request $request): JsonResponse
    {
        $perPage = min($request->query('per_page', 20), 50);

        try {
            $games = Game::where('phase', GamePhase::GAME_OVER)
                ->orderByDesc('updated_at')
                ->with(['results' => function ($q) {
                    $q->orderBy('placement');
                }, 'players'])
                ->paginate($perPage);
        } catch (\Throwable $e) {
            // If game_results table doesn't exist, fall back to games + players
            $games = Game::where('phase', GamePhase::GAME_OVER)
                ->orderByDesc('updated_at')
                ->with('players')
                ->paginate($perPage);
        }

        $data = $games->getCollection()->map(function ($game) {
            // Prefer results table, fall back to players table
            $playerData = $game->relationLoaded('results') && $game->results->isNotEmpty()
                ? $game->results->map(function ($result) {
                    return [
                        'player_name' => $result->player_name,
                        'placement' => $result->placement,
                        'is_winner' => $result->is_winner,
                        'is_alive' => $result->is_alive,
                        'coins' => $result->coins,
                        'influences' => $result->influences,
                        'revealed' => $result->revealed,
                    ];
                })
                : $game->players->map(function ($player) use ($game) {
                    return [
                        'player_name' => $player->name,
                        'placement' => $player->id === $game->winner_id ? 1 : ($player->is_alive ? 2 : 3),
                        'is_winner' => $player->id === $game->winner_id,
                        'is_alive' => $player->is_alive,
                        'coins' => $player->coins,
                        'influences' => $player->influences ?? [],
                        'revealed' => $player->revealed ?? [],
                    ];
                });

            return [
                'id' => $game->id,
                'code' => $game->code,
                'finished_at' => $game->updated_at->toISOString(),
                'total_turns' => $game->turn_number,
                'players' => $playerData,
            ];
        });

        return response()->json([
            'data' => $data,
            'total' => $games->total(),
            'per_page' => $games->perPage(),
            'current_page' => $games->currentPage(),
            'last_page' => $games->lastPage(),
        ]);
    }

    /**
     * GET /api/games/{game}/results — Detailed results for a specific game.
     */
    public function gameResults(Game $game): JsonResponse
    {
        $results = $game->results()->get();

        if ($results->isEmpty()) {
            return response()->json(['error' => 'Resultados não encontrados.'], 404);
        }

        // Get full event log from the winner's result
        $winnerResult = $results->firstWhere('is_winner', true);
        $fullLog = $winnerResult?->full_event_log ?? $game->event_log ?? [];

        return response()->json([
            'game' => [
                'id' => $game->id,
                'code' => $game->code,
                'total_turns' => $game->turn_number,
                'finished_at' => $game->updated_at->toISOString(),
            ],
            'players' => $results->map(function ($result) {
                return [
                    'player_name' => $result->player_name,
                    'placement' => $result->placement,
                    'is_winner' => $result->is_winner,
                    'is_alive' => $result->is_alive,
                    'coins' => $result->coins,
                    'influences' => $result->influences,
                    'revealed' => $result->revealed,
                    'seat' => $result->seat,
                ];
            }),
            'event_log' => $fullLog,
        ]);
    }
}
