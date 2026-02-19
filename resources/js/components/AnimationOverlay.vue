<template>
  <Teleport to="body">
    <Transition name="overlay-fade">
      <div v-if="currentAnimation"
           class="fixed inset-0 z-[9999] flex items-center justify-center pointer-events-none"
           @click.self="skip">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

        <!-- Animation content -->
        <div class="relative flex flex-col items-center gap-3 pointer-events-auto animate-pop-in">
          <!-- SVG -->
          <div class="w-40 h-40 sm:w-52 sm:h-52">
            <component :is="currentAnimation.component" svg-class="w-full h-full" />
          </div>

          <!-- Description text -->
          <div class="text-center max-w-xs px-4">
            <p class="text-base sm:text-lg font-bold text-white drop-shadow-lg">
              {{ currentAnimation.title }}
            </p>
            <p v-if="currentAnimation.subtitle" class="text-xs sm:text-sm text-gray-300 mt-1">
              {{ currentAnimation.subtitle }}
            </p>
          </div>

          <!-- Progress dots for queue -->
          <div v-if="queue.length > 0" class="flex gap-1 mt-1">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
            <span v-for="i in Math.min(queue.length, 4)" :key="i"
                  class="w-1.5 h-1.5 rounded-full bg-gray-500"></span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, shallowRef, watch, onUnmounted, markRaw } from 'vue';
import DukeSvg from './svg/DukeSvg.vue';
import AssassinSvg from './svg/AssassinSvg.vue';
import CaptainSvg from './svg/CaptainSvg.vue';
import AmbassadorSvg from './svg/AmbassadorSvg.vue';
import ContessaSvg from './svg/ContessaSvg.vue';
import CoupSvg from './svg/CoupSvg.vue';
import ChallengeSvg from './svg/ChallengeSvg.vue';
import VictorySvg from './svg/VictorySvg.vue';
import ExileSvg from './svg/ExileSvg.vue';

const props = defineProps({
  events: { type: Array, default: () => [] },
  players: { type: Array, default: () => [] },
});

const currentAnimation = shallowRef(null);
const queue = ref([]);
let dismissTimer = null;
let processedCount = ref(0);

// Character name map
const CHARACTER_MAP = {
  duke: { component: markRaw(DukeSvg), label: 'Duque' },
  assassin: { component: markRaw(AssassinSvg), label: 'Assassino' },
  captain: { component: markRaw(CaptainSvg), label: 'Capitão' },
  ambassador: { component: markRaw(AmbassadorSvg), label: 'Embaixador' },
  contessa: { component: markRaw(ContessaSvg), label: 'Condessa' },
};

// Action → animation config
const ACTION_ANIMATIONS = {
  tax: { character: 'duke', verb: 'cobrou Imposto' },
  steal: { character: 'captain', verb: 'roubou moedas' },
  assassinate: { character: 'assassin', verb: 'tenta Assassinar' },
  exchange: { character: 'ambassador', verb: 'fez Troca' },
  foreign_aid: { character: null, verb: 'pediu Ajuda Externa' },
  income: { character: null, verb: 'pegou Renda' },
  coup: { character: null, verb: 'deu Golpe de Estado' },
};

function playerName(id) {
  const p = props.players.find(p => p.id === id);
  return p?.name || 'Jogador';
}

function mapEventToAnimation(event) {
  const type = event.type;

  if (type === 'action_declared') {
    const actionCfg = ACTION_ANIMATIONS[event.action];
    if (!actionCfg) return null;

    if (event.action === 'coup') {
      return {
        component: markRaw(CoupSvg),
        title: `${event.actor_name} dá Golpe de Estado!`,
        subtitle: event.target_name ? `Alvo: ${event.target_name}` : null,
        duration: 3000,
      };
    }

    const charInfo = actionCfg.character ? CHARACTER_MAP[actionCfg.character] : null;
    return {
      component: charInfo ? charInfo.component : markRaw(DukeSvg),
      title: `${event.actor_name} ${actionCfg.verb}!`,
      subtitle: event.target_name ? `Alvo: ${event.target_name}` : (charInfo ? `Usando: ${charInfo.label}` : null),
      duration: 2500,
    };
  }

  if (type === 'challenge_action' || type === 'challenge_block') {
    return {
      component: markRaw(ChallengeSvg),
      title: `${event.challenger_name} contesta!`,
      subtitle: type === 'challenge_block'
        ? `Contesta o bloqueio de ${event.blocker_name || 'jogador'}`
        : `Contesta ${event.actor_name}`,
      duration: 2500,
    };
  }

  if (type === 'challenge_failed' || type === 'challenge_block_failed') {
    const charInfo = event.character ? CHARACTER_MAP[event.character] : null;
    return {
      component: charInfo ? charInfo.component : markRaw(ChallengeSvg),
      title: 'Contestação falhou!',
      subtitle: `${event.proven_by_name || event.blocker_name || 'Jogador'} provou ter ${charInfo?.label || event.character}`,
      duration: 2500,
    };
  }

  if (type === 'challenge_succeeded' || type === 'challenge_block_succeeded') {
    return {
      component: markRaw(ChallengeSvg),
      title: 'Contestação bem-sucedida!',
      subtitle: type === 'challenge_block_succeeded'
        ? `${event.blocker_name || 'Jogador'} não tinha a carta!`
        : `${event.actor_name} foi pego blefando!`,
      duration: 2500,
    };
  }

  if (type === 'block_declared') {
    const charInfo = event.block_character ? CHARACTER_MAP[event.block_character] : null;
    return {
      component: charInfo ? charInfo.component : markRaw(ContessaSvg),
      title: `${event.blocker_name} bloqueia!`,
      subtitle: charInfo ? `Usando: ${charInfo.label}` : null,
      duration: 2500,
    };
  }

  if (type === 'block_succeeded') {
    return {
      component: markRaw(ContessaSvg),
      title: 'Bloqueio aceito!',
      subtitle: 'Ação foi bloqueada com sucesso',
      duration: 2000,
    };
  }

  if (type === 'player_exiled') {
    return {
      component: markRaw(ExileSvg),
      title: `${event.player_name} foi eliminado!`,
      subtitle: null,
      duration: 3000,
    };
  }

  if (type === 'game_over') {
    return {
      component: markRaw(VictorySvg),
      title: `${event.winner_name || 'Jogador'} venceu! 🏆`,
      subtitle: 'Fim de jogo!',
      duration: 4000,
    };
  }

  if (type === 'player_abandoned') {
    return {
      component: markRaw(ExileSvg),
      title: `${event.player_name} abandonou!`,
      subtitle: null,
      duration: 2000,
    };
  }

  if (type === 'influence_lost') {
    const charInfo = event.character ? CHARACTER_MAP[event.character] : null;
    return {
      component: charInfo ? charInfo.component : markRaw(ExileSvg),
      title: `${event.player_name} perdeu influência!`,
      subtitle: charInfo ? charInfo.label : null,
      duration: 2000,
    };
  }

  // Don't animate these event types
  // action_resolved, exchange_started, exchange_completed, turn_start, game_started
  return null;
}

function showNext() {
  if (queue.value.length === 0) {
    currentAnimation.value = null;
    return;
  }

  const next = queue.value.shift();
  currentAnimation.value = next;

  dismissTimer = setTimeout(() => {
    currentAnimation.value = null;
    // Small gap between animations
    setTimeout(showNext, 300);
  }, next.duration || 2500);
}

function skip() {
  if (dismissTimer) clearTimeout(dismissTimer);
  currentAnimation.value = null;
  setTimeout(showNext, 200);
}

function enqueueAnimation(anim) {
  if (currentAnimation.value) {
    queue.value.push(anim);
  } else {
    currentAnimation.value = anim;
    dismissTimer = setTimeout(() => {
      currentAnimation.value = null;
      setTimeout(showNext, 300);
    }, anim.duration || 2500);
  }
}

// Watch events array for new entries
watch(() => props.events?.length, (newLen, oldLen) => {
  if (!newLen || !oldLen || newLen <= oldLen) return;

  // Process only new events
  const newEvents = props.events.slice(oldLen);
  for (const event of newEvents) {
    const anim = mapEventToAnimation(event);
    if (anim) {
      enqueueAnimation(anim);
    }
  }
}, { flush: 'post' });

// Initialize processedCount on mount to avoid animating old events
watch(() => props.events?.length, (len) => {
  if (len && processedCount.value === 0) {
    processedCount.value = len;
  }
}, { immediate: true });

onUnmounted(() => {
  if (dismissTimer) clearTimeout(dismissTimer);
});
</script>

<style scoped>
.overlay-fade-enter-active {
  transition: opacity 0.25s ease-out;
}
.overlay-fade-leave-active {
  transition: opacity 0.3s ease-in;
}
.overlay-fade-enter-from,
.overlay-fade-leave-to {
  opacity: 0;
}

@keyframes pop-in {
  0% { transform: scale(0.6); opacity: 0; }
  60% { transform: scale(1.05); opacity: 1; }
  100% { transform: scale(1); opacity: 1; }
}
.animate-pop-in {
  animation: pop-in 0.35s ease-out forwards;
}
</style>
