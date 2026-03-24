<template>
  <div class="bg-gray-800/60 backdrop-blur rounded-xl p-4 border border-amber-400/30">
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-sm font-bold text-amber-400">
        {{ hasPassed ? 'Aguardando...' : phaseTitle }}
      </h3>
      <div v-if="!hasPassed && countdown > 0" class="flex items-center gap-1.5">
        <div
          class="w-7 h-7 rounded-full border-2 flex items-center justify-center text-xs font-bold tabular-nums"
          :class="countdown <= 10
            ? 'border-red-500 text-red-400 animate-pulse'
            : countdown <= 20
              ? 'border-amber-500 text-amber-400'
              : 'border-gray-500 text-gray-400'"
        >
          {{ countdown }}
        </div>
      </div>
    </div>

    <div v-if="hasPassed" class="text-center py-3">
      <div class="flex items-center justify-center gap-2 text-gray-400 text-sm mb-2">
        <div class="flex gap-1">
          <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
          <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
          <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
        </div>
        Aguardando outros jogadores...
      </div>
      <p class="text-xs text-gray-500">Você já passou. Esperando decisão dos demais.</p>
    </div>

    <template v-else>
      <p class="text-xs text-gray-400 mb-3">{{ phaseDescription }}</p>

      <div class="flex flex-wrap gap-2">
        <template v-if="phase === 'awaiting_challenge_action'">
          <button
            @click="emit('challenge')"
            class="flex-1 py-2 px-4 rounded-lg bg-red-600 hover:bg-red-500 text-white text-sm font-bold transition"
          >
            Contestar
          </button>

          <template v-for="char in availableBlockCharacters" :key="char.value">
            <button
              @click="emit('block', char.value)"
              class="flex-1 py-2 px-4 rounded-lg text-white text-sm font-bold transition"
              :style="{ backgroundColor: char.btnColor }"
            >
              Bloquear ({{ char.name }})
            </button>
          </template>

          <button
            @click="emit('pass')"
            class="flex-1 py-2 px-4 rounded-lg bg-gray-600 hover:bg-gray-500 text-white text-sm transition"
          >
            Passar
          </button>
        </template>

        <template v-else-if="phase === 'awaiting_block'">
          <template v-for="char in availableBlockCharacters" :key="char.value">
            <button
              @click="emit('block', char.value)"
              class="flex-1 py-2 px-4 rounded-lg text-white text-sm font-bold transition"
              :style="{ backgroundColor: char.btnColor }"
            >
              Bloquear ({{ char.name }})
            </button>
          </template>

          <button
            @click="emit('pass')"
            class="flex-1 py-2 px-4 rounded-lg bg-gray-600 hover:bg-gray-500 text-white text-sm transition"
          >
            Passar
          </button>
        </template>

        <template v-else-if="phase === 'awaiting_challenge_block'">
          <button
            @click="emit('challenge-block')"
            class="flex-1 py-2 px-4 rounded-lg bg-red-600 hover:bg-red-500 text-white text-sm font-bold transition"
          >
            Contestar Bloqueio
          </button>
          <button
            @click="emit('pass')"
            class="flex-1 py-2 px-4 rounded-lg bg-gray-600 hover:bg-gray-500 text-white text-sm transition"
          >
            Passar
          </button>
        </template>
      </div>

      <div v-if="countdown > 0" class="mt-3">
        <div class="h-1 rounded-full bg-gray-700 overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-1000 ease-linear"
            :class="countdown <= 10 ? 'bg-red-500' : countdown <= 20 ? 'bg-amber-500' : 'bg-gray-500'"
            :style="{ width: `${(countdown / AUTO_PASS_SECONDS) * 100}%` }"
          ></div>
        </div>
        <p class="text-[10px] text-gray-600 mt-1 text-center">Auto-pass em {{ countdown }}s</p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { ACTIONS, CHARACTERS } from '../data/characters';

const AUTO_PASS_SECONDS = 30;

const props = defineProps({
  phase: String,
  turnState: Object,
  myId: Number,
  hasPassed: { type: Boolean, default: false },
});

const emit = defineEmits(['pass', 'challenge', 'block', 'challenge-block']);

const countdown = ref(0);
let countdownInterval = null;

function startCountdown() {
  stopCountdown();
  countdown.value = AUTO_PASS_SECONDS;
  countdownInterval = setInterval(() => {
    countdown.value--;
    if (countdown.value <= 0) {
      stopCountdown();
      emit('pass');
    }
  }, 1000);
}

function stopCountdown() {
  if (countdownInterval) {
    clearInterval(countdownInterval);
    countdownInterval = null;
  }
  countdown.value = 0;
}

watch(
  () => [props.phase, props.hasPassed, props.turnState?.passed_players],
  () => {
    const isReactionPhase = ['awaiting_challenge_action', 'awaiting_block', 'awaiting_challenge_block'].includes(props.phase);
    if (isReactionPhase && !props.hasPassed) {
      if (!countdownInterval) {
        startCountdown();
      }
    } else {
      stopCountdown();
    }
  },
  { immediate: true }
);

watch(() => props.phase, () => {
  const isReactionPhase = ['awaiting_challenge_action', 'awaiting_block', 'awaiting_challenge_block'].includes(props.phase);
  if (isReactionPhase && !props.hasPassed) {
    startCountdown();
  }
});

onUnmounted(() => stopCountdown());

const canBlockCurrentAction = computed(() => {
  const ts = props.turnState;
  if (!ts) return false;
  if (!['awaiting_challenge_action', 'awaiting_block'].includes(props.phase)) return false;

  if (ts.action === 'foreign_aid') {
    return props.myId !== ts.actor_id;
  }

  return props.myId === ts.target_id;
});

const availableBlockCharacters = computed(() => {
  if (!canBlockCurrentAction.value) return [];

  const action = props.turnState?.action;
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

const phaseTitle = computed(() => {
  if (props.phase === 'awaiting_challenge_action') {
    return availableBlockCharacters.value.length > 0
      ? 'Contestar, bloquear ou passar?'
      : 'Contestar ou passar?';
  }
  if (props.phase === 'awaiting_block') return 'Bloquear ação?';
  if (props.phase === 'awaiting_challenge_block') return 'Contestar bloqueio?';
  return '';
});

const phaseDescription = computed(() => {
  const ts = props.turnState;
  if (!ts) return '';

  const actionData = ACTIONS[ts.action];

  if (props.phase === 'awaiting_challenge_action') {
    return availableBlockCharacters.value.length > 0
      ? `Jogador declarou ${actionData?.label || ts.action}. Você pode contestar, bloquear ou passar.`
      : `Jogador declarou ${actionData?.label || ts.action}. Deseja contestar ou passar?`;
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
</script>
