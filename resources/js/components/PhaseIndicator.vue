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
