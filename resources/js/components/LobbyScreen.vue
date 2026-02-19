<template>
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <h1 class="text-6xl font-black tracking-wider text-amber-400 drop-shadow-lg">COUP</h1>
        <p class="text-gray-400 mt-2 text-sm">Jogo de Blefe e Política</p>
        <div class="flex justify-center gap-2 mt-3">
          <button @click="showHelp = true"
                  class="px-4 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-amber-400 text-sm border border-gray-700 transition cursor-pointer">
            ❓ Como Jogar
          </button>
          <button @click="showRanking = true"
                  class="px-4 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-amber-400 text-sm border border-gray-700 transition cursor-pointer">
            🏆 Ranking
          </button>
          <button @click="showHistory = true"
                  class="px-4 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-amber-400 text-sm border border-gray-700 transition cursor-pointer">
            📜 Histórico
          </button>
        </div>
      </div>

      <HelpRules :visible="showHelp" @close="showHelp = false" />

      <!-- Ranking overlay -->
      <Transition name="fade">
        <div v-if="showRanking" class="fixed inset-0 z-40 bg-black/60 flex items-center justify-center p-4" @click.self="showRanking = false">
          <div class="max-w-2xl w-full max-h-[80vh] overflow-y-auto">
            <RankingScreen @close="showRanking = false" />
          </div>
        </div>
      </Transition>

      <!-- History overlay -->
      <Transition name="fade">
        <div v-if="showHistory" class="fixed inset-0 z-40 bg-black/60 flex items-center justify-center p-4" @click.self="showHistory = false">
          <div class="max-w-2xl w-full max-h-[80vh] overflow-y-auto">
            <HistoryScreen @close="showHistory = false" />
          </div>
        </div>
      </Transition>

      <!-- If already in a lobby, show room info -->
      <div v-if="state.game && state.game.phase === 'lobby'" class="bg-gray-800/60 backdrop-blur rounded-2xl p-6 shadow-xl border border-gray-700">
        <div class="text-center mb-6">
          <p class="text-gray-400 text-sm">Código da sala</p>
          <p class="text-4xl font-mono font-bold text-amber-400 tracking-[0.3em]">{{ state.game.code }}</p>
          <p class="text-xs text-gray-500 mt-1">Compartilhe este código com outros jogadores</p>
        </div>

        <!-- Player list -->
        <div class="space-y-2 mb-6">
          <div v-for="player in state.game.players" :key="player.id"
               class="flex items-center gap-3 px-4 py-2 rounded-lg"
               :class="player.id === state.player?.id ? 'bg-amber-400/10 border border-amber-400/30' : 'bg-gray-700/40'">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold"
                 :class="player.is_host ? 'bg-amber-400 text-gray-900' : 'bg-gray-600 text-white'">
              {{ player.name[0].toUpperCase() }}
            </div>
            <span class="font-medium">{{ player.name }}</span>
            <span v-if="player.is_host" class="text-xs text-amber-400">Anfitrião</span>
            <span v-if="player.id === state.player?.id" class="text-xs text-green-400">Você</span>
            <span class="ml-auto text-xs font-bold px-2 py-0.5 rounded-full"
                  :class="player.is_ready ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-gray-600/50 text-gray-500 border border-gray-600'">
              {{ player.is_ready ? '✓ Pronto' : '⏳ Aguardando' }}
            </span>
          </div>
        </div>

        <!-- Toggle ready button -->
        <button @click="toggleReady"
                class="w-full py-3 rounded-xl font-bold text-lg transition-all duration-200 mb-3 cursor-pointer"
                :class="state.player?.is_ready
                  ? 'bg-gray-600 text-gray-300 hover:bg-gray-500 border-2 border-gray-500'
                  : 'bg-green-600 text-white hover:bg-green-500 border-2 border-green-500'">
          {{ state.player?.is_ready ? '❌ Cancelar Pronto' : '✅ Estou Pronto!' }}
        </button>

        <!-- Start / Waiting -->
        <div v-if="isHost" class="space-y-3">
          <button @click="startGame"
                  :disabled="state.game.players.length < 2 || !allPlayersReady"
                  class="w-full py-3 rounded-xl font-bold text-lg transition-all duration-200
                         bg-amber-400 text-gray-900 hover:bg-amber-300 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
            Iniciar Partida ({{ state.game.players.length }} jogadores)
          </button>
          <p v-if="state.game.players.length < 2" class="text-center text-gray-500 text-xs">
            Aguardando mais jogadores...
          </p>
          <p v-else-if="!allPlayersReady" class="text-center text-gray-500 text-xs">
            Todos os jogadores precisam estar prontos.
          </p>
        </div>
        <div v-else class="text-center text-gray-400">
          <div class="flex items-center justify-center gap-2">
            <div class="animate-pulse w-2 h-2 bg-amber-400 rounded-full"></div>
            Aguardando o anfitrião iniciar...
          </div>
        </div>

        <button @click="leaveLobby" class="w-full mt-4 py-2 rounded-lg text-gray-500 hover:text-red-400 text-sm transition">
          Sair da sala
        </button>
      </div>

      <!-- Join / Create -->
      <div v-else class="space-y-4">
        <!-- Name input -->
        <div class="bg-gray-800/60 backdrop-blur rounded-2xl p-6 shadow-xl border border-gray-700">
          <label class="block text-sm text-gray-400 mb-2">Seu nome</label>
          <input v-model="playerName"
                 type="text"
                 maxlength="20"
                 placeholder="Nome do jogador"
                 class="w-full px-4 py-3 rounded-xl bg-gray-700/60 border border-gray-600 text-white
                        placeholder-gray-500 focus:border-amber-400 focus:outline-none transition"
                 @keydown.enter="handleCreate" />
        </div>

        <!-- Create room -->
        <button @click="handleCreate"
                :disabled="!playerName.trim()"
                class="w-full py-3 rounded-xl font-bold text-lg transition-all duration-200
                       bg-amber-400 text-gray-900 hover:bg-amber-300 disabled:opacity-50 disabled:cursor-not-allowed">
          Criar Sala
        </button>

        <!-- Divider -->
        <div class="flex items-center gap-4">
          <div class="flex-1 h-px bg-gray-700"></div>
          <span class="text-gray-500 text-sm">ou</span>
          <div class="flex-1 h-px bg-gray-700"></div>
        </div>

        <!-- Join room -->
        <div class="bg-gray-800/60 backdrop-blur rounded-2xl p-6 shadow-xl border border-gray-700">
          <label class="block text-sm text-gray-400 mb-2">Código da sala</label>
          <input v-model="roomCode"
                 type="text"
                 maxlength="6"
                 placeholder="ABC123"
                 class="w-full px-4 py-3 rounded-xl bg-gray-700/60 border border-gray-600 text-white
                        placeholder-gray-500 focus:border-amber-400 focus:outline-none transition
                        tracking-[0.3em] text-center font-mono text-xl uppercase"
                 @keydown.enter="handleJoin" />
          <button @click="handleJoin"
                  :disabled="!playerName.trim() || roomCode.length < 6"
                  class="w-full mt-3 py-3 rounded-xl font-bold transition-all duration-200
                         bg-gray-600 text-white hover:bg-gray-500 disabled:opacity-50 disabled:cursor-not-allowed">
            Entrar na Sala
          </button>
        </div>

        <!-- Open rooms list -->
        <div class="bg-gray-800/60 backdrop-blur rounded-2xl p-6 shadow-xl border border-gray-700">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-gray-300">🌐 Salas Disponíveis</h3>
            <button @click="fetchRooms" :disabled="loadingRooms"
                    class="text-xs text-gray-500 hover:text-amber-400 transition cursor-pointer">
              {{ loadingRooms ? '⏳' : '🔄' }} Atualizar
            </button>
          </div>

          <div v-if="loadingRooms && openRooms.length === 0" class="text-center text-gray-500 text-sm py-4">
            Carregando...
          </div>
          <div v-else-if="openRooms.length === 0" class="text-center text-gray-500 text-sm py-4">
            Nenhuma sala aberta no momento.
          </div>
          <div v-else class="space-y-2 max-h-64 overflow-y-auto">
            <div v-for="room in openRooms" :key="room.id"
                 class="flex items-center justify-between px-3 py-2 rounded-lg border transition"
                 :class="room.is_lobby ? 'bg-green-900/20 border-green-700/50 hover:border-green-500' : 'bg-gray-700/30 border-gray-600'">
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <span class="font-mono font-bold text-sm" :class="room.is_lobby ? 'text-green-400' : 'text-gray-400'">
                    {{ room.code }}
                  </span>
                  <span v-if="room.is_lobby"
                        class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-500/20 text-green-400 border border-green-500/30">
                    ABERTA
                  </span>
                  <span v-else
                        class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                    EM JOGO · {{ room.elapsed }}
                  </span>
                </div>
                <div class="text-[10px] text-gray-500 mt-0.5 truncate">
                  👥 {{ room.players.join(', ') }} ({{ room.player_count }}/{{ room.max_players }})
                </div>
              </div>
              <button v-if="room.is_lobby && room.player_count < room.max_players"
                      @click="joinRoom(room.code)"
                      class="ml-2 px-3 py-1.5 rounded-lg text-xs font-bold bg-green-600 hover:bg-green-500 text-white transition shrink-0 cursor-pointer">
                Entrar
              </button>
              <span v-else-if="room.is_lobby" class="ml-2 text-xs text-gray-500">Cheia</span>
              <span v-else class="ml-2 text-xs text-gray-500">Turno {{ room.turn_number }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useGame } from '../composables/useGame';
import api from '../api';
import HelpRules from './HelpRules.vue';
import RankingScreen from './RankingScreen.vue';
import HistoryScreen from './HistoryScreen.vue';

const { state, createGame, joinGame, startGame, toggleReady, leaveLobby, isHost } = useGame();

const playerName = ref('');
const roomCode = ref('');
const showHelp = ref(false);
const showRanking = ref(false);
const showHistory = ref(false);
const openRooms = ref([]);
const loadingRooms = ref(false);

const allPlayersReady = computed(() => {
  if (!state.game?.players) return false;
  return state.game.players.every(p => p.is_ready);
});

async function fetchRooms() {
  loadingRooms.value = true;
  try {
    const { data } = await api.get('/rooms');
    openRooms.value = data;
  } catch {
    openRooms.value = [];
  } finally {
    loadingRooms.value = false;
  }
}

onMounted(() => {
  if (!state.game) {
    fetchRooms();
  }
});

async function handleCreate() {
  if (!playerName.value.trim()) return;
  await createGame(playerName.value.trim());
}

async function handleJoin() {
  if (!playerName.value.trim() || roomCode.value.length < 6) return;
  await joinGame(roomCode.value, playerName.value.trim());
}

async function joinRoom(code) {
  if (!playerName.value.trim()) {
    state.error = 'Digite seu nome primeiro.';
    return;
  }
  await joinGame(code, playerName.value.trim());
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
