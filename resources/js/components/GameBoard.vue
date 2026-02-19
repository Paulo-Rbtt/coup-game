<template>
  <div class="min-h-screen flex flex-col">
    <!-- Top bar (compact on mobile) -->
    <header class="flex items-center justify-between px-3 py-2 sm:px-4 sm:py-3 bg-gray-900/80 backdrop-blur border-b border-gray-800">
      <div class="flex items-center gap-2 sm:gap-3">
        <h1 class="text-lg sm:text-xl font-black text-amber-400">COUP</h1>
        <span class="text-[10px] sm:text-xs text-gray-500 font-mono">{{ state.game.code }}</span>
      </div>
      <div class="flex items-center gap-2 sm:gap-4">
        <span class="text-xs sm:text-sm text-gray-400 hidden sm:inline">Turno {{ state.game.turn_number }}</span>
        <div class="flex items-center gap-1 text-amber-400">
          <CoinIcon class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
          <span class="text-xs sm:text-sm font-bold">{{ state.game.treasury }}</span>
        </div>
        <span class="text-[10px] sm:text-xs text-gray-400">🃏 {{ state.game.deck_count }}</span>
        <button @click="confirmLeave"
                class="px-2 py-0.5 sm:px-3 sm:py-1 text-[10px] sm:text-xs rounded-lg bg-red-900/60 hover:bg-red-800 text-red-300 border border-red-700 transition-colors cursor-pointer">
          Sair
        </button>
        <button @click="showHelp = true"
                class="px-2 py-0.5 sm:px-3 sm:py-1 text-[10px] sm:text-xs rounded-lg bg-gray-700/60 hover:bg-gray-600 text-gray-400 hover:text-amber-400 border border-gray-600 transition-colors cursor-pointer">
          ❓
        </button>
      </div>
    </header>

    <HelpRules :visible="showHelp" @close="showHelp = false" />

    <!-- Chat -->
    <ChatPanel v-if="state.game?.id && state.player?.id"
               :gameId="state.game.id"
               :myId="state.player.id" />

    <!-- Nudge alert -->
    <Transition name="nudge-slide">
      <div v-if="showNudge"
           class="mx-3 sm:mx-4 mt-2 sm:mt-3 px-3 sm:px-4 py-2 sm:py-3 rounded-xl border-2 flex items-center justify-between gap-2 sm:gap-3 animate-pulse-slow"
           :class="nudgeUrgent
             ? 'bg-red-900/60 border-red-500 shadow-lg shadow-red-500/20'
             : 'bg-amber-900/40 border-amber-500/60 shadow-lg shadow-amber-500/10'">
        <div class="flex items-center gap-2 sm:gap-3">
          <span class="text-xl sm:text-2xl" :class="{ 'animate-bounce': nudgeUrgent }">{{ nudgeUrgent ? '🚨' : '⏰' }}</span>
          <div>
            <p class="text-xs sm:text-sm font-bold" :class="nudgeUrgent ? 'text-red-300' : 'text-amber-300'">
              {{ nudgeUrgent ? 'Você precisa jogar!' : 'É a sua vez!' }}
            </p>
            <p class="text-[10px] sm:text-xs" :class="nudgeUrgent ? 'text-red-400/70' : 'text-amber-400/60'">
              {{ nudgeMessage }}
            </p>
          </div>
        </div>
        <span class="text-[10px] sm:text-xs font-mono tabular-nums" :class="nudgeUrgent ? 'text-red-400' : 'text-amber-400/70'">
          {{ nudgeTimer }}s
        </span>
      </div>
    </Transition>

    <!-- MOBILE LAYOUT: My info + actions first, then opponents -->
    <!-- DESKTOP LAYOUT: Opponents left, my area right -->
    <div class="flex-1 flex flex-col lg:flex-row gap-3 sm:gap-4 p-3 sm:p-4">

      <!-- Opponents area (below on mobile, left on desktop) -->
      <div class="flex-1 order-2 lg:order-1">
        <!-- Collapsible on mobile -->
        <button @click="opponentsExpanded = !opponentsExpanded"
                class="lg:hidden w-full flex items-center justify-between px-3 py-2 mb-2 rounded-lg bg-gray-800/40 border border-gray-700 text-sm text-gray-400 cursor-pointer">
          <span>👥 Oponentes ({{ otherPlayers.length }})</span>
          <span class="text-xs">{{ opponentsExpanded ? '▲' : '▼' }}</span>
        </button>

        <div :class="{ 'hidden lg:block': !opponentsExpanded }">
          <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
            <PlayerCard v-for="player in otherPlayers" :key="player.id"
                        :player="player"
                        :isCurrentTurn="state.game.current_player_id === player.id"
                        :isTurnStateTarget="state.game.turn_state?.target_id === player.id"
                        :passStatus="getPlayerPassStatus(player)"
                        :chatMessage="getPlayerChatMessage(player)"
                        @select="selectTarget(player.id)" />
          </div>
        </div>

        <!-- Phase indicator -->
        <PhaseIndicator :phase="state.game.phase"
                        :turnState="state.game.turn_state"
                        :players="state.game.players"
                        class="mt-3 sm:mt-4" />

        <!-- Event log (collapsed by default on mobile) -->
        <div class="mt-3 sm:mt-4">
          <button @click="logExpanded = !logExpanded"
                  class="lg:hidden w-full flex items-center justify-between px-3 py-2 mb-1 rounded-lg bg-gray-800/40 border border-gray-700 text-xs text-gray-500 cursor-pointer">
            <span>📜 Histórico</span>
            <span>{{ logExpanded ? '▲' : '▼' }}</span>
          </button>
          <div :class="{ 'hidden lg:block': !logExpanded }">
            <EventLog :events="state.game.event_log" :players="state.game.players" />
          </div>
        </div>
      </div>

      <!-- My area (top on mobile, right on desktop) -->
      <div class="lg:w-80 space-y-3 sm:space-y-4 order-1 lg:order-2">
        <!-- My info -->
        <div class="bg-gray-800/60 backdrop-blur rounded-xl p-3 sm:p-4 border border-gray-700">
          <div class="flex items-center justify-between mb-2 sm:mb-3">
            <h3 class="font-bold text-amber-400 text-sm sm:text-base">{{ state.player?.name }}</h3>
            <div class="flex items-center gap-2">
              <div class="flex items-center gap-1">
                <CoinIcon class="w-4 h-4 sm:w-5 sm:h-5 text-amber-400" />
                <span class="text-base sm:text-lg font-bold text-amber-400">{{ state.player?.coins }}</span>
              </div>
              <button @click="cardsVisible = !cardsVisible"
                      class="px-2 py-1 rounded-lg text-[10px] sm:text-xs transition-all duration-200 border cursor-pointer"
                      :class="cardsVisible
                        ? 'bg-amber-400/20 border-amber-400/50 text-amber-300'
                        : 'bg-gray-700/60 border-gray-600 text-gray-400 hover:text-amber-300 hover:border-amber-400/50'">
                {{ cardsVisible ? '🙈' : '👁' }}
                <span class="hidden sm:inline">{{ cardsVisible ? ' Esconder' : ' Ver cartas' }}</span>
              </button>
            </div>
          </div>

          <!-- My cards -->
          <div class="flex gap-2">
            <CharacterCard v-for="(card, index) in myInfluences" :key="'inf-'+index"
                           :character="card"
                           :faceUp="cardsVisible || forceReveal"
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
          <div v-if="mustCoup && isMyTurn" class="mt-2 sm:mt-3 px-3 py-2 rounded-lg bg-red-900/40 border border-red-700 text-red-300 text-[10px] sm:text-xs text-center">
            ⚠ 10+ moedas: você DEVE dar Golpe de Estado
          </div>
        </div>

        <!-- Action buttons -->
        <ActionPanel v-if="isMyTurn && state.game.phase === 'action_selection'"
                     :coins="state.player?.coins"
                     :mustCoup="mustCoup"
                     :opponents="aliveOpponents"
                     @action="handleAction" />

        <!-- Reaction buttons -->
        <ReactionPanel v-if="showReactionPanel"
                       :phase="state.game.phase"
                       :turnState="state.game.turn_state"
                       :myId="state.player?.id"
                       :hasPassed="hasPassed"
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

    <!-- Animation overlay -->
    <AnimationOverlay :events="state.game?.event_log || []"
                      :players="state.game?.players || []" />
  </div>
</template>

<script setup>
import { computed, ref, reactive, watch, onMounted, onUnmounted } from 'vue';
import { useGame } from '../composables/useGame';
import { playYourTurn, playTurnEnd, playNudge, initAudio } from '../composables/useSound';
import PlayerCard from './PlayerCard.vue';
import CharacterCard from './CharacterCard.vue';
import ActionPanel from './ActionPanel.vue';
import ReactionPanel from './ReactionPanel.vue';
import InfluenceLossPanel from './InfluenceLossPanel.vue';
import ExchangePanel from './ExchangePanel.vue';
import PhaseIndicator from './PhaseIndicator.vue';
import EventLog from './EventLog.vue';
import CoinIcon from './icons/CoinIcon.vue';
import HelpRules from './HelpRules.vue';
import ChatPanel from './ChatPanel.vue';
import AnimationOverlay from './AnimationOverlay.vue';

const {
  state,
  isMyTurn,
  myInfluences,
  otherPlayers,
  aliveOpponents,
  mustCoup,
  hasPassed,
  declareAction,
  pass,
  challengeAction,
  declareBlock,
  challengeBlock,
  loseInfluence,
  exchangeCards,
  abandonGame,
} = useGame();

const cardsVisible = ref(false);
const showHelp = ref(false);
const opponentsExpanded = ref(false);
const logExpanded = ref(false);

// ── Audio init on first click ────────────────
onMounted(() => {
  const handler = () => {
    initAudio();
    document.removeEventListener('click', handler);
  };
  document.addEventListener('click', handler);
});

// ── Chat bubbles per player (8 ball pool style) ─────
const playerChatBubbles = reactive({}); // { playerId: 'message text' }
let chatBubbleTimers = {};

function onChatBubble(e) {
  const playerId = e.player_id;
  if (!playerId || playerId === state.player?.id) return; // Don't show own messages as bubbles

  // Set the message
  playerChatBubbles[playerId] = e.message;

  // Clear previous timer
  if (chatBubbleTimers[playerId]) {
    clearTimeout(chatBubbleTimers[playerId]);
  }

  // Auto-hide after 5 seconds
  chatBubbleTimers[playerId] = setTimeout(() => {
    delete playerChatBubbles[playerId];
  }, 5000);
}

function getPlayerChatMessage(player) {
  return playerChatBubbles[player.id] || null;
}

// Listen for chat messages for bubbles
onMounted(() => {
  if (window.Echo && state.game?.id) {
    const channelName = `game.${state.game.id}`;
    window.Echo.channel(channelName).listen('.chat.message', onChatBubble);
  }
});

onUnmounted(() => {
  // Clear all bubble timers
  Object.values(chatBubbleTimers).forEach(t => clearTimeout(t));
  chatBubbleTimers = {};
});

// ── Nudge / timer state ──────────────────────
const NUDGE_DELAY = 10;       // seconds before first nudge
const NUDGE_URGENT = 20;      // seconds before urgent nudge
const nudgeTimer = ref(0);
const showNudge = ref(false);
const nudgeUrgent = ref(false);
let nudgeInterval = null;
let lastNudgeSoundAt = 0;

function needsMyAction() {
  const phase = state.game?.phase;
  const ts = state.game?.turn_state;
  if (!phase || !ts || !state.player?.is_alive) return false;

  // My turn to select action
  if (phase === 'action_selection' && isMyTurn.value) return true;

  // I need to lose influence
  if (phase === 'awaiting_influence_loss' && ts.awaiting_influence_loss_from === state.player?.id) return true;

  // I need to exchange
  if (phase === 'awaiting_exchange_return' && ts.actor_id === state.player?.id) return true;

  // Reaction phases — I haven't passed yet
  if (['awaiting_challenge_action', 'awaiting_block', 'awaiting_challenge_block'].includes(phase)) {
    const passedIds = ts.passed_players || [];
    if (passedIds.includes(state.player?.id)) return false;

    if (phase === 'awaiting_challenge_action' && state.player?.id !== ts.actor_id) return true;
    if (phase === 'awaiting_block') {
      if (ts.action === 'foreign_aid' && state.player?.id !== ts.actor_id) return true;
      if (state.player?.id === ts.target_id) return true;
    }
    if (phase === 'awaiting_challenge_block' && state.player?.id !== ts.blocker_id) return true;
  }

  return false;
}

const nudgeMessage = computed(() => {
  const phase = state.game?.phase;
  if (phase === 'action_selection') return 'Escolha uma ação para jogar.';
  if (phase === 'awaiting_influence_loss') return 'Escolha uma influência para perder.';
  if (phase === 'awaiting_exchange_return') return 'Escolha as cartas para devolver.';
  if (phase === 'awaiting_challenge_action') return 'Decida: contestar ou passar.';
  if (phase === 'awaiting_block') return 'Decida: bloquear ou passar.';
  if (phase === 'awaiting_challenge_block') return 'Decida: contestar bloqueio ou passar.';
  return 'Faça sua jogada.';
});

function startNudgeTimer() {
  stopNudgeTimer();
  nudgeTimer.value = 0;
  showNudge.value = false;
  nudgeUrgent.value = false;

  nudgeInterval = setInterval(() => {
    if (!needsMyAction()) {
      stopNudgeTimer();
      return;
    }
    nudgeTimer.value++;

    if (nudgeTimer.value >= NUDGE_DELAY && !showNudge.value) {
      showNudge.value = true;
    }
    if (nudgeTimer.value >= NUDGE_URGENT) {
      nudgeUrgent.value = true;
    }

    // Play nudge sound every 10s after it becomes visible
    if (showNudge.value && (nudgeTimer.value - lastNudgeSoundAt) >= 10) {
      lastNudgeSoundAt = nudgeTimer.value;
      playNudge();
    }
  }, 1000);
}

function stopNudgeTimer() {
  if (nudgeInterval) {
    clearInterval(nudgeInterval);
    nudgeInterval = null;
  }
  nudgeTimer.value = 0;
  showNudge.value = false;
  nudgeUrgent.value = false;
  lastNudgeSoundAt = 0;
}

onUnmounted(() => stopNudgeTimer());

// ── Sound + nudge triggers on game state changes ──
let prevNeedsAction = false;
let prevPhase = null;

watch(() => [state.game?.phase, state.game?.turn_state, state.game?.current_player_id], () => {
  const currentPhase = state.game?.phase;
  const nowNeedsAction = needsMyAction();

  // My action started → play "your turn" sound
  if (nowNeedsAction && !prevNeedsAction) {
    playYourTurn();
    startNudgeTimer();
  }

  // My action ended (I acted) → play turn end
  if (!nowNeedsAction && prevNeedsAction) {
    playTurnEnd();
    stopNudgeTimer();
  }

  // Phase changed but I still need action → restart nudge timer
  if (nowNeedsAction && currentPhase !== prevPhase) {
    startNudgeTimer();
  }

  prevNeedsAction = nowNeedsAction;
  prevPhase = currentPhase;
}, { deep: true });

// ── Pass status per player ───────────────────
function getPlayerPassStatus(player) {
  const phase = state.game?.phase;
  const ts = state.game?.turn_state;
  if (!phase || !ts || !player.is_alive) return null;

  const isReactionPhase = ['awaiting_challenge_action', 'awaiting_block', 'awaiting_challenge_block'].includes(phase);
  if (!isReactionPhase) {
    // Not a reaction phase — show acting indicator for whose turn it is
    if (phase === 'action_selection' && state.game.current_player_id === player.id) return 'acting';
    if (phase === 'awaiting_influence_loss' && ts.awaiting_influence_loss_from === player.id) return 'acting';
    if (phase === 'awaiting_exchange_return' && ts.actor_id === player.id) return 'acting';
    return null;
  }

  // Check if this player can even react in this phase
  let canReact = false;
  if (phase === 'awaiting_challenge_action') canReact = player.id !== ts.actor_id;
  else if (phase === 'awaiting_block') {
    if (ts.action === 'foreign_aid') canReact = player.id !== ts.actor_id;
    else canReact = player.id === ts.target_id;
  }
  else if (phase === 'awaiting_challenge_block') canReact = player.id !== ts.blocker_id;

  if (!canReact) return null;

  const passedIds = ts.passed_players || [];
  return passedIds.includes(player.id) ? 'passed' : 'pending';
}

// Force reveal when the player needs to interact with their cards
const forceReveal = computed(() => {
  return isChoosingInfluenceLoss.value || isExchanging.value;
});

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
  if (!state.player?.is_alive) return false;

  // Can I react? (show panel even if already passed — ReactionPanel will show waiting state)
  if (phase === 'awaiting_challenge_action') {
    return state.player?.id !== ts.actor_id;
  }
  if (phase === 'awaiting_block') {
    const action = ts.action;
    if (action === 'foreign_aid') {
      return state.player?.id !== ts.actor_id;
    }
    return state.player?.id === ts.target_id;
  }
  if (phase === 'awaiting_challenge_block') {
    return state.player?.id !== ts.blocker_id;
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

<style scoped>
.nudge-slide-enter-active,
.nudge-slide-leave-active {
  transition: all 0.35s ease;
}
.nudge-slide-enter-from,
.nudge-slide-leave-to {
  opacity: 0;
  transform: translateY(-12px);
}

@keyframes pulse-slow {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.8; }
}
.animate-pulse-slow {
  animation: pulse-slow 2s ease-in-out infinite;
}
</style>
