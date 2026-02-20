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
                ->get()
                ->map(function ($rank) {
                    return [
                        'player_name' => $rank->player_name,
                        'games_played' => (int) $rank->games_played,
                        'wins' => (int) $rank->wins,
                        'win_rate' => $rank->games_played > 0
                            ? round(($rank->wins / $rank->games_played) * 100, 1)
                            : 0,
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
            // Query from game_results grouped by game_id + started_at
            // (same game_id can have multiple rounds after rematch)
            $paginatedRounds = GameResult::select('game_id', 'started_at')
                ->groupBy('game_id', 'started_at')
                ->orderByDesc(DB::raw('MAX(created_at)'))
                ->paginate($perPage);

            $rounds = $paginatedRounds->getCollection();

            // Load all results for these rounds
            $allResults = collect();
            foreach ($rounds as $round) {
                $results = GameResult::where('game_id', $round->game_id)
                    ->where(function ($q) use ($round) {
                        if ($round->started_at) {
                            $q->where('started_at', $round->started_at);
                        } else {
                            $q->whereNull('started_at');
                        }
                    })
                    ->orderBy('placement')
                    ->get();
                $key = $round->game_id . '|' . ($round->started_at ?? 'null');
                $allResults->put($key, $results);
            }

            $gameIds = $rounds->pluck('game_id')->unique();
            $games = Game::whereIn('id', $gameIds)->get()->keyBy('id');

            $data = $rounds->map(function ($round) use ($allResults, $games) {
                $key = $round->game_id . '|' . ($round->started_at ?? 'null');
                $results = $allResults->get($key, collect());
                $game = $games->get($round->game_id);
                $winner = $results->firstWhere('is_winner', true);

                return [
                    'id' => $round->game_id,
                    'started_at' => $round->started_at,
                    'code' => $game?->code ?? '???',
                    'finished_at' => $winner?->created_at?->toISOString() ?? $game?->updated_at?->toISOString(),
                    'total_turns' => $winner?->total_turns ?? $game?->turn_number ?? 0,
                    'players' => $results->map(function ($r) {
                        return [
                            'player_name' => $r->player_name,
                            'placement' => $r->placement,
                            'is_winner' => $r->is_winner,
                            'is_alive' => $r->is_alive,
                            'coins' => $r->coins,
                            'influences' => $r->influences,
                            'revealed' => $r->revealed,
                        ];
                    })->values(),
                ];
            });

            return response()->json([
                'data' => $data,
                'total' => $paginatedRounds->total(),
                'per_page' => $paginatedRounds->perPage(),
                'current_page' => $paginatedRounds->currentPage(),
                'last_page' => $paginatedRounds->lastPage(),
            ]);
        } catch (\Throwable $e) {
            // Fallback: query games with phase game_over (before game_results table exists)
            $games = Game::where('phase', GamePhase::GAME_OVER)
                ->orderByDesc('updated_at')
                ->with('players')
                ->paginate($perPage);

            $data = $games->getCollection()->map(function ($game) {
                return [
                    'id' => $game->id,
                    'code' => $game->code,
                    'finished_at' => $game->updated_at->toISOString(),
                    'total_turns' => $game->turn_number,
                    'players' => $game->players
                        ->where('is_spectator', false)
                        ->map(function ($player) use ($game) {
                            return [
                                'player_name' => $player->name,
                                'placement' => $player->id === $game->winner_id ? 1 : ($player->is_alive ? 2 : 3),
                                'is_winner' => $player->id === $game->winner_id,
                                'is_alive' => $player->is_alive,
                                'coins' => $player->coins,
                                'influences' => $player->influences ?? [],
                                'revealed' => $player->revealed ?? [],
                            ];
                        })->values(),
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
    }

    /**
     * GET /api/games/{game}/results — Detailed results for a specific game round.
     */
    public function gameResults(Game $game, Request $request): JsonResponse
    {
        $startedAt = $request->query('started_at');

        $query = $game->results();
        if ($startedAt) {
            $query->where('started_at', $startedAt);
        }
        $results = $query->orderBy('placement')->get();

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
