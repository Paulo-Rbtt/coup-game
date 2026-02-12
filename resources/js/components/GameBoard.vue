<template>
  <div class="min-h-screen flex flex-col">
    <!-- Top bar -->
    <header class="flex items-center justify-between px-4 py-3 bg-gray-900/80 backdrop-blur border-b border-gray-800">
      <div class="flex items-center gap-3">
        <h1 class="text-xl font-black text-amber-400">COUP</h1>
        <span class="text-xs text-gray-500 font-mono">{{ state.game.code }}</span>
        <!-- Connection indicator -->
        <span class="flex items-center gap-1 text-xs" :class="isHostMode ? 'text-emerald-400' : 'text-sky-400'">
          <span class="w-1.5 h-1.5 rounded-full" :class="isHostMode ? 'bg-emerald-400' : 'bg-sky-400'"></span>
          {{ isHostMode ? 'Host' : 'LAN' }}
        </span>
      </div>
      <div class="flex items-center gap-4">
        <span class="text-sm text-gray-400 hidden sm:inline">Turno {{ state.game.turn_number }}</span>
        <div class="flex items-center gap-1 text-amber-400">
          <CoinIcon class="w-4 h-4" />
          <span class="text-sm font-bold">{{ state.game.treasury }}</span>
          <span class="text-xs text-gray-500 hidden sm:inline">tesouro</span>
        </div>
        <div class="flex items-center gap-1 text-gray-400">
          <span class="text-xs">🃏 {{ state.game.deck_count }}</span>
        </div>
        <button @click="confirmLeave"
                class="ml-2 px-3 py-1 text-xs rounded-lg bg-red-900/60 hover:bg-red-800 text-red-300 border border-red-700 transition-colors cursor-pointer">
          Sair
        </button>
      </div>
    </header>

    <!-- Main content -->
    <div class="flex-1 flex flex-col lg:flex-row gap-4 p-4">
      <!-- Opponents area -->
      <div class="flex-1">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <PlayerCard v-for="player in otherPlayers" :key="player.id"
                      :player="player"
                      :isCurrentTurn="state.game.current_player_id === player.id"
                      :isTurnStateTarget="state.game.turn_state?.target_id === player.id"
                      @select="selectTarget(player.id)" />
        </div>

        <!-- Phase indicator -->
        <PhaseIndicator :phase="state.game.phase"
                        :turnState="state.game.turn_state"
                        :players="state.game.players"
                        class="mt-4" />

        <!-- Event log -->
        <EventLog :events="state.game.event_log" :players="state.game.players" class="mt-4" />
      </div>

      <!-- My area (right panel) -->
      <div class="lg:w-80 space-y-4">
        <!-- My info -->
        <div class="bg-gray-800/60 backdrop-blur rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-amber-400">{{ state.player?.name }}</h3>
            <div class="flex items-center gap-1">
              <CoinIcon class="w-5 h-5 text-amber-400" />
              <span class="text-lg font-bold text-amber-400">{{ state.player?.coins }}</span>
            </div>
          </div>

          <!-- My cards -->
          <div class="flex gap-2">
            <CharacterCard v-for="(card, index) in myInfluences" :key="'inf-'+index"
                           :character="card"
                           :faceUp="true"
                           :selectable="isChoosingInfluenceLoss"
                           @select="handleLoseInfluence(card)"
                           class="flex-1" />
            <CharacterCard v-for="(card, index) in (state.player?.revealed || [])" :key="'rev-'+index"
                           :character="card"
                           :faceUp="true"
                           :dead="true"
                           class="flex-1 opacity-40" />
          </div>

          <!-- Must coup warning -->
          <div v-if="mustCoup && isMyTurn" class="mt-3 px-3 py-2 rounded-lg bg-red-900/40 border border-red-700 text-red-300 text-xs text-center">
            ⚠ 10+ moedas: você DEVE dar Golpe de Estado
          </div>
        </div>

        <!-- Action buttons -->
        <ActionPanel v-if="isMyTurn && state.game.phase === 'action_selection'"
                     :coins="state.player?.coins"
                     :mustCoup="mustCoup"
                     :opponents="aliveOpponents"
                     @action="handleAction" />

        <!-- Reaction buttons (challenge/block/pass) -->
        <ReactionPanel v-if="showReactionPanel"
                       :phase="state.game.phase"
                       :turnState="state.game.turn_state"
                       :myId="state.player?.id"
                       @pass="pass"
                       @challenge="challengeAction"
                       @block="handleBlock"
                       @challenge-block="challengeBlock" />

        <!-- Influence loss chooser -->
        <InfluenceLossPanel v-if="isChoosingInfluenceLoss"
                            :influences="myInfluences"
                            @choose="handleLoseInfluence" />

        <!-- Exchange panel -->
        <ExchangePanel v-if="isExchanging"
                       :options="state.player?.exchange_options || []"
                       :keepCount="state.player?.exchange_keep_count || state.player?.influence_count || 0"
                       @confirm="handleExchange" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useGame } from '../composables/useGame';
import PlayerCard from './PlayerCard.vue';
import CharacterCard from './CharacterCard.vue';
import ActionPanel from './ActionPanel.vue';
import ReactionPanel from './ReactionPanel.vue';
import InfluenceLossPanel from './InfluenceLossPanel.vue';
import ExchangePanel from './ExchangePanel.vue';
import PhaseIndicator from './PhaseIndicator.vue';
import EventLog from './EventLog.vue';
import CoinIcon from './icons/CoinIcon.vue';

const {
  state,
  isMyTurn,
  myInfluences,
  otherPlayers,
  aliveOpponents,
  mustCoup,
  isHostMode,
  declareAction,
  pass,
  challengeAction,
  declareBlock,
  challengeBlock,
  loseInfluence,
  exchangeCards,
  abandonGame,
} = useGame();

function confirmLeave() {
  if (confirm('Tem certeza que deseja sair? Você será eliminado da partida.')) {
    abandonGame();
  }
}

const isChoosingInfluenceLoss = computed(() => {
  return state.game?.phase === 'awaiting_influence_loss'
    && state.game?.turn_state?.awaiting_influence_loss_from === state.player?.id;
});

const isExchanging = computed(() => {
  return state.game?.phase === 'awaiting_exchange_return'
    && state.game?.turn_state?.actor_id === state.player?.id
    && state.player?.exchange_options;
});

const showReactionPanel = computed(() => {
  const phase = state.game?.phase;
  const ts = state.game?.turn_state;
  if (!ts) return false;

  // Can I react?
  if (phase === 'awaiting_challenge_action') {
    return state.player?.id !== ts.actor_id && state.player?.is_alive;
  }
  if (phase === 'awaiting_block') {
    // Foreign aid → anyone but actor; others → only target
    const action = ts.action;
    if (action === 'foreign_aid') {
      return state.player?.id !== ts.actor_id && state.player?.is_alive;
    }
    return state.player?.id === ts.target_id && state.player?.is_alive;
  }
  if (phase === 'awaiting_challenge_block') {
    return state.player?.id !== ts.blocker_id && state.player?.is_alive;
  }
  return false;
});

function selectTarget(targetId) {
  // Used when picking a target for actions
  // This is handled via ActionPanel's callback
}

function handleAction({ action, targetId }) {
  declareAction(action, targetId);
}

function handleBlock(character) {
  declareBlock(character);
}

function handleLoseInfluence(character) {
  loseInfluence(character);
}

function handleExchange(keepCards) {
  exchangeCards(keepCards);
}
</script>
