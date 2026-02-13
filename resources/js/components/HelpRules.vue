<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="visible" class="fixed inset-0 z-[100] flex items-center justify-center p-4" @click.self="emit('close')">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl w-full max-w-lg max-h-[85vh] overflow-y-auto">
          <!-- Header -->
          <div class="sticky top-0 bg-gray-900 border-b border-gray-700 px-6 py-4 flex items-center justify-between rounded-t-2xl z-10">
            <h2 class="text-xl font-black text-amber-400">Como Jogar Coup</h2>
            <button @click="emit('close')"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-white transition">
              ✕
            </button>
          </div>

          <div class="px-6 py-4 space-y-5 text-sm text-gray-300">
            <!-- Objective -->
            <section>
              <h3 class="text-amber-400 font-bold mb-1">🎯 Objetivo</h3>
              <p>Ser o último jogador com influência (cartas). Elimine os outros usando blefes, ações e contestações.</p>
            </section>

            <!-- Setup -->
            <section>
              <h3 class="text-amber-400 font-bold mb-1">🃏 Preparação</h3>
              <p>Cada jogador recebe <b>2 cartas</b> (influências) e <b>2 moedas</b>. As cartas ficam secretas.</p>
            </section>

            <!-- Actions -->
            <section>
              <h3 class="text-amber-400 font-bold mb-2">⚡ Ações Gerais</h3>
              <div class="space-y-2">
                <div class="bg-gray-800/60 rounded-lg p-3">
                  <span class="font-bold text-white">Renda</span>
                  <span class="text-gray-400 ml-2">+1 moeda. Não pode ser contestada nem bloqueada.</span>
                </div>
                <div class="bg-gray-800/60 rounded-lg p-3">
                  <span class="font-bold text-white">Ajuda Externa</span>
                  <span class="text-gray-400 ml-2">+2 moedas. Pode ser bloqueada pelo Duque.</span>
                </div>
                <div class="bg-gray-800/60 rounded-lg p-3">
                  <span class="font-bold text-white">Golpe de Estado</span>
                  <span class="text-gray-400 ml-2">Custa 7 moedas. O alvo perde 1 influência. Obrigatório com 10+ moedas.</span>
                </div>
              </div>
            </section>

            <!-- Characters -->
            <section>
              <h3 class="text-amber-400 font-bold mb-2">👥 Personagens e Poderes</h3>
              <div class="space-y-2">
                <div class="bg-purple-900/30 border border-purple-800/40 rounded-lg p-3">
                  <span class="font-bold" style="color: #7c3aed">Duque</span>
                  <p class="text-gray-400 text-xs mt-1"><b>Ação:</b> Taxar (+3 moedas) &nbsp;|&nbsp; <b>Bloqueia:</b> Ajuda Externa</p>
                </div>
                <div class="bg-gray-800/40 border border-gray-700/40 rounded-lg p-3">
                  <span class="font-bold text-gray-200">Assassino</span>
                  <p class="text-gray-400 text-xs mt-1"><b>Ação:</b> Assassinar (3 moedas, alvo perde influência) &nbsp;|&nbsp; <b>Bloqueia:</b> Nada</p>
                </div>
                <div class="bg-blue-900/30 border border-blue-800/40 rounded-lg p-3">
                  <span class="font-bold" style="color: #2563eb">Capitão</span>
                  <p class="text-gray-400 text-xs mt-1"><b>Ação:</b> Extorquir (pega 2 moedas do alvo) &nbsp;|&nbsp; <b>Bloqueia:</b> Extorsão</p>
                </div>
                <div class="bg-green-900/30 border border-green-800/40 rounded-lg p-3">
                  <span class="font-bold" style="color: #16a34a">Embaixador</span>
                  <p class="text-gray-400 text-xs mt-1"><b>Ação:</b> Trocar cartas com o baralho &nbsp;|&nbsp; <b>Bloqueia:</b> Extorsão</p>
                </div>
                <div class="bg-red-900/30 border border-red-800/40 rounded-lg p-3">
                  <span class="font-bold" style="color: #dc2626">Condessa</span>
                  <p class="text-gray-400 text-xs mt-1"><b>Ação:</b> Nenhuma &nbsp;|&nbsp; <b>Bloqueia:</b> Assassinato</p>
                </div>
              </div>
            </section>

            <!-- Bluffing -->
            <section>
              <h3 class="text-amber-400 font-bold mb-1">🎭 Blefe</h3>
              <p>Você pode declarar <b>qualquer ação de personagem</b> mesmo sem ter a carta! Mas se alguém contestar e você não tiver a carta, <b>você perde 1 influência</b>.</p>
            </section>

            <!-- Challenge -->
            <section>
              <h3 class="text-amber-400 font-bold mb-1">🔍 Contestação</h3>
              <p>Quando alguém declara uma ação de personagem, os outros podem <b>contestar</b>.</p>
              <ul class="list-disc list-inside mt-1 text-gray-400 space-y-1">
                <li><b class="text-white">Contestação bem-sucedida:</b> O blefador perde 1 influência e a ação falha.</li>
                <li><b class="text-white">Contestação falha:</b> O contestador perde 1 influência. O ator troca a carta provada por uma nova.</li>
              </ul>
            </section>

            <!-- Block -->
            <section>
              <h3 class="text-amber-400 font-bold mb-1">🛡️ Bloqueio</h3>
              <p>Algumas ações podem ser bloqueadas por personagens específicos. O bloqueio também pode ser contestado!</p>
            </section>

            <!-- Elimination -->
            <section>
              <h3 class="text-amber-400 font-bold mb-1">💀 Eliminação</h3>
              <p>Quando perde as 2 influências, o jogador é eliminado. Suas moedas voltam ao tesouro. O último sobrevivente vence!</p>
            </section>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
defineProps({
  visible: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
