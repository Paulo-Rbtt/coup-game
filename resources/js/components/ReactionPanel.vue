<template>
  <div class="bg-gray-800/60 backdrop-blur rounded-xl p-4 border border-amber-400/30">
    <h3 class="text-sm font-bold text-amber-400 mb-3">
      {{ phaseTitle }}
    </h3>

    <p class="text-xs text-gray-400 mb-3">{{ phaseDescription }}</p>

    <div class="flex flex-wrap gap-2">
      <!-- Challenge action -->
      <template v-if="phase === 'awaiting_challenge_action'">
        <button @click="emit('challenge')"
                class="flex-1 py-2 px-4 rounded-lg bg-red-600 hover:bg-red-500 text-white text-sm font-bold transition">
          🔍 Contestar
        </button>
        <button @click="emit('pass')"
                class="flex-1 py-2 px-4 rounded-lg bg-gray-600 hover:bg-gray-500 text-white text-sm transition">
          Passar
        </button>
      </template>

      <!-- Block action -->
      <template v-if="phase === 'awaiting_block'">
        <template v-for="char in blockableCharacters" :key="char.value">
          <button @click="emit('block', char.value)"
                  class="flex-1 py-2 px-4 rounded-lg text-white text-sm font-bold transition"
                  :style="{ backgroundColor: char.btnColor }">
            🛡️ Bloquear ({{ char.name }})
          </button>
        </template>
        <button @click="emit('pass')"
                class="flex-1 py-2 px-4 rounded-lg bg-gray-600 hover:bg-gray-500 text-white text-sm transition">
          Passar
        </button>
      </template>

      <!-- Challenge block -->
      <template v-if="phase === 'awaiting_challenge_block'">
        <button @click="emit('challenge-block')"
                class="flex-1 py-2 px-4 rounded-lg bg-red-600 hover:bg-red-500 text-white text-sm font-bold transition">
          🔍 Contestar Bloqueio
        </button>
        <button @click="emit('pass')"
                class="flex-1 py-2 px-4 rounded-lg bg-gray-600 hover:bg-gray-500 text-white text-sm transition">
          Passar
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { CHARACTERS, ACTIONS } from '../data/characters';

const props = defineProps({
  phase: String,
  turnState: Object,
  myId: Number,
});

const emit = defineEmits(['pass', 'challenge', 'block', 'challenge-block']);

const phaseTitle = computed(() => {
  if (props.phase === 'awaiting_challenge_action') return 'Contestar ação?';
  if (props.phase === 'awaiting_block') return 'Bloquear ação?';
  if (props.phase === 'awaiting_challenge_block') return 'Contestar bloqueio?';
  return '';
});

const phaseDescription = computed(() => {
  const ts = props.turnState;
  if (!ts) return '';
  const actionData = ACTIONS[ts.action];
  if (props.phase === 'awaiting_challenge_action') {
    return `Jogador declarou ${actionData?.label || ts.action}. Deseja contestar?`;
  }
  if (props.phase === 'awaiting_block') {
    return `A ação ${actionData?.label || ts.action} pode ser bloqueada.`;
  }
  if (props.phase === 'awaiting_challenge_block') {
    const blockChar = CHARACTERS[ts.block_character];
    return `Bloqueio declarado com ${blockChar?.name || ts.block_character}. Contestar?`;
  }
  return '';
});

const blockableCharacters = computed(() => {
  const ts = props.turnState;
  if (!ts) return [];

  const action = ts.action;
  const map = {
    foreign_aid: [{ value: 'duke', name: 'Duque', btnColor: '#7c3aed' }],
    assassinate: [{ value: 'contessa', name: 'Condessa', btnColor: '#dc2626' }],
    steal: [
      { value: 'ambassador', name: 'Embaixador', btnColor: '#16a34a' },
      { value: 'captain', name: 'Capitão', btnColor: '#2563eb' },
    ],
  };
  return map[action] || [];
});
</script>
