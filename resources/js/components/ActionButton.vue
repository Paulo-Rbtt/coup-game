<template>
  <button @click="$emit('click')"
          :disabled="disabled"
          class="flex flex-col items-center gap-1 px-3 py-3 rounded-xl border transition-all duration-200
                 hover:scale-105 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:scale-100"
          :class="colorClass">
    <span class="text-lg">{{ icon }}</span>
    <span class="text-xs font-bold">{{ label }}</span>
    <span class="text-[10px] opacity-70">{{ desc }}</span>
  </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  label: String,
  desc: String,
  color: { type: String, default: 'gray' },
  icon: String,
  disabled: Boolean,
});

defineEmits(['click']);

const colorClass = computed(() => {
  const map = {
    gray: 'bg-gray-700/60 border-gray-600 hover:bg-gray-600/60 text-gray-200',
    red: 'bg-red-900/40 border-red-700 hover:bg-red-800/40 text-red-300',
    purple: 'bg-purple-900/40 border-purple-700 hover:bg-purple-800/40 text-purple-300',
    black: 'bg-gray-900/60 border-gray-700 hover:bg-gray-800/60 text-gray-300',
    blue: 'bg-blue-900/40 border-blue-700 hover:bg-blue-800/40 text-blue-300',
    green: 'bg-green-900/40 border-green-700 hover:bg-green-800/40 text-green-300',
  };
  return map[props.color] || map.gray;
});
</script>
