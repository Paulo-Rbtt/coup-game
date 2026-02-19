<template>
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md text-center">
      <div class="text-6xl mb-4">🏆</div>
      <h1 class="text-4xl font-black text-amber-400 mb-2">Fim de Jogo!</h1>

      <div v-if="winner" class="bg-gray-800/60 backdrop-blur rounded-2xl p-6 border border-amber-400/40 mb-6">
        <p class="text-gray-400 text-sm mb-1">Vencedor</p>
        <p class="text-3xl font-black text-amber-400">{{ winner.name }}</p>
        <p v-if="isWinner" class="text-green-400 text-sm mt-2">Parabéns! Você venceu! 🎉</p>
      </div>

      <!-- Final standings -->
      <div class="bg-gray-800/40 rounded-xl p-4 border border-gray-700 mb-6">
        <h3 class="text-sm font-bold text-gray-500 mb-3">Resultado Final</h3>
        <div class="space-y-2">
          <div v-for="(player, i) in sortedPlayers" :key="player.id"
               class="flex items-center gap-3 px-3 py-2 rounded-lg"
               :class="player.is_alive ? 'bg-amber-400/10' : 'bg-gray-700/30 opacity-60'">
            <span class="text-lg">{{ i === 0 ? '🥇' : '💀' }}</span>
            <span class="font-medium" :class="player.is_alive ? 'text-amber-400' : 'text-gray-400'">
              {{ player.name }}
            </span>
            <span class="ml-auto text-xs text-gray-500">
              {{ player.coins }} moedas
            </span>
          </div>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <button @click="rematchGame"
                class="py-3 px-8 rounded-xl bg-amber-400 text-gray-900 font-bold text-lg
                       hover:bg-amber-300 transition">
          🔄 Revanche
        </button>
        <button @click="leaveGame"
                class="py-3 px-8 rounded-xl bg-gray-700 text-gray-300 font-bold text-lg
                       hover:bg-gray-600 border border-gray-600 transition">
          Sair
        </button>
      </div>
    </div>

    <!-- Chat -->
    <ChatPanel v-if="state.game?.id && state.player?.id"
               :gameId="state.game.id"
               :myId="state.player.id" />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useGame } from '../composables/useGame';
import ChatPanel from './ChatPanel.vue';

const { state, leaveGame, rematchGame } = useGame();

const winner = computed(() => {
  if (!state.game) return null;
  return state.game.players.find(p => p.id === state.game.winner_id);
});

const isWinner = computed(() => {
  return state.player?.id === state.game?.winner_id;
});

const sortedPlayers = computed(() => {
  if (!state.game?.players) return [];
  return [...state.game.players].sort((a, b) => {
    if (a.is_alive && !b.is_alive) return -1;
    if (!a.is_alive && b.is_alive) return 1;
    return 0;
  });
});
</script>
