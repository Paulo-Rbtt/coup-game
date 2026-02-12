<template>
  <div class="bg-gray-800/60 backdrop-blur rounded-xl p-4 border border-gray-700">
    <h3 class="text-sm font-bold text-gray-400 mb-3">Escolha sua ação</h3>

    <!-- Target selector (if needed) -->
    <div v-if="selectingTarget" class="mb-4">
      <p class="text-xs text-amber-400 mb-2">Selecione o alvo para {{ pendingAction.label }}:</p>
      <div class="space-y-1">
        <button v-for="opp in opponents" :key="opp.id"
                @click="confirmAction(opp.id)"
                class="w-full text-left px-3 py-2 rounded-lg bg-gray-700/60 hover:bg-gray-600/60
                       text-sm text-white transition flex items-center justify-between">
          <span>{{ opp.name }}</span>
          <span class="text-amber-400 text-xs">{{ opp.coins }} moedas</span>
        </button>
      </div>
      <button @click="selectingTarget = false" class="mt-2 text-xs text-gray-500 hover:text-gray-300">
        ← Voltar
      </button>
    </div>

    <!-- Action buttons -->
    <div v-else class="grid grid-cols-2 gap-2">
      <!-- General actions -->
      <ActionButton v-if="!mustCoup" label="Renda" desc="+1 moeda" color="gray" icon="💰"
                    @click="emitAction('income')" />
      <ActionButton v-if="!mustCoup" label="Ajuda Externa" desc="+2 moedas" color="gray" icon="🤝"
                    @click="emitAction('foreign_aid')" />
      <ActionButton label="Golpe de Estado" desc="-7 moedas" color="red" icon="⚔️"
                    :disabled="coins < 7"
                    @click="startTargeted('coup', { label: 'Golpe de Estado' })" />

      <!-- Character actions -->
      <ActionButton v-if="!mustCoup" label="Taxar" desc="+3 (Duque)" color="purple" icon="👑"
                    @click="emitAction('tax')" />
      <ActionButton v-if="!mustCoup" label="Assassinar" desc="-3 (Assassino)" color="black" icon="💀"
                    :disabled="coins < 3"
                    @click="startTargeted('assassinate', { label: 'Assassinar' })" />
      <ActionButton v-if="!mustCoup" label="Extorquir" desc="Pega 2 (Capitão)" color="blue" icon="⚓"
                    @click="startTargeted('steal', { label: 'Extorquir' })" />
      <ActionButton v-if="!mustCoup" label="Trocar" desc="Troca (Embaixador)" color="green" icon="📜"
                    @click="emitAction('exchange')" />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import ActionButton from './ActionButton.vue';

const props = defineProps({
  coins: Number,
  mustCoup: Boolean,
  opponents: Array,
});

const emit = defineEmits(['action']);

const selectingTarget = ref(false);
const pendingAction = ref(null);

function emitAction(action, targetId = null) {
  emit('action', { action, targetId });
}

function startTargeted(action, meta) {
  if (props.opponents.length === 1) {
    // Auto-select only opponent
    emit('action', { action, targetId: props.opponents[0].id });
    return;
  }
  pendingAction.value = { action, ...meta };
  selectingTarget.value = true;
}

function confirmAction(targetId) {
  emit('action', { action: pendingAction.value.action, targetId });
  selectingTarget.value = false;
  pendingAction.value = null;
}
</script>
