<template>
  <div class="rounded-xl p-3 border transition-all duration-300"
       :class="[
         isCurrentTurn ? 'bg-amber-400/10 border-amber-400/50 shadow-amber-400/20 shadow-lg' : 'bg-gray-800/40 border-gray-700',
         !player.is_alive ? 'opacity-40 grayscale' : '',
         isTurnStateTarget ? 'ring-2 ring-red-500/60' : '',
       ]">
    <div class="flex items-center justify-between mb-2">
      <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
             :class="isCurrentTurn ? 'bg-amber-400 text-gray-900' : 'bg-gray-600 text-white'">
          {{ player.name[0].toUpperCase() }}
        </div>
        <span class="font-medium text-sm" :class="isCurrentTurn ? 'text-amber-400' : 'text-white'">
          {{ player.name }}
        </span>
        <span v-if="!player.is_alive" class="text-xs text-red-400">Exilado</span>
      </div>
      <div class="flex items-center gap-1 text-amber-400">
        <CoinIcon class="w-4 h-4" />
        <span class="text-sm font-bold">{{ player.coins }}</span>
      </div>
    </div>

    <!-- Influence indicators -->
    <div class="flex gap-1.5">
      <!-- Hidden (alive) influences -->
      <div v-for="i in player.influence_count" :key="'h-'+i"
           class="flex-1 h-16 rounded-lg bg-gradient-to-br from-indigo-800 to-purple-900 border border-indigo-600
                  flex items-center justify-center">
        <span class="text-2xl opacity-60">?</span>
      </div>
      <!-- Revealed (dead) influences -->
      <div v-for="(card, idx) in player.revealed" :key="'r-'+idx"
           class="flex-1 h-16 rounded-lg border flex items-center justify-center opacity-60"
           :style="{ backgroundColor: getColor(card) + '33', borderColor: getColor(card) + '66' }">
        <span class="text-xs font-bold" :style="{ color: getColor(card) }">
          {{ getName(card) }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { CHARACTERS } from '../data/characters';
import CoinIcon from './icons/CoinIcon.vue';

const props = defineProps({
  player: Object,
  isCurrentTurn: Boolean,
  isTurnStateTarget: Boolean,
});

const emit = defineEmits(['select']);

function getColor(character) {
  return CHARACTERS[character]?.color || '#666';
}

function getName(character) {
  return CHARACTERS[character]?.name || character;
}
</script>
