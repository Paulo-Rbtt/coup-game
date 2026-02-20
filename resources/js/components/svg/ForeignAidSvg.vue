<template>
  <!-- Foreign Aid: Two coins with hands -->
  <svg viewBox="0 0 200 200" :class="svgClass">
    <!-- Glow backdrop -->
    <circle cx="100" cy="100" r="75" fill="#fbbf24" opacity="0.1" class="aid-glow" />
    <!-- Left coin -->
    <g class="aid-coin-left">
      <circle cx="75" cy="95" r="35" fill="#fbbf24" stroke="#d97706" stroke-width="3" />
      <circle cx="75" cy="95" r="27" fill="none" stroke="#f59e0b" stroke-width="1.5" />
      <text x="75" y="103" text-anchor="middle" font-size="24" fill="#92400e" font-weight="bold">$</text>
    </g>
    <!-- Right coin -->
    <g class="aid-coin-right">
      <circle cx="125" cy="95" r="35" fill="#fbbf24" stroke="#d97706" stroke-width="3" />
      <circle cx="125" cy="95" r="27" fill="none" stroke="#f59e0b" stroke-width="1.5" />
      <text x="125" y="103" text-anchor="middle" font-size="24" fill="#92400e" font-weight="bold">$</text>
    </g>
    <!-- Hands icon (representing aid/help) -->
    <g class="aid-hands">
      <path d="M85,50 Q100,30 115,50" fill="none" stroke="#fde68a" stroke-width="3" stroke-linecap="round" />
      <circle cx="85" cy="52" r="4" fill="#fde68a" />
      <circle cx="115" cy="52" r="4" fill="#fde68a" />
    </g>
    <!-- Sparkles -->
    <g class="aid-sparkle1">
      <polygon points="50,55 53,62 60,62 55,66 57,73 50,69 43,73 45,66 40,62 47,62" fill="#fde68a" />
    </g>
    <g class="aid-sparkle2">
      <polygon points="155,55 158,62 165,62 160,66 162,73 155,69 148,73 150,66 145,62 152,62" fill="#fde68a" />
    </g>
    <!-- +2 label -->
    <g class="aid-label">
      <text x="100" y="170" text-anchor="middle" font-size="22" fill="#fbbf24" font-weight="bold">+2</text>
    </g>
    <!-- "Ajuda" subtitle -->
    <text x="100" y="190" text-anchor="middle" font-size="11" fill="#d97706" opacity="0.8">AJUDA EXTERNA</text>
  </svg>
</template>

<script setup>
defineProps({ svgClass: { type: String, default: '' } });
</script>

<style scoped>
.aid-coin-left {
  animation: aid-slide-left 1.5s ease-in-out infinite;
  transform-origin: center;
}
.aid-coin-right {
  animation: aid-slide-right 1.5s ease-in-out infinite;
  transform-origin: center;
}
.aid-glow {
  animation: aid-pulse 2s ease-in-out infinite;
  transform-origin: center;
}
.aid-hands {
  animation: aid-hands-move 1.5s ease-in-out infinite;
  transform-origin: 100px 45px;
}
.aid-sparkle1 {
  animation: aid-twinkle 1.2s ease-in-out infinite;
  transform-origin: 50px 64px;
}
.aid-sparkle2 {
  animation: aid-twinkle 1.2s ease-in-out infinite 0.4s;
  transform-origin: 155px 64px;
}
.aid-label {
  animation: aid-float 1.5s ease-in-out infinite;
}

@keyframes aid-slide-left {
  0%, 100% { transform: translateX(0); }
  50% { transform: translateX(-5px); }
}
@keyframes aid-slide-right {
  0%, 100% { transform: translateX(0); }
  50% { transform: translateX(5px); }
}
@keyframes aid-pulse {
  0%, 100% { opacity: 0.1; }
  50% { opacity: 0.25; }
}
@keyframes aid-hands-move {
  0%, 100% { transform: translateY(0) scale(1); }
  50% { transform: translateY(-5px) scale(1.05); }
}
@keyframes aid-twinkle {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.3; transform: scale(0.5); }
}
@keyframes aid-float {
  0%, 100% { opacity: 1; transform: translateY(0); }
  50% { opacity: 0.7; transform: translateY(-4px); }
}
</style>
