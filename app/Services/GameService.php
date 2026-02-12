<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Enums\Character;
use App\Enums\GamePhase;
use App\Events\GameUpdated;
use App\Events\PrivateStateUpdated;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

class GameService
{
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
            ]);

            $player = $this->addPlayer($game, $playerName, true);

            return ['game' => $game->fresh('players'), 'player' => $player];
        });
    }

    public function joinGame(string $code, string $playerName): array
    {
        return DB::transaction(function () use ($code, $playerName) {
            $game = Game::where('code', strtoupper($code))->lockForUpdate()->firstOrFail();

            if ($game->phase !== GamePhase::LOBBY) {
                throw new \RuntimeException('A partida já começou.');
            }
            if ($game->players()->count() >= $game->max_players) {
                throw new \RuntimeException('Sala cheia.');
            }

            $player = $this->addPlayer($game, $playerName, false);

            $this->broadcastPublic($game->fresh('players'));

            return ['game' => $game->fresh('players'), 'player' => $player];
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
        $seat = $game->players()->count();
        return Player::create([
            'game_id' => $game->id,
            'name' => $name,
            'token' => bin2hex(random_bytes(32)),
            'seat' => $seat,
            'coins' => 2,
            'influences' => [],
            'revealed' => [],
            'is_host' => $isHost,
        ]);
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
            if ($game->players()->count() < $game->min_players) {
                throw new \RuntimeException("Mínimo {$game->min_players} jogadores.");
            }
            if ($game->phase !== GamePhase::LOBBY) {
                throw new \RuntimeException('Partida já iniciada.');
            }

            // Build deck: 15 cards (3 × 5 characters)
            $deck = Game::buildDeck();

            // Deal 2 cards to each player
            $players = $game->players()->orderBy('seat')->get();
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

            // Determine next phase
            if ($action === ActionType::INCOME) {
                // Income: no challenge, no block → resolve immediately
                $this->resolveAction($game);
                return;
            }

            if ($action === ActionType::COUP) {
                // Coup: no challenge, no block → target loses influence
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
                // Actor proves → challenger loses 1 influence
                $actor->proveCharacter($requiredChar);

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
                // Blocker proves → challenger loses influence, block stands
                $blocker->proveCharacter($blockChar);

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
            $this->continueAfterInfluenceLoss($game);
            return;
        }

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

            $this->continueAfterInfluenceLoss($game);
        } else {
            // Player must choose which card to lose
            $ts = $game->turn_state;
            $ts['awaiting_influence_loss_from'] = $playerId;
            $ts['influence_loss_reason'] = $reason;
            $game->turn_state = $ts;
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

            $this->broadcastAll($game->fresh('players'));
            return;
        }

        $game->advanceTurn();

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
                // Fix current_player_index since a player was removed from alive list
                $alive = $game->alivePlayers()->get();
                $currentIdx = $game->current_player_index;
                if ($currentIdx >= $alive->count()) {
                    $game->current_player_index = 0;
                }
                $game->turn_number++;
                $game->phase = GamePhase::ACTION_SELECTION;
                $game->turn_state = null;
                $game->save();

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
}
