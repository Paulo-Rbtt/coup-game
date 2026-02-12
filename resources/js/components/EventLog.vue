<template>
  <div class="bg-gray-800/40 rounded-xl p-3 border border-gray-700 max-h-48 overflow-y-auto">
    <h4 class="text-xs font-bold text-gray-500 mb-2">Histórico</h4>
    <div class="space-y-1">
      <div v-for="(event, idx) in reversed" :key="idx"
           class="text-xs text-gray-400 flex gap-2 items-start"
           ref="eventRefs">
        <span class="text-[10px] text-gray-600 font-mono shrink-0">T{{ event.turn || '?' }}</span>
        <span>{{ formatEvent(event) }}</span>
      </div>
      <div v-if="!events?.length" class="text-xs text-gray-600 italic">
        Nenhum evento ainda.
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, watch, ref, nextTick } from 'vue';
import { animate } from 'animejs';
import { ACTIONS, CHARACTERS } from '../data/characters';

const props = defineProps({
  events: Array,
  players: Array,
});

const eventRefs = ref([]);

const reversed = computed(() => {
  return [...(props.events || [])].reverse();
});

watch(() => props.events?.length, async () => {
  await nextTick();
  if (eventRefs.value?.length) {
    const first = eventRefs.value[0];
    if (first) {
      animate(first, {
        translateX: [-20, 0],
        opacity: [0, 1],
        duration: 300,
        ease: 'outQuad',
      });
    }
  }
});

function pName(id) {
  return props.players?.find(p => p.id === id)?.name || '?';
}

function formatEvent(event) {
  switch (event.type) {
    case 'game_started':
      return `Partida iniciada com ${event.player_count} jogadores`;
    case 'action_declared': {
      const a = ACTIONS[event.action];
      let msg = `${event.actor_name} declara ${a?.label || event.action}`;
      if (event.target_name) msg += ` contra ${event.target_name}`;
      return msg;
    }
    case 'challenge_action':
      return `${event.challenger_name} contesta ${pName(event.actor_id)}`;
    case 'challenge_failed':
      return `${event.proven_by_name} prova ${CHARACTERS[event.character]?.name || event.character}! ${event.loser_name} perde influência`;
    case 'challenge_succeeded':
      return `${event.challenger_name} vence a contestação! ${event.actor_name} não tem a carta`;
    case 'block_declared':
      return `${event.blocker_name} bloqueia com ${event.block_character_label || CHARACTERS[event.block_character]?.name}`;
    case 'challenge_block':
      return `${event.challenger_name} contesta bloqueio de ${event.blocker_name}`;
    case 'challenge_block_failed':
      return `${event.proven_by_name} prova ${CHARACTERS[event.character]?.name}! Bloqueio se mantém`;
    case 'challenge_block_succeeded':
      return `${event.challenger_name} vence! ${event.blocker_name} blefou`;
    case 'block_succeeded':
      return `Bloqueio bem-sucedido! Ação falha`;
    case 'influence_lost':
      return `${event.player_name} perde ${CHARACTERS[event.character]?.name || event.character}`;
    case 'player_exiled':
      return `💀 ${event.player_name} foi exilado!`;
    case 'player_abandoned':
      return `🚪 ${event.player_name} saiu da partida!`;
    case 'action_resolved': {
      const ar = ACTIONS[event.action];
      let msg = `${event.actor_name}: ${ar?.label || event.action}`;
      if (event.coins) msg += ` (${event.coins > 0 ? '+' : ''}${event.coins} moedas)`;
      if (event.target_name) msg += ` → ${event.target_name}`;
      return msg;
    }
    case 'exchange_started':
      return `${event.actor_name} inicia troca de cartas`;
    case 'exchange_completed':
      return `${event.actor_name} completou a troca`;
    case 'turn_start':
      return `── Turno ${event.turn}: vez de ${event.player_name} ──`;
    case 'game_over':
      return `🏆 ${event.winner_name} venceu!`;
    default:
      return JSON.stringify(event);
  }
}
</script>
