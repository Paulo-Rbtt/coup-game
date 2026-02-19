<template>
  <div class="bg-gray-800/40 rounded-xl px-4 py-3 border border-gray-700">
    <div class="flex items-center gap-2 mb-1">
      <div class="w-2 h-2 rounded-full animate-pulse"
           :class="phaseColor"></div>
      <span class="text-sm font-bold" :class="phaseTextColor">
        {{ phaseLabel }}
      </span>
    </div>
    <p v-if="phaseDetail" class="text-xs text-gray-400">{{ phaseDetail }}</p>

    <!-- Pass progress bar (during reaction phases) -->
    <div v-if="showPassProgress" class="mt-2">
      <div class="flex items-center gap-2 mb-1.5">
        <span class="text-[10px] text-gray-500 font-medium">Decisões:</span>
        <span class="text-[10px] text-gray-500">
          {{ passedCount }}/{{ totalNeedPass }}
        </span>
      </div>
      <div class="flex gap-1 flex-wrap">
        <span v-for="p in reactionPlayers" :key="p.id"
              class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium border"
              :class="p.hasPassed
                ? 'bg-green-500/15 text-green-400 border-green-500/30'
                : 'bg-gray-700/40 text-gray-500 border-gray-600/40'">
          <span>{{ p.hasPassed ? '✓' : '⏳' }}</span>
          {{ p.name }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { PHASE_LABELS, ACTIONS, CHARACTERS } from '../data/characters';

const props = defineProps({
  phase: String,
  turnState: Object,
  players: Array,
});

function playerName(id) {
  return props.players?.find(p => p.id === id)?.name || '?';
}

const phaseLabel = computed(() => PHASE_LABELS[props.phase] || props.phase);

const phaseDetail = computed(() => {
  const ts = props.turnState;
  if (!ts) return '';

  const actorName = playerName(ts.actor_id);
  const actionData = ACTIONS[ts.action];
  const targetName = ts.target_id ? playerName(ts.target_id) : null;

  if (props.phase === 'action_selection') {
    return `Vez de ${actorName}`;
  }
  if (props.phase === 'awaiting_challenge_action') {
    let txt = `${actorName} declarou ${actionData?.label || ts.action}`;
    if (targetName) txt += ` contra ${targetName}`;
    return txt;
  }
  if (props.phase === 'awaiting_block') {
    return `${actionData?.label || ts.action} pode ser bloqueada`;
  }
  if (props.phase === 'awaiting_challenge_block') {
    const blockerName = playerName(ts.blocker_id);
    const blockChar = CHARACTERS[ts.block_character];
    return `${blockerName} bloqueia com ${blockChar?.name || ts.block_character}`;
  }
  if (props.phase === 'awaiting_influence_loss') {
    const loserName = playerName(ts.awaiting_influence_loss_from);
    return `${loserName} deve escolher uma influência para perder`;
  }
  if (props.phase === 'awaiting_exchange_return') {
    return `${actorName} está trocando cartas`;
  }
  return '';
});

// ── Pass progress for reaction phases ──────────
const isReactionPhase = computed(() => {
  return ['awaiting_challenge_action', 'awaiting_block', 'awaiting_challenge_block'].includes(props.phase);
});

const showPassProgress = computed(() => {
  return isReactionPhase.value && reactionPlayers.value.length > 0;
});

const reactionPlayers = computed(() => {
  if (!isReactionPhase.value || !props.players || !props.turnState) return [];

  const ts = props.turnState;
  const passedIds = ts.passed_players || [];

  // Determine which players can react (alive, not the actor/blocker)
  return props.players
    .filter(p => {
      if (!p.is_alive) return false;
      if (props.phase === 'awaiting_challenge_action') return p.id !== ts.actor_id;
      if (props.phase === 'awaiting_block') {
        if (ts.action === 'foreign_aid') return p.id !== ts.actor_id;
        return p.id === ts.target_id;
      }
      if (props.phase === 'awaiting_challenge_block') return p.id !== ts.blocker_id;
      return false;
    })
    .map(p => ({
      id: p.id,
      name: p.name,
      hasPassed: passedIds.includes(p.id),
    }));
});

const passedCount = computed(() => reactionPlayers.value.filter(p => p.hasPassed).length);
const totalNeedPass = computed(() => reactionPlayers.value.length);

const phaseColor = computed(() => {
  if (props.phase === 'awaiting_influence_loss') return 'bg-red-500';
  if (props.phase === 'awaiting_challenge_action' || props.phase === 'awaiting_challenge_block') return 'bg-amber-400';
  if (props.phase === 'awaiting_block') return 'bg-blue-400';
  if (props.phase === 'action_selection') return 'bg-green-400';
  return 'bg-gray-400';
});

const phaseTextColor = computed(() => {
  if (props.phase === 'awaiting_influence_loss') return 'text-red-400';
  if (props.phase === 'awaiting_challenge_action' || props.phase === 'awaiting_challenge_block') return 'text-amber-400';
  if (props.phase === 'awaiting_block') return 'text-blue-400';
  if (props.phase === 'action_selection') return 'text-green-400';
  return 'text-gray-400';
});
</script>
