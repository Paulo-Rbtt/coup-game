<template>
  <div class="bg-gray-800/40 rounded-xl px-4 py-3 border border-gray-700">
    <div class="flex items-center gap-2 mb-1">
      <div class="w-2 h-2 rounded-full animate-pulse" :class="phaseColor"></div>
      <span class="text-sm font-bold" :class="phaseTextColor">
        {{ phaseLabel }}
      </span>
    </div>
    <p v-if="phaseDetail" class="text-xs text-gray-400">{{ phaseDetail }}</p>

    <div v-if="showPassProgress" class="mt-2">
      <div class="flex items-center gap-2 mb-1.5">
        <span class="text-[10px] text-gray-500 font-medium">Decisões:</span>
        <span class="text-[10px] text-gray-500">
          {{ passedCount }}/{{ totalNeedPass }}
        </span>
      </div>
      <div class="flex gap-1 flex-wrap">
        <span
          v-for="p in reactionPlayers"
          :key="p.id"
          class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium border"
          :class="p.hasPassed
            ? 'bg-green-500/15 text-green-400 border-green-500/30'
            : 'bg-gray-700/40 text-gray-500 border-gray-600/40'"
        >
          <span>{{ p.hasPassed ? '✓' : '⏳' }}</span>
          {{ p.name }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { ACTIONS, CHARACTERS, PHASE_LABELS } from '../data/characters';

const props = defineProps({
  phase: String,
  turnState: Object,
  players: Array,
});

function playerName(id) {
  return props.players?.find((player) => player.id === id)?.name || '?';
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
    let text = `${actorName} declarou ${actionData?.label || ts.action}`;
    if (targetName) {
      text += ` contra ${targetName}`;
    }

    if (['assassinate', 'steal'].includes(ts.action)) {
      return `${text}. Reações: contestar, bloquear ou passar.`;
    }

    return `${text}. Reações: contestar ou passar.`;
  }

  if (props.phase === 'awaiting_block') {
    return `${actionData?.label || ts.action} pode ser bloqueada.`;
  }

  if (props.phase === 'awaiting_challenge_block') {
    const blockerName = playerName(ts.blocker_id);
    const blockChar = CHARACTERS[ts.block_character];
    return `${blockerName} bloqueia com ${blockChar?.name || ts.block_character}.`;
  }

  if (props.phase === 'awaiting_influence_loss') {
    const loserName = playerName(ts.awaiting_influence_loss_from);
    return `${loserName} deve escolher uma influência para perder.`;
  }

  if (props.phase === 'awaiting_exchange_return') {
    return `${actorName} está trocando cartas.`;
  }

  return '';
});

const isReactionPhase = computed(() => {
  return ['awaiting_challenge_action', 'awaiting_block', 'awaiting_challenge_block'].includes(props.phase);
});

const reactionPlayers = computed(() => {
  if (!isReactionPhase.value || !props.players || !props.turnState) return [];

  const ts = props.turnState;
  const passedIds = ts.passed_players || [];

  return props.players
    .filter((player) => {
      if (!player.is_alive) return false;
      if (props.phase === 'awaiting_challenge_action') return player.id !== ts.actor_id;
      if (props.phase === 'awaiting_block') {
        if (ts.action === 'foreign_aid') return player.id !== ts.actor_id;
        return player.id === ts.target_id;
      }
      if (props.phase === 'awaiting_challenge_block') return player.id !== ts.blocker_id;
      return false;
    })
    .map((player) => ({
      id: player.id,
      name: player.name,
      hasPassed: passedIds.includes(player.id),
    }));
});

const showPassProgress = computed(() => {
  return isReactionPhase.value && reactionPlayers.value.length > 0;
});

const passedCount = computed(() => reactionPlayers.value.filter((player) => player.hasPassed).length);
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
