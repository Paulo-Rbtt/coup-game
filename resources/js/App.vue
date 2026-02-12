<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-950 via-gray-900 to-gray-950 text-white">
    <!-- Loading overlay -->
    <div v-if="state.loading" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center">
      <div class="animate-spin w-12 h-12 border-4 border-amber-400 border-t-transparent rounded-full"></div>
    </div>

    <!-- Error toast -->
    <Transition name="slide">
      <div v-if="state.error"
           class="fixed top-4 right-4 z-50 bg-red-600 text-white px-6 py-3 rounded-lg shadow-xl cursor-pointer max-w-md"
           @click="state.error = null">
        {{ state.error }}
      </div>
    </Transition>

    <!-- Screens -->
    <LobbyScreen v-if="!state.game || state.game.phase === 'lobby'" />
    <GameBoard v-else-if="state.game.phase !== 'game_over'" />
    <GameOverScreen v-else />
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useGame } from './composables/useGame';
import LobbyScreen from './components/LobbyScreen.vue';
import GameBoard from './components/GameBoard.vue';
import GameOverScreen from './components/GameOverScreen.vue';

const { state, reconnect } = useGame();

onMounted(async () => {
  await reconnect();
});
</script>

<style>
.slide-enter-active,
.slide-leave-active {
  transition: all 0.3s ease;
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
  opacity: 0;
}
</style>
