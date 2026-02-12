<template>
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <h1 class="text-6xl font-black tracking-wider text-amber-400 drop-shadow-lg">COUP</h1>
        <p class="text-gray-400 mt-2 text-sm">Jogo de Blefe e Política</p>
      </div>

      <!-- Host network banner -->
      <div v-if="isHostMode && state.hostInfo?.primary_ip" class="mb-4 bg-emerald-900/30 border border-emerald-700/50 rounded-xl px-4 py-3 text-center">
        <p class="text-xs text-emerald-400 mb-1">Seu IP para outros jogadores:</p>
        <p class="text-xl font-mono font-bold text-emerald-300 tracking-wider">{{ state.hostInfo.primary_ip }}</p>
        <p class="text-xs text-gray-500 mt-1">Porta: {{ state.hostInfo.server_port || 8000 }}</p>
      </div>

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
            <span v-if="player.is_host" class="text-xs text-amber-400 ml-auto">Anfitrião</span>
            <span v-if="player.id === state.player?.id && !player.is_host" class="text-xs text-green-400 ml-auto">Você</span>
          </div>
        </div>

        <!-- Start / Waiting -->
        <div v-if="isHost" class="space-y-3">
          <button @click="startGame"
                  :disabled="state.game.players.length < 2"
                  class="w-full py-3 rounded-xl font-bold text-lg transition-all duration-200
                         bg-amber-400 text-gray-900 hover:bg-amber-300 disabled:opacity-50 disabled:cursor-not-allowed">
            Iniciar Partida ({{ state.game.players.length }} jogadores)
          </button>
          <p v-if="state.game.players.length < 2" class="text-center text-gray-500 text-xs">
            Aguardando mais jogadores...
          </p>
        </div>
        <div v-else class="text-center text-gray-400">
          <div class="flex items-center justify-center gap-2">
            <div class="animate-pulse w-2 h-2 bg-amber-400 rounded-full"></div>
            Aguardando o anfitrião iniciar...
          </div>
        </div>

        <button @click="leaveGame" class="w-full mt-4 py-2 rounded-lg text-gray-500 hover:text-red-400 text-sm transition">
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

        <!-- Disconnect button -->
        <button @click="disconnect"
                class="w-full py-2 text-sm text-gray-500 hover:text-red-400 transition">
          ← Voltar à tela de conexão
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useGame } from '../composables/useGame';

const { state, createGame, joinGame, startGame, leaveGame, disconnect, isHost, isHostMode } = useGame();

const playerName = ref('');
const roomCode = ref('');

async function handleCreate() {
  if (!playerName.value.trim()) return;
  await createGame(playerName.value.trim());
}

async function handleJoin() {
  if (!playerName.value.trim() || roomCode.value.length < 6) return;
  await joinGame(roomCode.value, playerName.value.trim());
}
</script>
