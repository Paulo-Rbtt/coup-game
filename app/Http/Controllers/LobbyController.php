<?php

namespace App\Http\Controllers;

use App\Enums\GamePhase;
use App\Models\Game;
use App\Models\Player;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LobbyController extends Controller
{
    public function __construct(private GameService $gameService)
    {
    }

    /**
     * POST /api/games — Create a new game room.
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'player_name' => 'required|string|max:20',
        ]);

        $result = $this->gameService->createGame($request->player_name);

        return response()->json([
            'game' => $result['game']->publicState(),
            'player' => $result['player']->privateState(),
        ], 201);
    }

    /**
     * POST /api/games/join — Join an existing game room.
     */
    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
            'player_name' => 'required|string|max:20',
        ]);

        try {
            $result = $this->gameService->joinGame($request->code, $request->player_name);
            return response()->json([
                'game' => $result['game']->publicState(),
                'player' => $result['player']->privateState(),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/games/reconnect — Reconnect to a game with token.
     */
    public function reconnect(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $result = $this->gameService->reconnect($request->token);
            return response()->json([
                'game' => $result['game']->publicState(),
                'player' => $result['player']->privateState(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token inválido.'], 404);
        }
    }

    /**
     * GET /api/rooms — List open/active game rooms.
     */
    public function listRooms(): JsonResponse
    {
        // Only show rooms updated in the last 30 minutes
        $freshCutoff = now()->subMinutes(30);

        $games = Game::whereIn('phase', [
                GamePhase::LOBBY,
                GamePhase::ACTION_SELECTION,
                GamePhase::AWAITING_CHALLENGE_ACTION,
                GamePhase::AWAITING_BLOCK,
                GamePhase::AWAITING_CHALLENGE_BLOCK,
                GamePhase::AWAITING_INFLUENCE_LOSS,
                GamePhase::AWAITING_EXCHANGE_RETURN,
                GamePhase::RESOLVING_CHALLENGE_ACTION,
                GamePhase::RESOLVING_CHALLENGE_BLOCK,
                GamePhase::RESOLVING_ACTION,
            ])
            ->where('updated_at', '>=', $freshCutoff)
            ->has('players') // Only rooms that still have players
            ->with('players')
            ->orderByDesc('updated_at')
            ->take(20)
            ->get()
            ->map(function ($game) {
                $isLobby = $game->phase === GamePhase::LOBBY;
                $elapsed = $game->updated_at->diffForHumans(null, true);
                return [
                    'id' => $game->id,
                    'code' => $game->code,
                    'phase' => $game->phase->value,
                    'is_lobby' => $isLobby,
                    'player_count' => $game->players->where('is_spectator', false)->count(),
                    'max_players' => $game->max_players,
                    'players' => $game->players->where('is_spectator', false)->pluck('name')->toArray(),
                    'spectator_count' => $game->players->where('is_spectator', true)->count(),
                    'started' => !$isLobby,
                    'elapsed' => $elapsed,
                    'turn_number' => $game->turn_number,
                    'created_at' => $game->created_at->toISOString(),
                ];
            });

        return response()->json($games);
    }

    /**
     * POST /api/games/{game}/toggle-ready — Toggle ready state.
     */
    public function toggleReady(Request $request, Game $game): JsonResponse
    {
        $player = $this->authenticatePlayer($request, $game);

        try {
            $isReady = $this->gameService->toggleReady($game, $player);
            return response()->json(['is_ready' => $isReady]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/games/{game}/kick — Host kicks a player from the lobby.
     */
    public function kick(Request $request, Game $game): JsonResponse
    {
        $request->validate([
            'player_id' => 'required|integer',
        ]);

        $host = $this->authenticatePlayer($request, $game);

        try {
            $this->gameService->kickPlayer($game, $host, $request->player_id);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/games/{game}/leave-lobby — Leave the lobby before game starts.
     */
    public function leaveLobby(Request $request, Game $game): JsonResponse
    {
        $player = $this->authenticatePlayer($request, $game);

        try {
            $this->gameService->leaveLobby($game, $player);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/games/{game}/start — Host starts the game.
     */
    public function start(Request $request, Game $game): JsonResponse
    {
        $player = $this->authenticatePlayer($request, $game);

        try {
            $this->gameService->startGame($game, $player);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/games/{game}/state — Get current public game state.
     */
    public function state(Request $request, Game $game): JsonResponse
    {
        $game->load('players');
        $data = ['game' => $game->publicState()];

        // If token provided, include private state
        $token = $request->header('X-Player-Token');
        if ($token) {
            $player = Player::where('token', $token)->where('game_id', $game->id)->first();
            if ($player) {
                $data['player'] = $player->privateState();
            }
        }

        return response()->json($data);
    }

    /**
     * POST /api/games/{game}/rematch — Reset game to lobby for rematch.
     */
    public function rematch(Request $request, Game $game): JsonResponse
    {
        $this->authenticatePlayer($request, $game);

        try {
            $this->gameService->rematchGame($game);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function authenticatePlayer(Request $request, Game $game): Player
    {
        $token = $request->header('X-Player-Token');
        if (!$token) {
            abort(401, 'Token necessário.');
        }

        $player = Player::where('token', $token)->where('game_id', $game->id)->first();
        if (!$player) {
            abort(401, 'Jogador não encontrado nesta partida.');
        }

        return $player;
    }
}
