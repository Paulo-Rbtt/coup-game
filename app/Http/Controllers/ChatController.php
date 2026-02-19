<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ChatController extends Controller
{
    private const COOLDOWN_SECONDS = 3;
    private const MAX_MESSAGE_LENGTH = 200;

    /**
     * POST /api/games/{game}/chat — Send a chat message.
     */
    public function send(Request $request, Game $game): JsonResponse
    {
        $player = $this->auth($request, $game);

        $request->validate([
            'message' => 'required|string|max:' . self::MAX_MESSAGE_LENGTH,
        ]);

        // ── Rate-limit: 1 message every 3 seconds per player ──
        $cacheKey = "chat_cooldown:{$player->id}";

        if (Cache::has($cacheKey)) {
            $remaining = ceil(Cache::get($cacheKey) - microtime(true));
            return response()->json([
                'error' => "Aguarde {$remaining}s para enviar outra mensagem.",
            ], 429);
        }

        // Set cooldown
        Cache::put($cacheKey, microtime(true) + self::COOLDOWN_SECONDS, self::COOLDOWN_SECONDS);

        // Sanitize
        $message = strip_tags(trim($request->message));
        if (empty($message)) {
            return response()->json(['error' => 'Mensagem vazia.'], 422);
        }

        // Broadcast to all players in the game
        event(new ChatMessageSent(
            gameId:     $game->id,
            playerId:   $player->id,
            playerName: $player->name,
            message:    $message,
            timestamp:  microtime(true),
        ));

        return response()->json(['success' => true]);
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
