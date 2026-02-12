<template>
  <div class="bg-gray-800/60 backdrop-blur rounded-xl p-4 border border-green-500/40">
    <h3 class="text-sm font-bold text-green-400 mb-2">Trocar cartas (Embaixador)</h3>
    <p class="text-xs text-gray-400 mb-3">
      Selecione {{ keepCount }} carta(s) para manter. As demais voltam ao baralho.
    </p>

    <div class="grid grid-cols-2 gap-2 mb-3">
      <button v-for="(card, idx) in options" :key="idx"
              @click="toggleCard(idx)"
              class="py-3 rounded-lg border-2 transition-all duration-200"
              :class="selected.includes(idx) ? 'ring-2 ring-amber-400 scale-105' : 'opacity-60'"
              :style="{
                backgroundColor: getColor(card) + '33',
                borderColor: selected.includes(idx) ? '#fbbf24' : getColor(card) + '66',
              }">
        <CharacterSvg :character="card" class="w-8 h-8 mx-auto mb-1" />
        <p class="text-xs font-bold text-center" :style="{ color: getColor(card) }">
          {{ getName(card) }}
        </p>
      </button>
    </div>

    <button @click="confirm"
            :disabled="selected.length !== keepCount"
            class="w-full py-2 rounded-lg bg-green-600 hover:bg-green-500 text-white font-bold text-sm
                   transition disabled:opacity-40 disabled:cursor-not-allowed">
      Confirmar ({{ selected.length }}/{{ keepCount }})
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { CHARACTERS } from '../data/characters';
import CharacterSvg from './CharacterSvg.vue';

const props = defineProps({
  options: Array,
  keepCount: Number,
});

const emit = defineEmits(['confirm']);

const selected = ref([]);

function toggleCard(idx) {
  const i = selected.value.indexOf(idx);
  if (i >= 0) {
    selected.value.splice(i, 1);
  } else if (selected.value.length < props.keepCount) {
    selected.value.push(idx);
  }
}

function confirm() {
  const keepCards = selected.value.map(i => props.options[i]);
  emit('confirm', keepCards);
}

function getColor(character) {
  return CHARACTERS[character]?.color || '#666';
}
function getName(character) {
  return CHARACTERS[character]?.name || character;
}
</script>
