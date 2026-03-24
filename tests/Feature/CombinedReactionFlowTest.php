<?php

namespace Tests\Feature;

use App\Enums\GamePhase;
use App\Models\Game;
use App\Models\Player;
use App\Services\GameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CombinedReactionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_challenge_to_steal_resolves_immediately_without_block_window(): void
    {
        [$game, $actor, $target] = $this->createRunningGame(
            actorCoins: 2,
            actorInfluences: ['captain', 'duke'],
            targetCoins: 4,
            targetInfluences: ['ambassador', 'contessa']
        );

        $service = app(GameService::class);

        $service->declareAction($game, $actor, 'steal', $target->id);
        $service->challengeAction($game, $target);

        $game = $game->fresh();
        $this->assertSame(GamePhase::AWAITING_INFLUENCE_LOSS, $game->phase);
        $this->assertSame($target->id, $game->turn_state['awaiting_influence_loss_from']);

        $service->chooseInfluenceLoss($game, $target->fresh(), 'ambassador');

        $game = $game->fresh();
        $actor = $actor->fresh();
        $target = $target->fresh();

        $this->assertSame(GamePhase::ACTION_SELECTION, $game->phase);
        $this->assertNull($game->turn_state);
        $this->assertSame($target->id, $game->currentPlayer()?->id);
        $this->assertSame(4, $actor->coins);
        $this->assertSame(2, $target->coins);
        $this->assertSame(['contessa'], $target->influences);
        $this->assertSame(['ambassador'], $target->revealed);

        $eventTypes = collect($game->event_log)->pluck('type');
        $this->assertTrue($eventTypes->contains('challenge_failed'));
        $this->assertTrue($eventTypes->contains('action_resolved'));
        $this->assertFalse($eventTypes->contains('block_declared'));
    }

    public function test_failed_challenge_to_assassinate_does_not_open_block_window(): void
    {
        [$game, $actor, $target, $third] = $this->createRunningGame(
            actorCoins: 5,
            actorInfluences: ['assassin', 'duke'],
            targetCoins: 2,
            targetInfluences: ['contessa', 'captain'],
            thirdInfluences: ['ambassador', 'duke']
        );

        $service = app(GameService::class);

        $service->declareAction($game, $actor, 'assassinate', $target->id);
        $service->challengeAction($game, $target);

        $game = $game->fresh();
        $this->assertSame(GamePhase::AWAITING_INFLUENCE_LOSS, $game->phase);
        $this->assertSame($target->id, $game->turn_state['awaiting_influence_loss_from']);

        $service->chooseInfluenceLoss($game, $target->fresh(), 'captain');

        $game = $game->fresh();
        $actor = $actor->fresh();
        $target = $target->fresh();
        $third = $third->fresh();

        $this->assertSame(GamePhase::ACTION_SELECTION, $game->phase);
        $this->assertNull($game->turn_state);
        $this->assertSame($third->id, $game->currentPlayer()?->id);
        $this->assertSame(2, $actor->coins);
        $this->assertFalse($target->is_alive);
        $this->assertSame([], $target->influences);
        $this->assertCount(2, $target->revealed);

        $eventTypes = collect($game->event_log)->pluck('type');
        $this->assertTrue($eventTypes->contains('challenge_failed'));
        $this->assertTrue($eventTypes->contains('action_resolved'));
        $this->assertFalse($eventTypes->contains('block_declared'));
        $this->assertFalse($eventTypes->contains('block_succeeded'));
    }

    public function test_target_can_block_during_initial_reaction_window(): void
    {
        [$game, $actor, $target] = $this->createRunningGame(
            actorCoins: 2,
            actorInfluences: ['captain', 'duke'],
            targetCoins: 4,
            targetInfluences: ['ambassador', 'contessa']
        );

        $service = app(GameService::class);

        $service->declareAction($game, $actor, 'steal', $target->id);
        $service->declareBlock($game->fresh(), $target->fresh(), 'ambassador');

        $game = $game->fresh();

        $this->assertSame(GamePhase::AWAITING_CHALLENGE_BLOCK, $game->phase);
        $this->assertSame($target->id, $game->turn_state['blocker_id']);
        $this->assertSame('ambassador', $game->turn_state['block_character']);
        $this->assertTrue(collect($game->event_log)->pluck('type')->contains('block_declared'));
    }

    private function createRunningGame(
        int $actorCoins,
        array $actorInfluences,
        int $targetCoins,
        array $targetInfluences,
        array $thirdInfluences = ['duke', 'captain']
    ): array {
        $game = Game::create([
            'code' => strtoupper(Str::random(6)),
            'phase' => GamePhase::ACTION_SELECTION,
            'deck' => ['duke', 'assassin', 'captain', 'ambassador', 'contessa'],
            'treasury' => 50,
            'current_player_index' => 0,
            'turn_state' => null,
            'event_log' => [],
            'turn_number' => 1,
            'last_activity_at' => now(),
            'started_at' => now(),
        ]);

        $actor = $this->makePlayer($game, 0, 'Ator', $actorCoins, $actorInfluences);
        $target = $this->makePlayer($game, 1, 'Alvo', $targetCoins, $targetInfluences);
        $third = $this->makePlayer($game, 2, 'Terceiro', 2, $thirdInfluences);

        return [$game, $actor, $target, $third];
    }

    private function makePlayer(Game $game, int $seat, string $name, int $coins, array $influences): Player
    {
        return Player::create([
            'game_id' => $game->id,
            'name' => $name,
            'token' => Str::random(64),
            'seat' => $seat,
            'coins' => $coins,
            'influences' => $influences,
            'revealed' => [],
            'is_alive' => true,
            'is_host' => $seat === 0,
            'is_spectator' => false,
            'is_ready' => true,
            'last_activity_at' => now(),
        ]);
    }
}
