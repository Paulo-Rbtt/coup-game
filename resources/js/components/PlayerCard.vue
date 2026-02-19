<template>
  <div class="relative rounded-xl p-2 sm:p-3 border transition-all duration-300"
       :class="[
         isCurrentTurn ? 'bg-amber-400/10 border-amber-400/50 shadow-amber-400/20 shadow-lg' : 'bg-gray-800/40 border-gray-700',
         !player.is_alive ? 'opacity-40 grayscale' : '',
         isTurnStateTarget ? 'ring-2 ring-red-500/60' : '',
       ]">

    <!-- Chat bubble (8 ball pool style) -->
    <Transition name="bubble">
      <div v-if="chatMessage && player.is_alive"
           class="absolute -top-8 sm:-top-10 left-1/2 -translate-x-1/2 z-10 max-w-[150px] sm:max-w-[200px] px-2 sm:px-3 py-1 sm:py-1.5 rounded-xl bg-white text-gray-900 text-[10px] sm:text-xs font-medium shadow-lg whitespace-nowrap overflow-hidden text-ellipsis pointer-events-none"
           style="filter: drop-shadow(0 2px 8px rgba(0,0,0,0.3))">
        {{ chatMessage }}
        <!-- Triangle pointer -->
        <div class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-white rotate-45"></div>
      </div>
    </Transition>

    <div class="flex items-center justify-between mb-1 sm:mb-2">
      <div class="flex items-center gap-1 sm:gap-2 min-w-0">
        <div class="w-5 h-5 sm:w-7 sm:h-7 rounded-full flex items-center justify-center text-[10px] sm:text-xs font-bold shrink-0"
             :class="isCurrentTurn ? 'bg-amber-400 text-gray-900' : 'bg-gray-600 text-white'">
          {{ player.name[0].toUpperCase() }}
        </div>
        <span class="font-medium text-xs sm:text-sm truncate" :class="isCurrentTurn ? 'text-amber-400' : 'text-white'">
          {{ player.name }}
        </span>
        <span v-if="!player.is_alive" class="text-[10px] sm:text-xs text-red-400 shrink-0">💀</span>

        <!-- Pass / pending indicator (hidden on very small screens, shown on sm+) -->
        <span v-if="player.is_alive && passStatus === 'passed'"
              class="hidden sm:inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-green-500/20 text-green-400 border border-green-500/30">
          ✓ Passou
        </span>
        <span v-else-if="player.is_alive && passStatus === 'pending'"
              class="hidden sm:inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30 animate-pulse">
          ⏳
        </span>
        <span v-else-if="player.is_alive && passStatus === 'acting'"
              class="hidden sm:inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/20 text-blue-400 border border-blue-500/30 animate-pulse">
          🎯
        </span>
      </div>
      <div class="flex items-center gap-0.5 sm:gap-1 text-amber-400 shrink-0">
        <CoinIcon class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
        <span class="text-xs sm:text-sm font-bold">{{ player.coins }}</span>
        <!-- Mobile-only compact status dot -->
        <span v-if="player.is_alive && passStatus === 'passed'" class="sm:hidden w-2 h-2 rounded-full bg-green-400 ml-1"></span>
        <span v-else-if="player.is_alive && passStatus === 'pending'" class="sm:hidden w-2 h-2 rounded-full bg-amber-400 animate-pulse ml-1"></span>
        <span v-else-if="player.is_alive && passStatus === 'acting'" class="sm:hidden w-2 h-2 rounded-full bg-blue-400 animate-pulse ml-1"></span>
      </div>
    </div>

    <!-- Influence indicators -->
    <div class="flex gap-1 sm:gap-1.5">
      <!-- Hidden (alive) influences -->
      <div v-for="i in player.influence_count" :key="'h-'+i"
           class="flex-1 h-10 sm:h-16 rounded-lg bg-gradient-to-br from-indigo-800 to-purple-900 border border-indigo-600
                  flex items-center justify-center">
        <span class="text-lg sm:text-2xl opacity-60">?</span>
      </div>
      <!-- Revealed (dead) influences -->
      <div v-for="(card, idx) in player.revealed" :key="'r-'+idx"
           class="flex-1 h-10 sm:h-16 rounded-lg border flex items-center justify-center opacity-60"
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
  passStatus: { type: String, default: null },
  chatMessage: { type: String, default: null },
});

const emit = defineEmits(['select']);

function getColor(character) {
  return CHARACTERS[character]?.color || '#666';
}

function getName(character) {
  return CHARACTERS[character]?.name || character;
}
</script>

<style scoped>
.bubble-enter-active {
  transition: all 0.3s ease-out;
}
.bubble-leave-active {
  transition: all 0.5s ease-in;
}
.bubble-enter-from {
  opacity: 0;
  transform: translate(-50%, 8px) scale(0.8);
}
.bubble-leave-to {
  opacity: 0;
  transform: translate(-50%, -4px) scale(0.9);
}
</style>
