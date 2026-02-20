<template>
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg text-center">
      <div class="text-6xl mb-4">🏆</div>
      <h1 class="text-4xl font-black text-amber-400 mb-2">Fim de Jogo!</h1>

      <div v-if="winner" class="bg-gray-800/60 backdrop-blur rounded-2xl p-6 border border-amber-400/40 mb-6">
        <p class="text-gray-400 text-sm mb-1">Vencedor</p>
        <p class="text-3xl font-black text-amber-400">{{ winner.name }}</p>
        <p v-if="isWinner" class="text-green-400 text-sm mt-2">Parabéns! Você venceu! 🎉</p>
        <!-- Winner's remaining cards -->
        <div v-if="winnerInfluences.length" class="flex justify-center gap-2 mt-3">
          <div v-for="(card, idx) in winnerInfluences" :key="idx"
               class="px-3 py-1 rounded-lg text-xs font-bold border"
               :style="{ backgroundColor: getColor(card) + '33', borderColor: getColor(card) + '88', color: getColor(card) }">
            {{ getName(card) }}
          </div>
        </div>
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
            <!-- Show revealed cards -->
            <div class="flex gap-1 ml-2">
              <span v-for="(card, ci) in player.revealed" :key="ci"
                    class="text-[10px] px-1.5 py-0.5 rounded font-bold"
                    :style="{ backgroundColor: getColor(card) + '22', color: getColor(card) }">
                {{ getName(card) }}
              </span>
            </div>
            <span class="ml-auto text-xs text-gray-500">
              {{ player.coins }} moedas
            </span>
          </div>
        </div>
      </div>

      <!-- Match History (event log) -->
      <div class="bg-gray-800/40 rounded-xl border border-gray-700 mb-6 text-left">
        <button @click="showHistory = !showHistory"
                class="w-full px-4 py-3 flex items-center justify-between text-sm font-bold text-gray-400 hover:text-amber-400 transition cursor-pointer">
          <span>📜 Histórico da Partida ({{ (state.game?.event_log || []).length }} eventos)</span>
          <span class="text-xs">{{ showHistory ? '▲ Fechar' : '▼ Expandir' }}</span>
        </button>
        <Transition name="expand">
          <div v-if="showHistory" class="px-4 pb-4 max-h-64 overflow-y-auto space-y-1 scrollbar-thin">
            <div v-for="(event, idx) in reversedLog" :key="idx"
                 class="text-xs text-gray-400 flex gap-2 items-start py-0.5">
              <span class="text-[10px] text-gray-600 font-mono shrink-0">T{{ event.turn || '?' }}</span>
              <span>{{ formatEvent(event) }}</span>
            </div>
            <div v-if="!state.game?.event_log?.length" class="text-xs text-gray-600 italic">
              Nenhum evento registrado.
            </div>
          </div>
        </Transition>
      </div>

      <!-- Action buttons -->
      <div v-if="!wantsRematch" class="flex flex-col sm:flex-row gap-3 justify-center">
        <button @click="rematchGame"
                class="py-3 px-8 rounded-xl bg-amber-400 text-gray-900 font-bold text-lg
                       hover:bg-amber-300 transition cursor-pointer">
          🔄 Revanche
        </button>
        <button @click="downloadPdf"
                class="py-3 px-8 rounded-xl bg-indigo-600 text-white font-bold text-lg
                       hover:bg-indigo-500 border border-indigo-500 transition cursor-pointer">
          📄 Baixar PDF
        </button>
        <button @click="leaveAfterGameOver"
                class="py-3 px-8 rounded-xl bg-gray-700 text-gray-300 font-bold text-lg
                       hover:bg-gray-600 border border-gray-600 transition cursor-pointer">
          Sair
        </button>
      </div>

      <!-- Waiting for others -->
      <div v-else class="text-center space-y-4">
        <div class="bg-amber-400/10 border border-amber-400/30 rounded-xl p-4">
          <p class="text-amber-400 font-bold text-lg">🔄 Aguardando outros jogadores...</p>
          <div class="flex flex-wrap justify-center gap-2 mt-3">
            <span v-for="p in gamePlayersStatus" :key="p.id"
                  class="px-3 py-1 rounded-full text-xs font-bold"
                  :class="p.is_ready ? 'bg-green-600/30 text-green-400 border border-green-500/40' : 'bg-gray-700/50 text-gray-400 border border-gray-600'">
              {{ p.name }} {{ p.is_ready ? '✓' : '…' }}
            </span>
          </div>
        </div>
        <div class="flex gap-3 justify-center">
          <button @click="downloadPdf"
                  class="py-2 px-6 rounded-xl bg-indigo-600 text-white font-bold
                         hover:bg-indigo-500 border border-indigo-500 transition cursor-pointer">
            📄 Baixar PDF
          </button>
          <button @click="leaveAfterGameOver"
                  class="py-2 px-6 rounded-xl bg-gray-700 text-gray-300 font-bold
                         hover:bg-gray-600 border border-gray-600 transition cursor-pointer">
            Sair
          </button>
        </div>
      </div>
    </div>

    <!-- Chat -->
    <ChatPanel v-if="state.game?.id && state.player?.id"
               :gameId="state.game.id"
               :myId="state.player.id" />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useGame } from '../composables/useGame';
import { CHARACTERS, ACTIONS } from '../data/characters';
import ChatPanel from './ChatPanel.vue';

const { state, leaveAfterGameOver, rematchGame } = useGame();

const showHistory = ref(false);

const wantsRematch = computed(() => {
  return state.player?.is_ready ?? false;
});

const gamePlayersStatus = computed(() => {
  if (!state.game?.players) return [];
  return state.game.players.filter(p => !p.is_spectator);
});

const winner = computed(() => {
  if (!state.game) return null;
  return state.game.players.find(p => p.id === state.game.winner_id);
});

const isWinner = computed(() => {
  return state.player?.id === state.game?.winner_id;
});

// Winner's remaining influences (from private state if it's us, or from results)
const winnerInfluences = computed(() => {
  if (isWinner.value && state.player?.influences?.length) {
    return state.player.influences;
  }
  return [];
});

const sortedPlayers = computed(() => {
  if (!state.game?.players) return [];
  return [...state.game.players].sort((a, b) => {
    if (a.is_alive && !b.is_alive) return -1;
    if (!a.is_alive && b.is_alive) return 1;
    return 0;
  });
});

const reversedLog = computed(() => {
  return [...(state.game?.event_log || [])].reverse();
});

function getColor(character) {
  return CHARACTERS[character]?.color || '#666';
}

function getName(character) {
  return CHARACTERS[character]?.name || character;
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
    case 'challenge_block':
      return `${event.challenger_name} contesta bloqueio de ${event.blocker_name}`;
    case 'challenge_block_failed':
      return `${event.proven_by_name} prova ${CHARACTERS[event.character]?.name}! Bloqueio se mantém`;
    case 'challenge_block_succeeded':
      return `${event.challenger_name} vence! ${event.blocker_name} blefou`;
    case 'block_succeeded':
      return `Bloqueio bem-sucedido! Ação falha`;
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
      if (event.target_name) msg += ` → ${event.target_name}`;
      return msg;
    }
    case 'exchange_started':
      return `${event.actor_name} inicia troca de cartas`;
    case 'exchange_completed':
      return `${event.actor_name} completou a troca`;
    case 'turn_start':
      return `── Turno ${event.turn}: vez de ${event.player_name} ──`;
    case 'game_over':
      return `🏆 ${event.winner_name} venceu!`;
    default:
      return JSON.stringify(event);
  }
}

function downloadPdf() {
  const game = state.game;
  if (!game) return;

  const players = sortedPlayers.value;
  const events = game.event_log || [];
  const winnerPlayer = winner.value;

  // Build text content for the PDF-like download (plain text/HTML)
  let html = `
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>COUP - Partida ${game.code}</title>
<style>
  body { font-family: 'Segoe UI', sans-serif; max-width: 700px; margin: 40px auto; color: #333; line-height: 1.6; }
  h1 { color: #d97706; border-bottom: 3px solid #d97706; padding-bottom: 8px; }
  h2 { color: #6b7280; margin-top: 24px; }
  .winner { background: #fef3c7; padding: 16px; border-radius: 12px; border: 2px solid #d97706; text-align: center; margin: 16px 0; }
  .winner-name { font-size: 24px; font-weight: 900; color: #d97706; }
  table { width: 100%; border-collapse: collapse; margin: 12px 0; }
  th, td { border: 1px solid #e5e7eb; padding: 8px 12px; text-align: left; font-size: 14px; }
  th { background: #f9fafb; font-weight: 600; }
  .alive { color: #16a34a; font-weight: bold; }
  .dead { color: #dc2626; }
  .event { font-size: 13px; padding: 4px 0; border-bottom: 1px solid #f3f4f6; }
  .event-turn { color: #9ca3af; font-family: monospace; font-size: 11px; margin-right: 8px; }
  .footer { text-align: center; color: #9ca3af; font-size: 12px; margin-top: 32px; }
</style></head><body>
  <h1>🏆 COUP - Partida ${game.code}</h1>
  <p><strong>Turnos:</strong> ${game.turn_number} | <strong>Jogadores:</strong> ${players.length}</p>

  <div class="winner">
    <div>🏆 Vencedor</div>
    <div class="winner-name">${winnerPlayer?.name || 'N/A'}</div>
  </div>

  <h2>Resultado Final</h2>
  <table>
    <thead><tr><th>#</th><th>Jogador</th><th>Status</th><th>Moedas</th><th>Cartas Reveladas</th></tr></thead>
    <tbody>`;

  players.forEach((p, i) => {
    const revealedCards = (p.revealed || []).map(c => getName(c)).join(', ') || '-';
    html += `<tr>
      <td>${i + 1}</td>
      <td>${p.name}</td>
      <td class="${p.is_alive ? 'alive' : 'dead'}">${p.is_alive ? '✅ Vivo' : '💀 Eliminado'}</td>
      <td>${p.coins}</td>
      <td>${revealedCards}</td>
    </tr>`;
  });

  html += `</tbody></table>

  <h2>📜 Histórico de Eventos</h2>
  <div>`;

  events.forEach(evt => {
    html += `<div class="event"><span class="event-turn">T${evt.turn || '?'}</span>${formatEvent(evt)}</div>`;
  });

  html += `</div>
  <div class="footer">Gerado em ${new Date().toLocaleString('pt-BR')} | COUP Online</div>
</body></html>`;

  // Open in new window for printing as PDF
  const printWindow = window.open('', '_blank');
  if (printWindow) {
    printWindow.document.write(html);
    printWindow.document.close();
    setTimeout(() => printWindow.print(), 500);
  }
}
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
.expand-enter-to,
.expand-leave-from {
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
