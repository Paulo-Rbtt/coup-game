<template>
  <svg :viewBox="'0 0 40 40'" xmlns="http://www.w3.org/2000/svg" v-bind="$attrs" ref="svgRef">
    <!-- Duke: Crown -->
    <g v-if="character === 'duke'">
      <path d="M6 30 L10 14 L16 22 L20 10 L24 22 L30 14 L34 30 Z"
            fill="#7c3aed" stroke="#a78bfa" stroke-width="1.5"/>
      <circle cx="10" cy="13" r="2" fill="#a78bfa"/>
      <circle cx="20" cy="9" r="2.5" fill="#c4b5fd"/>
      <circle cx="30" cy="13" r="2" fill="#a78bfa"/>
    </g>

    <!-- Assassin: Skull -->
    <g v-else-if="character === 'assassin'">
      <circle cx="20" cy="16" r="10" fill="#333" stroke="#666" stroke-width="1"/>
      <circle cx="15" cy="14" r="3" fill="#111"/>
      <circle cx="25" cy="14" r="3" fill="#111"/>
      <path d="M16 22 L18 20 L20 22 L22 20 L24 22" stroke="#666" stroke-width="1.5" fill="none"/>
      <rect x="18" y="26" width="4" height="6" rx="1" fill="#333" stroke="#666" stroke-width="0.5"/>
    </g>

    <!-- Captain: Anchor -->
    <g v-else-if="character === 'captain'">
      <circle cx="20" cy="10" r="4" fill="none" stroke="#2563eb" stroke-width="2"/>
      <line x1="20" y1="14" x2="20" y2="32" stroke="#2563eb" stroke-width="2"/>
      <line x1="14" y1="22" x2="26" y2="22" stroke="#2563eb" stroke-width="2"/>
      <path d="M10 28 Q15 34 20 32 Q25 34 30 28" fill="none" stroke="#60a5fa" stroke-width="2"/>
    </g>

    <!-- Ambassador: Scroll -->
    <g v-else-if="character === 'ambassador'">
      <rect x="12" y="8" width="16" height="24" rx="2" fill="#16a34a" stroke="#4ade80" stroke-width="1"/>
      <ellipse cx="12" cy="10" rx="3" ry="2" fill="#16a34a" stroke="#4ade80" stroke-width="1"/>
      <ellipse cx="28" cy="10" rx="3" ry="2" fill="#16a34a" stroke="#4ade80" stroke-width="1"/>
      <ellipse cx="12" cy="30" rx="3" ry="2" fill="#16a34a" stroke="#4ade80" stroke-width="1"/>
      <ellipse cx="28" cy="30" rx="3" ry="2" fill="#16a34a" stroke="#4ade80" stroke-width="1"/>
      <line x1="16" y1="15" x2="24" y2="15" stroke="#bbf7d0" stroke-width="1"/>
      <line x1="16" y1="19" x2="24" y2="19" stroke="#bbf7d0" stroke-width="1"/>
      <line x1="16" y1="23" x2="22" y2="23" stroke="#bbf7d0" stroke-width="1"/>
    </g>

    <!-- Contessa: Shield -->
    <g v-else-if="character === 'contessa'">
      <path d="M20 6 L32 12 L32 22 Q32 32 20 36 Q8 32 8 22 L8 12 Z"
            fill="#dc2626" stroke="#f87171" stroke-width="1.5"/>
      <path d="M20 12 L16 20 L24 20 Z" fill="#fca5a5"/>
      <circle cx="20" cy="26" r="3" fill="#fca5a5"/>
    </g>

    <!-- Unknown -->
    <g v-else>
      <rect x="8" y="8" width="24" height="24" rx="4" fill="#4b5563" stroke="#6b7280" stroke-width="1"/>
      <text x="20" y="24" text-anchor="middle" font-size="14" fill="#9ca3af">?</text>
    </g>
  </svg>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { animate } from 'animejs';

const props = defineProps({
  character: String,
});

const svgRef = ref(null);

onMounted(() => {
  if (svgRef.value) {
    animate(svgRef.value, {
      scale: [0, 1],
      opacity: [0, 1],
      duration: 400,
      ease: 'outBack',
    });
  }
});
</script>
