<template>
  <div class="rounded-lg border p-2 transition-all duration-300 cursor-pointer relative overflow-hidden"
       :class="[
         dead ? 'opacity-40 grayscale cursor-default' : '',
         selectable ? 'hover:ring-2 hover:ring-amber-400 cursor-pointer' : '',
       ]"
       :style="cardStyle"
       @click="selectable && emit('select', character)">

    <!-- Card face -->
    <div v-if="faceUp" class="text-center">
      <CharacterSvg :character="character" class="w-8 h-8 mx-auto mb-1" />
      <p class="text-xs font-bold" :style="{ color: charData.color }">
        {{ charData.name }}
      </p>
    </div>

    <!-- Card back -->
    <div v-else class="text-center h-16 flex items-center justify-center">
      <span class="text-2xl opacity-40">🃏</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { CHARACTERS } from '../data/characters';
import CharacterSvg from './CharacterSvg.vue';

const props = defineProps({
  character: String,
  faceUp: { type: Boolean, default: false },
  dead: { type: Boolean, default: false },
  selectable: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);

const charData = computed(() => CHARACTERS[props.character] || { name: '?', color: '#666' });

const cardStyle = computed(() => {
  if (!props.faceUp) {
    // Card hidden: use neutral gray colors
    return {
      backgroundColor: '#374151',
      borderColor: '#4b5563',
    };
  }
  // Card revealed: use character colors
  return {
    backgroundColor: charData.value.color + (props.dead ? '22' : '33'),
    borderColor: charData.value.color + (props.dead ? '44' : '88'),
  };
});
</script>
