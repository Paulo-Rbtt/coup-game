<template>
  <div class="bg-gray-800/60 backdrop-blur rounded-xl p-4 border border-red-500/40">
    <h3 class="text-sm font-bold text-red-400 mb-3">Você deve perder uma influência</h3>
    <p class="text-xs text-gray-400 mb-3">Escolha qual carta revelar:</p>
    <div class="flex gap-2">
      <button v-for="(card, idx) in influences" :key="idx"
              @click="emit('choose', card)"
              class="flex-1 py-3 rounded-lg border-2 transition-all duration-200 hover:scale-105"
              :style="{
                backgroundColor: getColor(card) + '33',
                borderColor: getColor(card) + '88',
              }">
        <CharacterSvg :character="card" class="w-8 h-8 mx-auto mb-1" />
        <p class="text-xs font-bold text-center" :style="{ color: getColor(card) }">
          {{ getName(card) }}
        </p>
      </button>
    </div>
  </div>
</template>

<script setup>
import { CHARACTERS } from '../data/characters';
import CharacterSvg from './CharacterSvg.vue';

defineProps({
  influences: Array,
});

const emit = defineEmits(['choose']);

function getColor(character) {
  return CHARACTERS[character]?.color || '#666';
}
function getName(character) {
  return CHARACTERS[character]?.name || character;
}
</script>
