<template>
  <div class="bg-gray-800/60 backdrop-blur rounded-2xl p-6 shadow-xl border border-gray-700">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-bold text-amber-400">📜 Histórico de Partidas</h2>
      <button @click="emit('close')"
              class="px-3 py-1 text-xs rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-400 hover:text-white border border-gray-600 transition cursor-pointer">
        ✕ Fechar
      </button>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin w-8 h-8 border-3 border-amber-400 border-t-transparent rounded-full mx-auto"></div>
      <p class="text-xs text-gray-500 mt-2">Carregando histórico...</p>
    </div>

    <div v-else-if="games.length === 0" class="text-center py-8">
      <p class="text-gray-500 text-sm">Nenhuma partida finalizada ainda.</p>
    </div>

    <div v-else class="space-y-3">
      <div v-for="game in games" :key="game.id"
           class="bg-gray-700/30 rounded-xl p-4 border border-gray-600/50 hover:border-amber-400/30 transition cursor-pointer"
           @click="toggleExpand(game.id)">

        <!-- Game header -->
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-3">
            <span class="font-mono text-xs text-gray-500 bg-gray-800 px-2 py-0.5 rounded">{{ game.code }}</span>
            <span class="text-xs text-gray-400">{{ game.total_turns }} turnos</span>
          </div>
          <span class="text-xs text-gray-500">{{ formatDate(game.finished_at) }}</span>
        </div>

        <!-- Players -->
        <div class="flex flex-wrap gap-2">
          <div v-for="player in game.players" :key="player.player_name"
               class="flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs"
               :class="player.is_winner ? 'bg-amber-400/10 border border-amber-400/30' : 'bg-gray-800/40'">
            <span v-if="player.is_winner">🏆</span>
            <span v-else>💀</span>
            <span :class="player.is_winner ? 'text-amber-400 font-bold' : 'text-gray-400'">
              {{ player.player_name }}
            </span>
            <!-- Show revealed cards -->
            <span v-for="(card, ci) in player.revealed" :key="ci"
                  class="text-[9px] px-1 py-0.5 rounded font-bold"
                  :style="{ backgroundColor: getColor(card) + '22', color: getColor(card) }">
              {{ getName(card) }}
            </span>
            <!-- Show winner's remaining cards -->
            <template v-if="player.is_winner && player.influences?.length">
              <span v-for="(card, ci) in player.influences" :key="'w-'+ci"
                    class="text-[9px] px-1 py-0.5 rounded font-bold border"
                    :style="{ backgroundColor: getColor(card) + '33', color: getColor(card), borderColor: getColor(card) + '66' }">
                {{ getName(card) }}
              </span>
            </template>
          </div>
        </div>

        <!-- Expanded details -->
        <Transition name="expand">
          <div v-if="expandedGameId === game.id && expandedDetails" class="mt-3 pt-3 border-t border-gray-600/50">
            <div v-if="loadingDetails" class="text-center py-3">
              <div class="animate-spin w-5 h-5 border-2 border-amber-400 border-t-transparent rounded-full mx-auto"></div>
            </div>
            <div v-else>
              <h4 class="text-xs font-bold text-gray-500 mb-2">Histórico de Eventos</h4>
              <div class="max-h-48 overflow-y-auto space-y-0.5 scrollbar-thin">
                <div v-for="(event, idx) in reversedDetails" :key="idx"
                     class="text-[11px] text-gray-400 flex gap-2 items-start py-0.5">
                  <span class="text-[9px] text-gray-600 font-mono shrink-0">T{{ event.turn || '?' }}</span>
                  <span>{{ formatEvent(event) }}</span>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-center gap-2 mt-4">
        <button @click="loadPage(currentPage - 1)" :disabled="currentPage <= 1"
                class="px-3 py-1 text-xs rounded-lg bg-gray-700 text-gray-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition cursor-pointer">
          ← Anterior
        </button>
        <span class="text-xs text-gray-500">{{ currentPage }} / {{ totalPages }}</span>
        <button @click="loadPage(currentPage + 1)" :disabled="currentPage >= totalPages"
                class="px-3 py-1 text-xs rounded-lg bg-gray-700 text-gray-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition cursor-pointer">
          Próximo →
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api';
import { CHARACTERS, ACTIONS } from '../data/characters';

const emit = defineEmits(['close']);

const games = ref([]);
const loading = ref(true);
const currentPage = ref(1);
const totalPages = ref(1);
const expandedGameId = ref(null);
const expandedDetails = ref(null);
const loadingDetails = ref(false);

function getColor(character) {
  return CHARACTERS[character]?.color || '#666';
}

function getName(character) {
  return CHARACTERS[character]?.name || character;
}

function formatDate(isoStr) {
  try {
    return new Date(isoStr).toLocaleString('pt-BR', {
      day: '2-digit', month: '2-digit', year: '2-digit',
      hour: '2-digit', minute: '2-digit'
    });
  } catch {
    return isoStr;
  }
}

const reversedDetails = computed(() => {
  if (!expandedDetails.value?.event_log) return [];
  return [...expandedDetails.value.event_log].reverse();
});

async function loadPage(page) {
  if (page < 1 || page > totalPages.value) return;
  loading.value = true;
  try {
    const { data } = await api.get('/history', { params: { page, per_page: 10 } });
    games.value = data.data;
    currentPage.value = data.current_page;
    totalPages.value = data.last_page;
  } catch (e) {
    console.error('Failed to load history:', e);
  } finally {
    loading.value = false;
  }
}

async function toggleExpand(gameId) {
  if (expandedGameId.value === gameId) {
    expandedGameId.value = null;
    expandedDetails.value = null;
    return;
  }

  expandedGameId.value = gameId;
  expandedDetails.value = null;
  loadingDetails.value = true;

  try {
    const { data } = await api.get(`/games/${gameId}/results`);
    expandedDetails.value = data;
  } catch (e) {
    console.error('Failed to load game details:', e);
  } finally {
    loadingDetails.value = false;
  }
}

function formatEvent(event) {
  switch (event.type) {
    case 'game_started':
      return `Partida iniciada com ${event.player_count} jogadores`;
    case 'action_declared': {
      const a = ACTIONS[event.action];
      let msg = `${event.actor_name} declara ${a?.label || event.action}`;
      if (event.target_name) msg += ` contra ${event.target_name}`;
      return msg;
    }
    case 'challenge_action':
      return `${event.challenger_name} contesta ${event.actor_name}`;
    case 'challenge_failed':
      return `${event.proven_by_name} prova ${CHARACTERS[event.character]?.name || event.character}! ${event.loser_name} perde influência`;
    case 'challenge_succeeded':
      return `${event.challenger_name} vence a contestação! ${event.actor_name} não tem a carta`;
    case 'block_declared':
      return `${event.blocker_name} bloqueia com ${event.block_character_label || CHARACTERS[event.block_character]?.name}`;
    case 'influence_lost':
      return `${event.player_name} perde ${CHARACTERS[event.character]?.name || event.character}`;
    case 'player_exiled':
      return `💀 ${event.player_name} foi exilado!`;
    case 'player_abandoned':
      return `🚪 ${event.player_name} saiu da partida!`;
    case 'action_resolved': {
      const ar = ACTIONS[event.action];
      let msg = `${event.actor_name}: ${ar?.label || event.action}`;
      if (event.coins) msg += ` (${event.coins > 0 ? '+' : ''}${event.coins} moedas)`;
      return msg;
    }
    case 'turn_start':
      return `── Turno ${event.turn}: vez de ${event.player_name} ──`;
    case 'game_over':
      return `🏆 ${event.winner_name} venceu!`;
    default:
      return event.type || '...';
  }
}

onMounted(() => loadPage(1));
</script>

<style scoped>
.expand-enter-active,
.expand-leave-active {
  transition: all 0.3s ease;
  overflow: hidden;
}
.expand-enter-from,
.expand-leave-to {
  max-height: 0;
  opacity: 0;
}
.expand-enter-to {
  max-height: 300px;
  opacity: 1;
}

.scrollbar-thin::-webkit-scrollbar {
  width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 2px;
}
</style>
