<template>
  <div class="bg-gray-800/60 backdrop-blur rounded-2xl p-6 shadow-xl border border-gray-700">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-bold text-amber-400">🏆 Ranking Global</h2>
      <button @click="emit('close')"
              class="px-3 py-1 text-xs rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-400 hover:text-white border border-gray-600 transition cursor-pointer">
        ✕ Fechar
      </button>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin w-8 h-8 border-3 border-amber-400 border-t-transparent rounded-full mx-auto"></div>
      <p class="text-xs text-gray-500 mt-2">Carregando ranking...</p>
    </div>

    <div v-else-if="rankings.length === 0" class="text-center py-8">
      <p class="text-gray-500 text-sm">Nenhuma partida finalizada ainda.</p>
      <p class="text-gray-600 text-xs mt-1">Complete partidas para aparecer no ranking!</p>
    </div>

    <div v-else class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-gray-500 text-xs border-b border-gray-700">
            <th class="pb-2 pr-2">#</th>
            <th class="pb-2 pr-2">Jogador</th>
            <th class="pb-2 pr-2 text-center">Vitórias</th>
            <th class="pb-2 pr-2 text-center">Partidas</th>
            <th class="pb-2 pr-2 text-center">% Vitórias</th>
            <th class="pb-2">Cartas Vitoriosas</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(rank, i) in rankings" :key="rank.player_name"
              class="border-b border-gray-700/50 hover:bg-gray-700/30 transition-colors">
            <td class="py-2 pr-2">
              <span v-if="i === 0" class="text-lg">🥇</span>
              <span v-else-if="i === 1" class="text-lg">🥈</span>
              <span v-else-if="i === 2" class="text-lg">🥉</span>
              <span v-else class="text-gray-500 text-xs font-mono pl-1">{{ i + 1 }}</span>
            </td>
            <td class="py-2 pr-2">
              <span class="font-bold" :class="i < 3 ? 'text-amber-400' : 'text-white'">
                {{ rank.player_name }}
              </span>
            </td>
            <td class="py-2 pr-2 text-center">
              <span class="font-bold text-green-400">{{ rank.wins }}</span>
            </td>
            <td class="py-2 pr-2 text-center text-gray-400">{{ rank.games_played }}</td>
            <td class="py-2 pr-2 text-center">
              <span class="px-2 py-0.5 rounded-full text-xs font-bold"
                    :class="rank.win_rate >= 50 ? 'bg-green-500/20 text-green-400' : 'bg-gray-600/40 text-gray-400'">
                {{ rank.win_rate }}%
              </span>
            </td>
            <td class="py-2">
              <div class="flex gap-1 flex-wrap">
                <span v-for="(count, card) in rank.winning_cards" :key="card"
                      class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                      :style="{ backgroundColor: getColor(card) + '22', color: getColor(card), borderColor: getColor(card) + '44' }"
                      style="border: 1px solid;">
                  {{ getName(card) }} ×{{ count }}
                </span>
                <span v-if="!Object.keys(rank.winning_cards || {}).length" class="text-gray-600 text-xs">-</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';
import { CHARACTERS } from '../data/characters';

const emit = defineEmits(['close']);

const rankings = ref([]);
const loading = ref(true);

function getColor(character) {
  return CHARACTERS[character]?.color || '#666';
}

function getName(character) {
  return CHARACTERS[character]?.name || character;
}

onMounted(async () => {
  try {
    const { data } = await api.get('/rankings');
    rankings.value = data;
  } catch (e) {
    console.error('Failed to load rankings:', e);
  } finally {
    loading.value = false;
  }
});
</script>
