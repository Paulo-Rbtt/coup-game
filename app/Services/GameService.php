<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Enums\Character;
use App\Enums\GamePhase;
use App\Events\GameUpdated;
use App\Events\PrivateStateUpdated;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

class GameService
{
    // ══════════════════════════════════════════════════════════════
    // ACTIVITY TRACKING
    // ══════════════════════════════════════════════════════════════

    private function touchActivity(Game $game, ?Player $player = null): void
    {
        $game->last_activity_at = now();
        $game->save();

        if ($player) {
            $player->last_activity_at = now();
            $player->save();
        }
    }

    // ══════════════════════════════════════════════════════════════
    // LOBBY
    // ══════════════════════════════════════════════════════════════

    public function createGame(string $playerName): array
    {
        return DB::transaction(function () use ($playerName) {
            $game = Game::create([
                'code' => Game::generateCode(),
                'phase' => GamePhase::LOBBY,
                'deck' => [],
                'treasury' => 0,
                'last_activity_at' => now(),
            ]);

            $player = $this->addPlayer($game, $playerName, true);

            return ['game' => $game->fresh('players'), 'player' => $player];
        });
    }

    public function joinGame(string $code, string $playerName): array
    {
        return DB::transaction(function () use ($code, $playerName) {
            $game = Game::where('code', strtoupper($code))->lockForUpdate()->firstOrFail();

            // If game is in lobby, join as regular player
            if ($game->phase === GamePhase::LOBBY) {
                if ($game->gamePlayers()->count() >= $game->max_players) {
                    throw new \RuntimeException('Sala cheia.');
                }

                $player = $this->addPlayer($game, $playerName, false);

                $this->touchActivity($game, $player);
                $this->broadcastPublic($game->fresh('players'));

                return ['game' => $game->fresh('players'), 'player' => $player];
            }

            // If game is in progress or game over, join as spectator
            if ($game->phase !== GamePhase::GAME_OVER) {
                $player = $this->addSpectator($game, $playerName);

                $this->touchActivity($game, $player);
                $this->broadcastPublic($game->fresh('players'));

                return ['game' => $game->fresh('players'), 'player' => $player];
            }

            throw new \RuntimeException('A partida já terminou.');
        });
    }

    public function reconnect(string $token): array
    {
        $player = Player::where('token', $token)->firstOrFail();
        $game = $player->game;
        $game->load('players');

        return ['game' => $game, 'player' => $player];
    }

    private function addPlayer(Game $game, string $name, bool $isHost): Player
    {
        $seat = $game->gamePlayers()->count();
        return Player::create([
            'game_id' => $game->id,
            'name' => $name,
            'token' => bin2hex(random_bytes(32)),
            'seat' => $seat,
            'coins' => 2,
            'influences' => [],
            'revealed' => [],
            'is_host' => $isHost,
            'is_spectator' => false,
        ]);
    }

    /**
     * Add a spectator to an in-progress game.
     * Spectators use negative seats to avoid unique constraint conflicts.
     */
    private function addSpectator(Game $game, string $name): Player
    {
        // Use negative seat numbers for spectators to avoid unique constraint with game players
        $minSeat = $game->spectators()->min('seat') ?? 0;
        $spectatorSeat = min($minSeat, 0) - 1;

        return Player::create([
            'game_id' => $game->id,
            'name' => $name,
            'token' => bin2hex(random_bytes(32)),
            'seat' => $spectatorSeat,
            'coins' => 0,
            'influences' => [],
            'revealed' => [],
            'is_host' => false,
            'is_spectator' => true,
            'is_alive' => false,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // LEAVE LOBBY (before game starts)
    // ══════════════════════════════════════════════════════════════

    public function kickPlayer(Game $game, Player $host, int $targetPlayerId): void
    {
        DB::transaction(function () use ($game, $host, $targetPlayerId) {
            $game = Game::lockForUpdate()->find($game->id);

            if ($game->phase !== GamePhase::LOBBY) {
                throw new \RuntimeException('Só pode expulsar jogadores no lobby.');
            }

            if (!$host->is_host) {
                throw new \RuntimeException('Apenas o anfitrião pode expulsar jogadores.');
            }

            $target = Player::where('id', $targetPlayerId)->where('game_id', $game->id)->first();
            if (!$target) {
                throw new \RuntimeException('Jogador não encontrado.');
            }

            if ($target->id === $host->id) {
                throw new \RuntimeException('Você não pode se expulsar.');
            }

            // Notify the kicked player via their private channel before deleting
            broadcast(new PrivateStateUpdated($game, $target, ['kicked' => true]));

            $target->delete();

            // Re-seat remaining game players
            $remaining = $game->gamePlayers()->orderBy('seat')->get();
            foreach ($remaining->values() as $idx => $p) {
                $p->seat = $idx;
                $p->save();
            }

            $this->broadcastPublic($game->fresh('players'));
        });
    }

    public function leaveLobby(Game $game, Player $player): void
    {
        DB::transaction(function () use ($game, $player) {
            $game = Game::lockForUpdate()->find($game->id);

            if ($game->phase !== GamePhase::LOBBY) {
                // If spectator in an active game, just remove them
                if ($player->is_spectator) {
                    $player->delete();
                    $this->broadcastPublic($game->fresh('players'));
                    return;
                }
                throw new \RuntimeException('Só pode sair do lobby antes do jogo iniciar.');
            }

            $wasHost = $player->is_host;
            $player->delete();

            // Re-seat remaining game players (not spectators)
            $remaining = $game->gamePlayers()->orderBy('seat')->get();
            foreach ($remaining->values() as $idx => $p) {
                $p->seat = $idx;
                $p->save();
            }

            // If the host left, promote the next player
            if ($wasHost && $remaining->count() > 0) {
                $newHost = $remaining->first();
                $newHost->is_host = true;
                $newHost->save();
            }

            // If room is empty (no game players), delete
            if ($remaining->count() === 0) {
                $game->spectators()->delete();
                $game->delete();
                return;
            }

            $this->broadcastPublic($game->fresh('players'));
        });
    }

    // ══════════════════════════════════════════════════════════════
    // TOGGLE READY
    // ══════════════════════════════════════════════════════════════

    public function toggleReady(Game $game, Player $player): bool
    {
        return DB::transaction(function () use ($game, $player) {
            $game = Game::lockForUpdate()->find($game->id);

            if ($game->phase !== GamePhase::LOBBY) {
                throw new \RuntimeException('A partida já começou.');
            }

            $player->is_ready = !$player->is_ready;
            $player->save();

            $this->broadcastPublic($game->fresh('players'));

            return $player->is_ready;
        });
    }

    // ══════════════════════════════════════════════════════════════
    // START GAME
    // ══════════════════════════════════════════════════════════════

    public function startGame(Game $game, Player $host): void
    {
        DB::transaction(function () use ($game, $host) {
            $game = Game::lockForUpdate()->find($game->id);

            if (!$host->is_host) {
                throw new \RuntimeException('Apenas o anfitrião pode iniciar.');
            }
            if ($game->gamePlayers()->count() < $game->min_players) {
                throw new \RuntimeException("Mínimo {$game->min_players} jogadores.");
            }
            if ($game->phase !== GamePhase::LOBBY) {
                throw new \RuntimeException('Partida já iniciada.');
            }

            // All non-spectator players must be ready
            $notReady = $game->gamePlayers()->where('is_ready', false)->count();
            if ($notReady > 0) {
                throw new \RuntimeException('Todos os jogadores precisam estar prontos.');
            }

            // Build deck: 15 cards (3 × 5 characters)
            $deck = Game::buildDeck();

            // Randomize seat order (who goes first) — only game players, not spectators
            // First set all seats to negative temp values to avoid unique constraint violations
            $players = $game->gamePlayers()->orderBy('seat')->get();
            foreach ($players as $i => $player) {
                $player->seat = -($i + 100);
                $player->save();
            }
            // Now assign the shuffled seats
            $seats = range(0, $players->count() - 1);
            shuffle($seats);
            foreach ($players as $i => $player) {
                $player->seat = $seats[$i];
                $player->save();
            }
            // Re-fetch in new seat order
            $players = $game->gamePlayers()->orderBy('seat')->get();

            // Deal 2 cards to each player
            foreach ($players as $player) {
                $hand = array_splice($deck, 0, 2);
                $player->influences = $hand;
                $player->coins = 2;
                $player->save();
            }

            // Remaining cards form the Court Deck
            // Treasury = total coins minus dealt coins
            $totalCoins = 50; // standard Coup has 50 coins
            $dealtCoins = $players->count() * 2;

            $game->deck = $deck;
            $game->treasury = $totalCoins - $dealtCoins;
            $game->phase = GamePhase::ACTION_SELECTION;
            $game->current_player_index = 0;
            $game->turn_number = 1;
            $game->turn_state = null;
            $game->event_log = [];
            $game->started_at = now();
            $game->last_activity_at = now();
            $game->save();

            $game->appendLog([
                'type' => 'game_started',
                'player_count' => $players->count(),
            ]);

            $this->broadcastAll($game->fresh('players'));
        });
    }

    // ══════════════════════════════════════════════════════════════
    // STEP 1-2: DECLARE ACTION
    // ══════════════════════════════════════════════════════════════

    public function declareAction(Game $game, Player $actor, string $actionStr, ?int $targetId = null): void
    {
        DB::transaction(function () use ($game, $actor, $actionStr, $targetId) {
            $game = Game::lockForUpdate()->find($game->id);
            $game->load('players');
            $this->touchActivity($game, $actor);

            $action = ActionType::from($actionStr);

            // Validate it's actor's turn
            $current = $game->currentPlayer();
            if (!$current || $current->id !== $actor->id) {
                throw new \RuntimeException('Não é seu turno.');
            }
            if ($game->phase !== GamePhase::ACTION_SELECTION) {
                throw new \RuntimeException('Fase inválida para declarar ação.');
            }

            // Coercion rule: 10+ coins → must Coup
            if ($actor->coins >= 10 && $action !== ActionType::COUP) {
                throw new \RuntimeException('Com 10+ moedas você deve dar Golpe de Estado.');
            }

            // Validate cost
            if ($actor->coins < $action->cost()) {
                throw new \RuntimeException('Moedas insuficientes.');
            }

            // Validate target
            $target = null;
            if ($action->requiresTarget()) {
                if (!$targetId) {
                    throw new \RuntimeException('Ação requer um alvo.');
                }
                $target = Player::find($targetId);
                if (!$target || $target->game_id !== $game->id || !$target->is_alive) {
                    throw new \RuntimeException('Alvo inválido.');
                }
                if ($target->id === $actor->id) {
                    throw new \RuntimeException('Não pode mirar em si mesmo.');
                }
                // Steal from player with 0 coins
                if ($action === ActionType::STEAL && $target->coins === 0) {
                    throw new \RuntimeException('Alvo não tem moedas para extorquir.');
                }
            }

            // Pay cost upfront for Coup (irrevocable). For Assassinate, cost is paid but refunded on failed challenge.
            if ($action === ActionType::COUP) {
                $actor->coins -= $action->cost();
                $actor->save();
                $game->treasury += $action->cost();
                $game->save();
            }

            // Set turn state
            $game->turn_state = [
                'action' => $action->value,
                'actor_id' => $actor->id,
                'target_id' => $target?->id,
                'cost_paid' => $action === ActionType::COUP, // Coup cost paid immediately
                'challenger_id' => null,
                'blocker_id' => null,
                'block_character' => null,
                'block_challenger_id' => null,
                'influence_loss_queue' => [], // [{player_id, reason}]
                'exchange_options' => null,
                'resolved' => false,
            ];
            $game->save();

            // Log the action declaration for ALL actions
            $game->appendLog([
                'type' => 'action_declared',
                'action' => $action->value,
                'action_label' => $action->label(),
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'target_id' => $target?->id,
                'target_name' => $target?->name,
                'character' => $action->requiredCharacter()?->value,
            ]);

            // Determine next phase
            if ($action === ActionType::INCOME) {
                // Income: no challenge, no block → resolve immediately
                $this->resolveAction($game);
                return;
            }

            if ($action === ActionType::COUP) {
                // Coup: no challenge, no block → target loses influence
                $this->broadcastAll($game->fresh('players'));
                $this->queueInfluenceLoss($game, $target->id, 'coup');
                return;
            }

            if ($action->isChallengeable()) {
                $game->phase = GamePhase::AWAITING_CHALLENGE_ACTION;
            } elseif ($action->canBeBlocked()) {
                // Foreign Aid: not challengeable but blockable
                $game->phase = GamePhase::AWAITING_BLOCK;
            } else {
                $this->resolveAction($game);
                return;
            }

            $game->save();

            $this->broadcastAll($game->fresh('players'));
        });
    }

    // ══════════════════════════════════════════════════════════════
    // STEP 3: PASS (no challenge / no block)
    // ══════════════════════════════════════════════════════════════

    public function pass(Game $game, Player $passer): void
    {
        DB::transaction(function () use ($game, $passer) {
            $game = Game::lockForUpdate()->find($game->id);
            $game->load('players');
            $this->touchActivity($game, $passer);
            $ts = $game->turn_state;

            if (!in_array($game->phase, [
                GamePhase::AWAITING_CHALLENGE_ACTION,
                GamePhase::AWAITING_BLOCK,
                GamePhase::AWAITING_CHALLENGE_BLOCK,
            ])) {
                throw new \RuntimeException('Fase inválida para passar.');
            }

            // Can't pass your own action/block
            if ($game->phase === GamePhase::AWAITING_CHALLENGE_ACTION && $passer->id === $ts['actor_id']) {
                throw new \RuntimeException('Não pode passar a própria ação.');
            }

            // Record that this player passed
            $passed = $ts['passed_players'] ?? [];
            if (in_array($passer->id, $passed)) {
                return; // already passed
            }
            $passed[] = $passer->id;
            $ts['passed_players'] = $passed;
            $game->turn_state = $ts;
            $game->save();

            // Check if all eligible players have passed
            $eligible = $this->getEligibleReactors($game, $ts);
            $allPassed = empty(array_diff($eligible, $passed));

            if ($allPassed) {
                $ts['passed_players'] = []; // reset for next window
                $game->turn_state = $ts;
                $game->save();
                $this->advanceAfterWindow($game);
            } else {
                $this->broadcastAll($game->fresh('players'));
            }
        });
    }

    /**
     * Get player IDs eligible to react in current window.
     */
    private function getEligibleReactors(Game $game, array $ts): array
    {
        $action = ActionType::from($ts['action']);
        $alive = $game->alivePlayers()->pluck('id')->toArray();

        if ($game->phase === GamePhase::AWAITING_CHALLENGE_ACTION) {
            // Everyone except the actor can challenge
            return array_values(array_diff($alive, [$ts['actor_id']]));
        }

        if ($game->phase === GamePhase::AWAITING_BLOCK) {
            // Who can block? Depends on action
            if ($action === ActionType::FOREIGN_AID) {
                // Anyone except actor
                return array_values(array_diff($alive, [$ts['actor_id']]));
            }
            // Assassinate → only target (Contessa)
            // Steal → only target (Ambassador/Captain)
            return $ts['target_id'] ? [$ts['target_id']] : [];
        }

        if ($game->phase === GamePhase::AWAITING_CHALLENGE_BLOCK) {
            // Everyone except the blocker can challenge the block
            return array_values(array_diff($alive, [$ts['blocker_id']]));
        }

        return [];
    }

    /**
     * Advance after all players passed in a window.
     */
    private function advanceAfterWindow(Game $game): void
    {
        $ts = $game->turn_state;
        $action = ActionType::from($ts['action']);

        if ($game->phase === GamePhase::AWAITING_CHALLENGE_ACTION) {
            // No one challenged the action
            if ($action->canBeBlocked()) {
                $game->phase = GamePhase::AWAITING_BLOCK;
                $game->save();
                $this->broadcastAll($game->fresh('players'));
            } else {
                $this->resolveAction($game);
            }
        } elseif ($game->phase === GamePhase::AWAITING_BLOCK) {
            // No one blocked → resolve action
            $this->resolveAction($game);
        } elseif ($game->phase === GamePhase::AWAITING_CHALLENGE_BLOCK) {
            // No one challenged the block → block stands → action fails
            $this->blockSucceeds($game);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // STEP 3: CHALLENGE ACTION
    // ══════════════════════════════════════════════════════════════

    public function challengeAction(Game $game, Player $challenger): void
    {
        DB::transaction(function () use ($game, $challenger) {
            $game = Game::lockForUpdate()->find($game->id);
            $game->load('players');
            $this->touchActivity($game, $challenger);
            $ts = $game->turn_state;

            if ($game->phase !== GamePhase::AWAITING_CHALLENGE_ACTION) {
                throw new \RuntimeException('Fase inválida para contestar.');
            }
            if ($challenger->id === $ts['actor_id']) {
                throw new \RuntimeException('Não pode contestar a própria ação.');
            }

            $ts['challenger_id'] = $challenger->id;
            $ts['passed_players'] = [];
            $game->turn_state = $ts;
            $game->phase = GamePhase::RESOLVING_CHALLENGE_ACTION;
            $game->save();

            $action = ActionType::from($ts['action']);
            $actor = Player::find($ts['actor_id']);
            $requiredChar = $action->requiredCharacter();

            $game->appendLog([
                'type' => 'challenge_action',
                'challenger_id' => $challenger->id,
                'challenger_name' => $challenger->name,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'character' => $requiredChar->value,
            ]);

            // Resolve challenge
            if ($actor->hasCharacter($requiredChar)) {
                // Skip card swap if this challenge loss will end the game
                $gameWillEnd = $challenger->influenceCount() <= 1
                    && $game->alivePlayers()->count() <= 2;
                $actor->proveCharacter($requiredChar, $gameWillEnd);

                $game->appendLog([
                    'type' => 'challenge_failed',
                    'proven_by' => $actor->id,
                    'proven_by_name' => $actor->name,
                    'character' => $requiredChar->value,
                    'loser_id' => $challenger->id,
                    'loser_name' => $challenger->name,
                ]);

                // Challenger must lose influence
                $this->queueInfluenceLoss($game, $challenger->id, 'challenge_lost');
            } else {
                // Actor can't prove → actor loses 1 influence, action fails
                $game->appendLog([
                    'type' => 'challenge_succeeded',
                    'challenger_id' => $challenger->id,
                    'challenger_name' => $challenger->name,
                    'actor_id' => $actor->id,
                    'actor_name' => $actor->name,
                ]);

                // Refund cost (Assassinate cost is refunded when challenge succeeds against actor)
                if ($action->cost() > 0 && !($ts['cost_paid'] ?? false)) {
                    // Cost wasn't paid yet (it's paid on resolve for Assassinate)
                    // No refund needed
                } elseif ($action === ActionType::ASSASSINATE) {
                    // Assassinate cost not yet deducted, no refund
                }

                // Actor loses influence, then turn ends
                $ts['action_failed'] = true;
                $game->turn_state = $ts;
                $game->save();

                $this->queueInfluenceLoss($game, $actor->id, 'challenge_lost');
            }
        });
    }

    // ══════════════════════════════════════════════════════════════
    // STEP 5: DECLARE BLOCK
    // ══════════════════════════════════════════════════════════════

    public function declareBlock(Game $game, Player $blocker, string $characterStr): void
    {
        DB::transaction(function () use ($game, $blocker, $characterStr) {
            $game = Game::lockForUpdate()->find($game->id);
            $game->load('players');
            $this->touchActivity($game, $blocker);
            $ts = $game->turn_state;

            if ($game->phase !== GamePhase::AWAITING_BLOCK) {
                throw new \RuntimeException('Fase inválida para bloquear.');
            }

            $action = ActionType::from($ts['action']);
            $blockChar = Character::from($characterStr);

            // Validate: is this character one that can block this action?
            if (!in_array($blockChar, $action->blockedBy())) {
                throw new \RuntimeException("$blockChar->value não pode bloquear {$action->value}.");
            }

            // Validate: blocker must be eligible
            if ($action === ActionType::FOREIGN_AID) {
                // Any player can block with Duke
                if ($blocker->id === $ts['actor_id']) {
                    throw new \RuntimeException('Não pode bloquear a própria ação.');
                }
            } else {
                // Only the target can block Assassinate/Steal
                if ($blocker->id !== $ts['target_id']) {
                    throw new \RuntimeException('Apenas o alvo pode bloquear esta ação.');
                }
            }

            $ts['blocker_id'] = $blocker->id;
            $ts['block_character'] = $blockChar->value;
            $ts['passed_players'] = [];
            $game->turn_state = $ts;
            $game->phase = GamePhase::AWAITING_CHALLENGE_BLOCK;
            $game->save();

            $game->appendLog([
                'type' => 'block_declared',
                'blocker_id' => $blocker->id,
                'blocker_name' => $blocker->name,
                'block_character' => $blockChar->value,
                'block_character_label' => $blockChar->label(),
                'action' => $action->value,
            ]);

            $this->broadcastAll($game->fresh('players'));
        });
    }

    // ══════════════════════════════════════════════════════════════
    // STEP 5: CHALLENGE BLOCK
    // ══════════════════════════════════════════════════════════════

    public function challengeBlock(Game $game, Player $challenger): void
    {
        DB::transaction(function () use ($game, $challenger) {
            $game = Game::lockForUpdate()->find($game->id);
            $game->load('players');
            $this->touchActivity($game, $challenger);
            $ts = $game->turn_state;

            if ($game->phase !== GamePhase::AWAITING_CHALLENGE_BLOCK) {
                throw new \RuntimeException('Fase inválida para contestar bloqueio.');
            }
            if ($challenger->id === $ts['blocker_id']) {
                throw new \RuntimeException('Não pode contestar o próprio bloqueio.');
            }

            $ts['block_challenger_id'] = $challenger->id;
            $ts['passed_players'] = [];
            $game->turn_state = $ts;
            $game->phase = GamePhase::RESOLVING_CHALLENGE_BLOCK;
            $game->save();

            $blocker = Player::find($ts['blocker_id']);
            $blockChar = Character::from($ts['block_character']);

            $game->appendLog([
                'type' => 'challenge_block',
                'challenger_id' => $challenger->id,
                'challenger_name' => $challenger->name,
                'blocker_id' => $blocker->id,
                'blocker_name' => $blocker->name,
                'block_character' => $blockChar->value,
            ]);

            if ($blocker->hasCharacter($blockChar)) {
                // Skip card swap if this challenge loss will end the game
                $gameWillEnd = $challenger->influenceCount() <= 1
                    && $game->alivePlayers()->count() <= 2;
                $blocker->proveCharacter($blockChar, $gameWillEnd);

                $game->appendLog([
                    'type' => 'challenge_block_failed',
                    'proven_by' => $blocker->id,
                    'proven_by_name' => $blocker->name,
                    'character' => $blockChar->value,
                    'loser_id' => $challenger->id,
                    'loser_name' => $challenger->name,
                ]);

                // Queue challenger influence loss, then block stands
                $ts['block_challenge_result'] = 'blocker_wins';
                $game->turn_state = $ts;
                $game->save();
                $this->queueInfluenceLoss($game, $challenger->id, 'challenge_block_lost');
            } else {
                // Blocker can't prove → blocker loses influence, block fails, action resolves
                $game->appendLog([
                    'type' => 'challenge_block_succeeded',
                    'challenger_id' => $challenger->id,
                    'challenger_name' => $challenger->name,
                    'blocker_id' => $blocker->id,
                    'blocker_name' => $blocker->name,
                ]);

                $ts['block_challenge_result'] = 'challenger_wins';
                $ts['block_failed'] = true;
                $game->turn_state = $ts;
                $game->save();

                // Blocker loses influence, then action resolves (double-death risk for Assassinate)
                $this->queueInfluenceLoss($game, $blocker->id, 'challenge_block_lost');
            }
        });
    }

    // ══════════════════════════════════════════════════════════════
    // INFLUENCE LOSS
    // ══════════════════════════════════════════════════════════════

    /**
     * Queue an influence loss. If the player has 1 influence, auto-resolve.
     * If the player has 2, prompt them to choose.
     */
    private function queueInfluenceLoss(Game $game, int $playerId, string $reason): void
    {
        $player = Player::find($playerId);
        if (!$player || !$player->is_alive) {
            // Player already dead, skip and continue
            // Still save the reason so continueAfterInfluenceLoss knows what to do
            $ts = $game->turn_state;
            $ts['influence_loss_reason'] = $reason;
            $game->turn_state = $ts;
            $game->save();
            $this->continueAfterInfluenceLoss($game);
            return;
        }

        // Always save reason to turn_state so continueAfterInfluenceLoss can read it
        $ts = $game->turn_state;
        $ts['awaiting_influence_loss_from'] = $playerId;
        $ts['influence_loss_reason'] = $reason;
        $game->turn_state = $ts;

        if ($player->influenceCount() === 1) {
            // Only 1 card: auto-reveal
            $card = $player->influences[0];
            $player->loseInfluence($card);

            $game->appendLog([
                'type' => 'influence_lost',
                'player_id' => $player->id,
                'player_name' => $player->name,
                'character' => $card,
                'reason' => $reason,
            ]);

            if (!$player->is_alive) {
                $player->exile();
                $game->appendLog([
                    'type' => 'player_exiled',
                    'player_id' => $player->id,
                    'player_name' => $player->name,
                ]);
            }

            $game->save();
            $this->continueAfterInfluenceLoss($game);
        } else {
            // Player must choose which card to lose
            $game->phase = GamePhase::AWAITING_INFLUENCE_LOSS;
            $game->save();

            $this->broadcastAll($game->fresh('players'));
        }
    }

    /**
     * Player chooses which influence to lose.
     */
    public function chooseInfluenceLoss(Game $game, Player $player, string $characterValue): void
    {
        DB::transaction(function () use ($game, $player, $characterValue) {
            $game = Game::lockForUpdate()->find($game->id);
            $this->touchActivity($game, $player);
            $ts = $game->turn_state;

            if ($game->phase !== GamePhase::AWAITING_INFLUENCE_LOSS) {
                throw new \RuntimeException('Fase inválida.');
            }
            if ($player->id !== ($ts['awaiting_influence_loss_from'] ?? null)) {
                throw new \RuntimeException('Não é sua vez de perder influência.');
            }

            $player = Player::find($player->id); // refresh
            if (!$player->loseInfluence($characterValue)) {
                throw new \RuntimeException('Você não possui esta carta.');
            }

            $reason = $ts['influence_loss_reason'] ?? 'unknown';

            $game->appendLog([
                'type' => 'influence_lost',
                'player_id' => $player->id,
                'player_name' => $player->name,
                'character' => $characterValue,
                'reason' => $reason,
            ]);

            if (!$player->is_alive) {
                $player->exile();
                $game->appendLog([
                    'type' => 'player_exiled',
                    'player_id' => $player->id,
                    'player_name' => $player->name,
                ]);
            }

            $this->continueAfterInfluenceLoss($game);
        });
    }

    /**
     * After influence loss resolves, determine what happens next.
     */
    private function continueAfterInfluenceLoss(Game $game): void
    {
        $game = $game->fresh('players');
        $ts = $game->turn_state;

        // Check for game over
        $alive = $game->alivePlayers()->get();
        if ($alive->count() <= 1) {
            $game->phase = GamePhase::GAME_OVER;
            $game->winner_id = $alive->first()?->id;
            $game->save();

            $game->appendLog([
                'type' => 'game_over',
                'winner_id' => $alive->first()?->id,
                'winner_name' => $alive->first()?->name,
            ]);

            $this->saveGameResults($game->fresh('players'));
            $this->broadcastAll($game->fresh('players'));
            return;
        }

        $action = ActionType::from($ts['action']);
        $reason = $ts['influence_loss_reason'] ?? '';

        // Clear the awaiting fields
        $ts['awaiting_influence_loss_from'] = null;
        $ts['influence_loss_reason'] = null;
        $game->turn_state = $ts;
        $game->save();

        // Determine where in the flow we are and what happens next
        if ($reason === 'coup') {
            // Coup resolved; end turn
            $this->endTurn($game);
            return;
        }

        if ($reason === 'challenge_lost') {
            // Challenge on original action resolved
            if ($ts['action_failed'] ?? false) {
                // Actor couldn't prove → action fails → end turn
                $this->endTurn($game);
                return;
            }
            // Challenger lost → action continues
            if ($action->canBeBlocked()) {
                $game->phase = GamePhase::AWAITING_BLOCK;
                $game->save();
                $this->broadcastAll($game->fresh('players'));
            } else {
                $this->resolveAction($game);
            }
            return;
        }

        if ($reason === 'challenge_block_lost') {
            $blockResult = $ts['block_challenge_result'] ?? null;
            if ($blockResult === 'blocker_wins') {
                // Block stands → action fails
                $this->blockSucceeds($game);
            } else {
                // Block failed → action resolves
                // But if it's assassinate, the target (blocker) may now lose another influence
                $this->resolveAction($game);
            }
            return;
        }

        if ($reason === 'assassinated') {
            // Assassinate effect applied → end turn
            $this->endTurn($game);
            return;
        }

        // Default: end turn
        $this->endTurn($game);
    }

    // ══════════════════════════════════════════════════════════════
    // RESOLVE ACTION
    // ══════════════════════════════════════════════════════════════

    private function resolveAction(Game $game): void
    {
        $game = $game->fresh('players');
        $ts = $game->turn_state;
        $action = ActionType::from($ts['action']);
        $actor = Player::find($ts['actor_id']);
        $target = $ts['target_id'] ? Player::find($ts['target_id']) : null;

        switch ($action) {
            case ActionType::INCOME:
                $actor->coins += 1;
                $actor->save();
                $game->treasury -= 1;
                $game->save();

                $game->appendLog([
                    'type' => 'action_resolved',
                    'action' => 'income',
                    'actor_id' => $actor->id,
                    'actor_name' => $actor->name,
                    'coins' => 1,
                ]);
                $this->endTurn($game);
                break;

            case ActionType::FOREIGN_AID:
                $actor->coins += 2;
                $actor->save();
                $game->treasury -= 2;
                $game->save();

                $game->appendLog([
                    'type' => 'action_resolved',
                    'action' => 'foreign_aid',
                    'actor_id' => $actor->id,
                    'actor_name' => $actor->name,
                    'coins' => 2,
                ]);
                $this->endTurn($game);
                break;

            case ActionType::TAX:
                $actor->coins += 3;
                $actor->save();
                $game->treasury -= 3;
                $game->save();

                $game->appendLog([
                    'type' => 'action_resolved',
                    'action' => 'tax',
                    'actor_id' => $actor->id,
                    'actor_name' => $actor->name,
                    'coins' => 3,
                ]);
                $this->endTurn($game);
                break;

            case ActionType::STEAL:
                if ($target && $target->is_alive) {
                    $stolen = min(2, $target->coins);
                    $target->coins -= $stolen;
                    $target->save();
                    $actor->coins += $stolen;
                    $actor->save();

                    $game->appendLog([
                        'type' => 'action_resolved',
                        'action' => 'steal',
                        'actor_id' => $actor->id,
                        'actor_name' => $actor->name,
                        'target_id' => $target->id,
                        'target_name' => $target->name,
                        'coins' => $stolen,
                    ]);
                }
                $this->endTurn($game);
                break;

            case ActionType::ASSASSINATE:
                // Pay cost
                $actor->coins -= 3;
                $actor->save();
                $game->treasury += 3;
                $game->save();

                $game->appendLog([
                    'type' => 'action_resolved',
                    'action' => 'assassinate',
                    'actor_id' => $actor->id,
                    'actor_name' => $actor->name,
                    'target_id' => $target?->id,
                    'target_name' => $target?->name,
                ]);

                // Target loses 1 influence
                if ($target && $target->is_alive) {
                    $this->queueInfluenceLoss($game, $target->id, 'assassinated');
                } else {
                    $this->endTurn($game);
                }
                break;

            case ActionType::EXCHANGE:
                // Draw 2 cards from deck
                $drawn = $game->drawCards(2);

                // Combine with actor's current influences
                $actor = Player::find($actor->id); // refresh
                $allCards = array_merge($actor->influences, $drawn);

                $ts['exchange_options'] = $allCards;
                $ts['exchange_keep_count'] = $actor->influenceCount();
                $game->turn_state = $ts;
                $game->phase = GamePhase::AWAITING_EXCHANGE_RETURN;
                $game->save();

                $game->appendLog([
                    'type' => 'exchange_started',
                    'actor_id' => $actor->id,
                    'actor_name' => $actor->name,
                ]);

                $this->broadcastAll($game->fresh('players'));
                // Send private exchange options to the actor
                broadcast(new PrivateStateUpdated($game, $actor, [
                    'exchange_options' => $allCards,
                    'exchange_keep_count' => $actor->influenceCount(),
                ]));
                break;

            default:
                $this->endTurn($game);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // EXCHANGE RETURN
    // ══════════════════════════════════════════════════════════════

    public function chooseExchangeCards(Game $game, Player $actor, array $keepCards): void
    {
        DB::transaction(function () use ($game, $actor, $keepCards) {
            $game = Game::lockForUpdate()->find($game->id);
            $this->touchActivity($game, $actor);
            $ts = $game->turn_state;

            if ($game->phase !== GamePhase::AWAITING_EXCHANGE_RETURN) {
                throw new \RuntimeException('Fase inválida.');
            }
            if ($actor->id !== $ts['actor_id']) {
                throw new \RuntimeException('Apenas o ator pode escolher cartas.');
            }

            $available = $ts['exchange_options'];
            $keepCount = $ts['exchange_keep_count'];

            if (count($keepCards) !== $keepCount) {
                throw new \RuntimeException("Deve manter exatamente $keepCount carta(s).");
            }

            // Validate that keepCards are a subset of available
            $availCopy = $available;
            foreach ($keepCards as $card) {
                $idx = array_search($card, $availCopy);
                if ($idx === false) {
                    throw new \RuntimeException("Carta '$card' não disponível.");
                }
                array_splice($availCopy, $idx, 1);
            }

            // Return the remaining cards to the deck
            $actor->influences = array_values($keepCards);
            $actor->save();

            $game->returnCardsToDeck($availCopy);

            $game->appendLog([
                'type' => 'exchange_completed',
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
            ]);

            $this->endTurn($game);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // BLOCK SUCCEEDS
    // ══════════════════════════════════════════════════════════════

    private function blockSucceeds(Game $game): void
    {
        $ts = $game->turn_state;
        $action = ActionType::from($ts['action']);

        $game->appendLog([
            'type' => 'block_succeeded',
            'blocker_id' => $ts['blocker_id'],
            'action' => $action->value,
        ]);

        // If action had cost (Assassinate), cost is NOT refunded when block succeeds
        // But cost hasn't been deducted yet for Assassinate (it's deducted on resolve)
        // Actually, per the rules, the cost IS paid regardless. For Assassinate blocked by Contessa,
        // the 3 coins are paid but the assassination doesn't happen.
        if ($action === ActionType::ASSASSINATE) {
            $actor = Player::find($ts['actor_id']);
            $actor->coins -= 3;
            $actor->save();
            $game->treasury += 3;
            $game->save();
        }

        $this->endTurn($game);
    }

    // ══════════════════════════════════════════════════════════════
    // END TURN
    // ══════════════════════════════════════════════════════════════

    private function endTurn(Game $game): void
    {
        $game = $game->fresh('players');

        // Check win condition
        $alive = $game->alivePlayers()->get();
        if ($alive->count() <= 1) {
            $game->phase = GamePhase::GAME_OVER;
            $game->winner_id = $alive->first()?->id;
            $game->save();

            $game->appendLog([
                'type' => 'game_over',
                'winner_id' => $alive->first()?->id,
                'winner_name' => $alive->first()?->name,
            ]);

            $this->saveGameResults($game->fresh('players'));
            $this->broadcastAll($game->fresh('players'));
            return;
        }

        // Find actor's seat for correct seat-based advancement
        $ts = $game->turn_state;
        $actorSeat = null;
        if ($ts && isset($ts['actor_id'])) {
            $actor = Player::find($ts['actor_id']);
            $actorSeat = $actor?->seat;
        }

        $game->advanceTurn($actorSeat);

        $game->appendLog([
            'type' => 'turn_start',
            'player_id' => $game->currentPlayer()?->id,
            'player_name' => $game->currentPlayer()?->name,
            'turn' => $game->turn_number,
        ]);

        $this->broadcastAll($game->fresh('players'));
    }

    // ══════════════════════════════════════════════════════════════
    // ABANDON GAME (player leaves voluntarily)
    // ══════════════════════════════════════════════════════════════

    public function abandonGame(Game $game, Player $player): void
    {
        DB::transaction(function () use ($game, $player) {
            $game = Game::lockForUpdate()->find($game->id);
            $game->load('players');
            $player = Player::find($player->id);

            // Spectators can simply leave
            if ($player && $player->is_spectator) {
                $player->delete();
                $this->broadcastGameState($game);
                return;
            }

            if (!$player || !$player->is_alive) {
                throw new \RuntimeException('Jogador já eliminado.');
            }

            if ($game->phase === GamePhase::LOBBY) {
                throw new \RuntimeException('O jogo ainda não começou.');
            }

            if ($game->phase === GamePhase::GAME_OVER) {
                throw new \RuntimeException('O jogo já terminou.');
            }

            // Reveal all hidden influences
            $revealed = $player->revealed ?? [];
            foreach ($player->influences as $card) {
                $revealed[] = $card;
            }
            $player->influences = [];
            $player->revealed = $revealed;
            $player->is_alive = false;
            $player->save();
            $player->exile();

            $game->appendLog([
                'type' => 'player_abandoned',
                'player_id' => $player->id,
                'player_name' => $player->name,
            ]);

            // Check win condition
            $alive = $game->alivePlayers()->get();
            if ($alive->count() <= 1) {
                $game->phase = GamePhase::GAME_OVER;
                $game->winner_id = $alive->first()?->id;
                $game->save();

                $game->appendLog([
                    'type' => 'game_over',
                    'winner_id' => $alive->first()?->id,
                    'winner_name' => $alive->first()?->name,
                ]);

                $this->saveGameResults($game->fresh('players'));
                $this->broadcastAll($game->fresh('players'));
                return;
            }

            // Handle mid-turn involvement
            $ts = $game->turn_state;
            $needsEndTurn = false;

            if ($ts) {
                $actorId = $ts['actor_id'] ?? null;
                $targetId = $ts['target_id'] ?? null;
                $blockerId = $ts['blocker_id'] ?? null;
                $awaitingLossFrom = $ts['awaiting_influence_loss_from'] ?? null;

                // Case 1: Player was awaiting influence loss → auto-continue
                if ($game->phase === GamePhase::AWAITING_INFLUENCE_LOSS && $awaitingLossFrom === $player->id) {
                    $ts['awaiting_influence_loss_from'] = null;
                    $ts['influence_loss_reason'] = null;
                    $game->turn_state = $ts;
                    $game->save();
                    $this->continueAfterInfluenceLoss($game);
                    return;
                }

                // Case 2: Player was the actor (it's their turn) → end turn
                if ($actorId === $player->id) {
                    // Refund assassinate cost if it was paid on declare but not yet resolved
                    // (cost is paid on resolve, so no refund needed here)
                    $needsEndTurn = true;
                }

                // Case 3: Player was the exchange actor → return drawn cards to deck, end turn
                if ($game->phase === GamePhase::AWAITING_EXCHANGE_RETURN && $actorId === $player->id) {
                    $exchangeOptions = $ts['exchange_options'] ?? [];
                    if (!empty($exchangeOptions)) {
                        $game->returnCardsToDeck($exchangeOptions);
                    }
                    $needsEndTurn = true;
                }

                // Case 4: Player was a blocker → block fails, resolve action
                if ($blockerId === $player->id && in_array($game->phase, [
                    GamePhase::AWAITING_CHALLENGE_BLOCK,
                    GamePhase::RESOLVING_CHALLENGE_BLOCK,
                ])) {
                    $needsEndTurn = true;
                }

                // Case 5: Player is in a reaction window → remove from eligible, check pass completion
                if (in_array($game->phase, [
                    GamePhase::AWAITING_CHALLENGE_ACTION,
                    GamePhase::AWAITING_BLOCK,
                    GamePhase::AWAITING_CHALLENGE_BLOCK,
                ]) && !$needsEndTurn) {
                    // Add to passed list so the window can advance
                    $passed = $ts['passed_players'] ?? [];
                    if (!in_array($player->id, $passed)) {
                        $passed[] = $player->id;
                        $ts['passed_players'] = $passed;
                        $game->turn_state = $ts;
                        $game->save();
                    }

                    // Check if all remaining eligible have passed
                    $eligible = $this->getEligibleReactors($game, $ts);
                    $allPassed = empty(array_diff($eligible, $passed));

                    if ($allPassed) {
                        $ts['passed_players'] = [];
                        $game->turn_state = $ts;
                        $game->save();
                        $this->advanceAfterWindow($game);
                        return;
                    }

                    $this->broadcastAll($game->fresh('players'));
                    return;
                }

                // Case 6: It was this player's turn (action_selection phase) → advance
                if ($game->phase === GamePhase::ACTION_SELECTION) {
                    $currentPlayer = $game->currentPlayer();
                    if ($currentPlayer && $currentPlayer->id === $player->id) {
                        $needsEndTurn = true;
                    }
                }
            } elseif ($game->phase === GamePhase::ACTION_SELECTION) {
                // No turn state yet, but it's this player's turn
                $currentPlayer = $game->currentPlayer();
                if ($currentPlayer && $currentPlayer->id === $player->id) {
                    $needsEndTurn = true;
                }
            }

            if ($needsEndTurn) {
                // Use seat-based advancement from the abandoning player's seat
                $game->advanceTurn($player->seat);

                $game->appendLog([
                    'type' => 'turn_start',
                    'player_id' => $game->currentPlayer()?->id,
                    'player_name' => $game->currentPlayer()?->name,
                    'turn' => $game->turn_number,
                ]);

                $this->broadcastAll($game->fresh('players'));
            } else {
                $this->broadcastAll($game->fresh('players'));
            }
        });
    }

    // ══════════════════════════════════════════════════════════════
    // REMATCH
    // ══════════════════════════════════════════════════════════════

    public function rematchGame(Game $game): void
    {
        DB::transaction(function () use ($game) {
            $game = Game::lockForUpdate()->find($game->id);

            if ($game->phase !== GamePhase::GAME_OVER) {
                throw new \RuntimeException('A partida ainda não terminou.');
            }

            // Reset all players (including spectators → become regular players)
            $allPlayers = $game->players()->get();
            $seatIndex = 0;
            foreach ($allPlayers as $player) {
                $player->coins = 2;
                $player->influences = [];
                $player->revealed = [];
                $player->is_alive = true;
                $player->is_ready = false;
                $player->is_spectator = false;
                $player->seat = -($seatIndex + 100); // temp negative to avoid constraint
                $player->save();
                $seatIndex++;
            }
            // Reassign proper seats
            foreach ($allPlayers->values() as $i => $player) {
                $player->seat = $i;
                $player->save();
            }

            // Reset game to lobby
            $game->phase = GamePhase::LOBBY;
            $game->deck = [];
            $game->treasury = 0;
            $game->current_player_index = 0;
            $game->turn_number = 0;
            $game->turn_state = null;
            $game->event_log = [];
            $game->winner_id = null;
            $game->save();

            $this->broadcastAll($game->fresh('players'));
        });
    }

    // ══════════════════════════════════════════════════════════════
    // BROADCASTING
    // ══════════════════════════════════════════════════════════════

    private function broadcastPublic(Game $game): void
    {
        broadcast(new GameUpdated($game));
    }

    private function broadcastAll(Game $game): void
    {
        broadcast(new GameUpdated($game));

        // Send private state to each alive player
        foreach ($game->players as $player) {
            broadcast(new PrivateStateUpdated($game, $player));
        }
    }

    // ══════════════════════════════════════════════════════════════
    // SAVE GAME RESULTS
    // ══════════════════════════════════════════════════════════════

    private function saveGameResults(Game $game): void
    {
        try {
            // Don't save if results already exist for this specific round
            // (same game_id + started_at identifies a unique round, even after rematch)
            if (GameResult::where('game_id', $game->id)
                    ->where('started_at', $game->started_at)
                    ->exists()) {
                return;
            }

            $players = $game->gamePlayers()->orderBy('seat')->get();
            $totalPlayers = $players->count();
            $totalTurns = $game->turn_number;
            $fullLog = $game->event_log ?? [];

            // Determine death order from event log (player_exiled events)
            $deathOrder = collect($fullLog)
                ->filter(fn($e) => ($e['type'] ?? '') === 'player_exiled')
                ->pluck('player_id')
                ->values()
                ->toArray();

            foreach ($players as $player) {
                $isWinner = $player->id === $game->winner_id;

                // Placement: winner=1, alive non-winner=2, dead by reverse death order
                if ($isWinner) {
                    $placement = 1;
                } elseif ($player->is_alive) {
                    $placement = 2;
                } else {
                    // Earlier death = worse placement; last dead before winner = placement 2
                    $deathIndex = array_search($player->id, $deathOrder);
                    if ($deathIndex !== false) {
                        // deathIndex 0 = died first = worst placement
                        $placement = $totalPlayers - $deathIndex;
                    } else {
                        $placement = $totalPlayers;
                    }
                }

                GameResult::create([
                    'game_id' => $game->id,
                    'player_id' => $player->id,
                    'player_name' => $player->name,
                    'seat' => $player->seat,
                    'coins' => $player->coins,
                    'influences' => $player->influences ?? [],
                    'revealed' => $player->revealed ?? [],
                    'is_winner' => $isWinner,
                    'is_alive' => $player->is_alive,
                    'placement' => $placement,
                    'total_players' => $totalPlayers,
                    'total_turns' => $totalTurns,
                    'full_event_log' => $isWinner ? $fullLog : [],
                    'started_at' => $game->started_at,
                ]);
            }
        } catch (\Throwable $e) {
            // Log error but don't let it prevent game from ending
            \Illuminate\Support\Facades\Log::error('Failed to save game results: ' . $e->getMessage(), [
                'game_id' => $game->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
