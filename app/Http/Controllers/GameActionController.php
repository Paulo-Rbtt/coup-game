<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Player;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameActionController extends Controller
{
    public function __construct(private GameService $gameService)
    {
    }

    /**
     * POST /api/games/{game}/action — Declare an action.
     */
    public function declareAction(Request $request, Game $game): JsonResponse
    {
        $player = $this->auth($request, $game);

        $request->validate([
            'action' => 'required|string',
            'target_id' => 'nullable|integer',
        ]);

        try {
            $this->gameService->declareAction($game, $player, $request->action, $request->target_id);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/games/{game}/pass — Pass (no challenge / no block).
     */
    public function pass(Request $request, Game $game): JsonResponse
    {
        $player = $this->auth($request, $game);

        try {
            $this->gameService->pass($game, $player);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/games/{game}/challenge — Challenge the current action.
     */
    public function challengeAction(Request $request, Game $game): JsonResponse
    {
        $player = $this->auth($request, $game);

        try {
            $this->gameService->challengeAction($game, $player);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/games/{game}/block — Declare a block.
     */
    public function declareBlock(Request $request, Game $game): JsonResponse
    {
        $player = $this->auth($request, $game);

        $request->validate([
            'character' => 'required|string',
        ]);

        try {
            $this->gameService->declareBlock($game, $player, $request->character);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/games/{game}/challenge-block — Challenge the current block.
     */
    public function challengeBlock(Request $request, Game $game): JsonResponse
    {
        $player = $this->auth($request, $game);

        try {
            $this->gameService->challengeBlock($game, $player);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/games/{game}/lose-influence — Choose which influence to lose.
     */
    public function loseInfluence(Request $request, Game $game): JsonResponse
    {
        $player = $this->auth($request, $game);

        $request->validate([
            'character' => 'required|string',
        ]);

        try {
            $this->gameService->chooseInfluenceLoss($game, $player, $request->character);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/games/{game}/exchange — Choose cards to keep from exchange.
     */
    public function exchange(Request $request, Game $game): JsonResponse
    {
        $player = $this->auth($request, $game);

        $request->validate([
            'keep' => 'required|array',
        ]);

        try {
            $this->gameService->chooseExchangeCards($game, $player, $request->keep);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/games/{game}/leave — Abandon the game (voluntary exile).
     */
    public function leave(Request $request, Game $game): JsonResponse
    {
        $player = $this->auth($request, $game);

        try {
            $this->gameService->abandonGame($game, $player);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function auth(Request $request, Game $game): Player
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
