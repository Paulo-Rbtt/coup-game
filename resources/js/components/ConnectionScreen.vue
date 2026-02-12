<template>
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <h1 class="text-6xl font-black tracking-wider text-amber-400 drop-shadow-lg">COUP</h1>
        <p class="text-gray-400 mt-2 text-sm">Jogo de Blefe e Política</p>
        <p class="text-gray-500 mt-1 text-xs">Multiplayer LAN</p>
      </div>

      <!-- Connection options -->
      <div class="space-y-4">
        <!-- Host Game -->
        <button @click="handleHost"
                :disabled="state.loading"
                class="w-full py-4 rounded-2xl font-bold text-lg transition-all duration-200
                       bg-amber-400 text-gray-900 hover:bg-amber-300 disabled:opacity-50
                       flex flex-col items-center gap-1">
          <span class="text-xl">📡 Hospedar Jogo</span>
          <span class="text-xs font-normal opacity-70">Seu dispositivo será o servidor</span>
        </button>

        <!-- Divider -->
        <div class="flex items-center gap-4">
          <div class="flex-1 h-px bg-gray-700"></div>
          <span class="text-gray-500 text-sm">ou</span>
          <div class="flex-1 h-px bg-gray-700"></div>
        </div>

        <!-- Join via LAN -->
        <div class="bg-gray-800/60 backdrop-blur rounded-2xl p-6 shadow-xl border border-gray-700">
          <h3 class="text-sm text-gray-400 mb-3 font-medium">Entrar via LAN</h3>

          <div class="space-y-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">IP do Host</label>
              <input v-model="hostIp"
                     type="text"
                     placeholder="192.168.x.x"
                     class="w-full px-4 py-3 rounded-xl bg-gray-700/60 border border-gray-600 text-white
                            placeholder-gray-500 focus:border-amber-400 focus:outline-none transition
                            font-mono text-center"
                     @keydown.enter="handleJoin" />
            </div>

            <!-- Advanced toggle -->
            <button @click="showAdvanced = !showAdvanced"
                    class="text-xs text-gray-500 hover:text-gray-400 transition">
              {{ showAdvanced ? '▼' : '▶' }} Configurações avançadas
            </button>

            <div v-if="showAdvanced" class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-xs text-gray-500 mb-1">Porta HTTP</label>
                <input v-model.number="serverPort"
                       type="number"
                       class="w-full px-3 py-2 rounded-lg bg-gray-700/60 border border-gray-600 text-white
                              text-center text-sm focus:border-amber-400 focus:outline-none transition" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">Porta WebSocket</label>
                <input v-model.number="wsPort"
                       type="number"
                       class="w-full px-3 py-2 rounded-lg bg-gray-700/60 border border-gray-600 text-white
                              text-center text-sm focus:border-amber-400 focus:outline-none transition" />
              </div>
            </div>

            <button @click="handleJoin"
                    :disabled="!hostIp.trim() || state.loading"
                    class="w-full py-3 rounded-xl font-bold transition-all duration-200
                           bg-gray-600 text-white hover:bg-gray-500 disabled:opacity-50 disabled:cursor-not-allowed">
              Conectar
            </button>
          </div>
        </div>

        <!-- Loading indicator -->
        <div v-if="state.loading" class="text-center text-gray-400 text-sm flex items-center justify-center gap-2">
          <div class="animate-spin w-4 h-4 border-2 border-amber-400 border-t-transparent rounded-full"></div>
          Conectando...
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useGame } from '../composables/useGame';

const { state, connectAsHost, connectAsClient } = useGame();

const hostIp = ref('');
const serverPort = ref(8000);
const wsPort = ref(8080);
const showAdvanced = ref(false);

async function handleHost() {
  await connectAsHost();
}

async function handleJoin() {
  if (!hostIp.value.trim()) return;
  await connectAsClient(hostIp.value.trim(), serverPort.value, wsPort.value);
}
</script>
