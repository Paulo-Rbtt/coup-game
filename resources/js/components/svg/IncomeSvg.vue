<template>
  <!-- Income: Single coin with shine -->
  <svg viewBox="0 0 200 200" :class="svgClass">
    <!-- Glow backdrop -->
    <circle cx="100" cy="100" r="70" fill="#fbbf24" opacity="0.15" class="income-glow" />
    <!-- Main coin -->
    <g class="income-coin">
      <circle cx="100" cy="100" r="50" fill="#fbbf24" stroke="#d97706" stroke-width="4" />
      <circle cx="100" cy="100" r="40" fill="none" stroke="#f59e0b" stroke-width="2" />
      <text x="100" y="108" text-anchor="middle" font-size="36" fill="#92400e" font-weight="bold">$</text>
    </g>
    <!-- Sparkles -->
    <g class="income-sparkle1">
      <polygon points="55,45 58,52 65,52 60,56 62,63 55,59 48,63 50,56 45,52 52,52" fill="#fde68a" />
    </g>
    <g class="income-sparkle2">
      <polygon points="150,50 153,57 160,57 155,61 157,68 150,64 143,68 145,61 140,57 147,57" fill="#fde68a" />
    </g>
    <g class="income-sparkle3">
      <polygon points="145,145 148,152 155,152 150,156 152,163 145,159 138,163 140,156 135,152 142,152" fill="#fde68a" />
    </g>
    <!-- +1 label -->
    <g class="income-label">
      <text x="100" y="180" text-anchor="middle" font-size="22" fill="#fbbf24" font-weight="bold">+1</text>
    </g>
  </svg>
</template>

<script setup>
defineProps({ svgClass: { type: String, default: '' } });
</script>

<style scoped>
.income-coin {
  animation: income-bounce 1.2s ease-in-out infinite;
  transform-origin: center;
}
.income-glow {
  animation: income-pulse 1.5s ease-in-out infinite;
  transform-origin: center;
}
.income-sparkle1 {
  animation: income-twinkle 1s ease-in-out infinite;
  transform-origin: 55px 54px;
}
.income-sparkle2 {
  animation: income-twinkle 1s ease-in-out infinite 0.3s;
  transform-origin: 150px 59px;
}
.income-sparkle3 {
  animation: income-twinkle 1s ease-in-out infinite 0.6s;
  transform-origin: 145px 154px;
}
.income-label {
  animation: income-float 1.5s ease-in-out infinite;
}

@keyframes income-bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-8px); }
}
@keyframes income-pulse {
  0%, 100% { opacity: 0.15; r: 70; }
  50% { opacity: 0.3; r: 75; }
}
@keyframes income-twinkle {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.3; transform: scale(0.5); }
}
@keyframes income-float {
  0%, 100% { opacity: 1; transform: translateY(0); }
  50% { opacity: 0.7; transform: translateY(-4px); }
}
</style>
