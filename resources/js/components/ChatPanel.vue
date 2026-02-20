<template>
  <!-- Floating chat toggle (when collapsed) -->
  <button v-if="!isOpen"
          @click="openChat"
          class="fixed bottom-4 right-4 z-40 w-12 h-12 rounded-full bg-amber-500 hover:bg-amber-400 text-gray-900 shadow-lg flex items-center justify-center transition-all duration-200 cursor-pointer"
          :class="{ 'animate-bounce': hasUnread }">
    <span class="text-lg">💬</span>
    <span v-if="unreadCount > 0"
          class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
      {{ unreadCount > 9 ? '9+' : unreadCount }}
    </span>
  </button>

  <!-- Chat panel -->
  <Transition name="chat-slide">
    <div v-if="isOpen"
         class="fixed bottom-4 right-4 z-40 w-96 max-h-[520px] flex flex-col bg-gray-900/95 backdrop-blur-lg border border-gray-700 rounded-xl shadow-2xl overflow-hidden">

      <!-- Header -->
      <div class="flex items-center justify-between px-3 py-2 bg-gray-800/80 border-b border-gray-700 shrink-0">
        <div class="flex items-center gap-2">
          <span class="text-sm">💬</span>
          <h4 class="text-xs font-bold text-amber-400">Chat da Partida</h4>
        </div>
        <button @click="isOpen = false"
                class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-700 text-gray-400 hover:text-white transition-colors cursor-pointer">
          ✕
        </button>
      </div>

      <!-- Messages -->
      <div ref="messagesContainer"
           class="flex-1 overflow-y-auto p-3 space-y-2 min-h-0 max-h-[400px] scrollbar-thin">
        <div v-if="messages.length === 0" class="text-xs text-gray-600 italic text-center py-4">
          Nenhuma mensagem ainda. Diga algo! 🎭
        </div>

        <div v-for="(msg, idx) in messages" :key="idx"
             class="flex flex-col"
             :class="msg.player_id === myId ? 'items-end' : 'items-start'">
          <span class="text-[10px] font-semibold mb-0.5 px-1"
                :class="msg.player_id === myId ? 'text-amber-400' : 'text-gray-500'">
            {{ msg.player_id === myId ? 'Você' : msg.player_name }}
          </span>
          <div class="max-w-[85%] px-3 py-1.5 rounded-xl text-xs leading-relaxed break-words"
               :class="msg.player_id === myId
                 ? 'bg-amber-500/20 text-amber-100 rounded-br-sm border border-amber-500/30'
                 : 'bg-gray-700/60 text-gray-300 rounded-bl-sm border border-gray-600/40'">
            {{ msg.message }}
          </div>
        </div>
      </div>

      <!-- Input -->
      <div class="px-3 py-2 border-t border-gray-700 bg-gray-800/50 shrink-0">
        <form @submit.prevent="sendMessage" class="flex gap-2">
          <input v-model="input"
                 ref="inputRef"
                 type="text"
                 maxlength="200"
                 :placeholder="onCooldown ? `Aguarde ${cooldownRemaining}s para enviar...` : 'Digite uma mensagem...'"
                 class="flex-1 bg-gray-800 border border-gray-600 rounded-lg px-3 py-1.5 text-xs text-white placeholder-gray-500 outline-none focus:border-amber-500 transition-colors" />
          <button type="submit"
                  :disabled="!canSend"
                  class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all duration-200 cursor-pointer"
                  :class="canSend
                    ? 'bg-amber-500 hover:bg-amber-400 text-gray-900'
                    : 'bg-gray-700 text-gray-500 cursor-not-allowed'">
            ➤
          </button>
        </form>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import api from '../api';
import { playChatPing } from '../composables/useSound';

const props = defineProps({
  gameId: { type: Number, required: true },
  myId:   { type: Number, required: true },
});

const COOLDOWN_MS = 3000;
const MAX_MESSAGES = 100;

const isOpen = ref(false);
const input = ref('');
const messages = ref([]);
const unreadCount = ref(0);
const messagesContainer = ref(null);
const inputRef = ref(null);

// ── Cooldown ───────────────────────────────
const lastSentAt = ref(0);
const cooldownRemaining = ref(0);
let cooldownTimer = null;

const onCooldown = computed(() => cooldownRemaining.value > 0);
const canSend = computed(() => input.value.trim().length > 0 && !onCooldown.value);
const hasUnread = computed(() => unreadCount.value > 0);

function startCooldown() {
  lastSentAt.value = Date.now();
  cooldownRemaining.value = Math.ceil(COOLDOWN_MS / 1000);

  clearInterval(cooldownTimer);
  cooldownTimer = setInterval(() => {
    const elapsed = Date.now() - lastSentAt.value;
    const remain = Math.ceil((COOLDOWN_MS - elapsed) / 1000);
    cooldownRemaining.value = Math.max(0, remain);
    if (cooldownRemaining.value <= 0) {
      clearInterval(cooldownTimer);
    }
  }, 250);
}

// ── Open / scroll ──────────────────────────
function openChat() {
  isOpen.value = true;
  unreadCount.value = 0;
  nextTick(() => {
    scrollToBottom();
    inputRef.value?.focus();
  });
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
  });
}

// ── Send ────────────────────────────────────
async function sendMessage() {
  if (!canSend.value) return;

  const msg = input.value.trim();
  input.value = '';

  try {
    await api.post(`/games/${props.gameId}/chat`, { message: msg });
    startCooldown();
  } catch (e) {
    const errMsg = e.response?.data?.error || 'Erro ao enviar mensagem.';
    // Show locally as a system message
    messages.value.push({
      player_id: 0,
      player_name: 'Sistema',
      message: errMsg,
      timestamp: Date.now() / 1000,
    });
    scrollToBottom();
  }
}

// ── Echo listener ───────────────────────────
function onChatMessage(e) {
  messages.value.push(e);

  // Trim old messages
  if (messages.value.length > MAX_MESSAGES) {
    messages.value = messages.value.slice(-MAX_MESSAGES);
  }

  if (!isOpen.value) {
    unreadCount.value++;
  }

  // Play sound for messages from others
  if (e.player_id !== props.myId) {
    playChatPing();
  }

  scrollToBottom();
}

let echoChannel = null;

function listenChat() {
  if (!window.Echo || !props.gameId) return;
  const channelName = `game.${props.gameId}`;
  echoChannel = window.Echo.channel(channelName);
  echoChannel.listen('.chat.message', onChatMessage);
}

function stopListening() {
  if (echoChannel) {
    echoChannel.stopListening('.chat.message');
    echoChannel = null;
  }
}

// ── Auto-scroll on new messages when open ───
watch(() => messages.value.length, () => {
  if (isOpen.value) {
    scrollToBottom();
  }
});

onMounted(() => {
  listenChat();
});

onUnmounted(() => {
  stopListening();
  clearInterval(cooldownTimer);
});
</script>

<style scoped>
.chat-slide-enter-active,
.chat-slide-leave-active {
  transition: all 0.25s ease;
}
.chat-slide-enter-from,
.chat-slide-leave-to {
  opacity: 0;
  transform: translateY(16px) scale(0.95);
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
