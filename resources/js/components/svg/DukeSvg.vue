<template>
  <!-- Duke: Royal figure with crown collecting coins -->
  <svg viewBox="0 0 200 200" :class="svgClass">
    <defs>
      <linearGradient id="duke-robe" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style="stop-color:#7c3aed" />
        <stop offset="100%" style="stop-color:#5b21b6" />
      </linearGradient>
    </defs>
    <!-- Body/Robe -->
    <ellipse cx="100" cy="155" rx="45" ry="35" fill="url(#duke-robe)" class="duke-body" />
    <!-- Head -->
    <circle cx="100" cy="90" r="28" fill="#fbbf24" class="duke-head" />
    <!-- Eyes -->
    <circle cx="90" cy="86" r="4" fill="#1e1b4b" />
    <circle cx="110" cy="86" r="4" fill="#1e1b4b" />
    <!-- Smirk -->
    <path d="M88 100 Q100 110 112 100" stroke="#1e1b4b" fill="none" stroke-width="2.5" stroke-linecap="round" />
    <!-- Crown -->
    <polygon points="75,72 80,55 88,68 95,50 100,65 105,50 112,68 120,55 125,72" fill="#fbbf24" stroke="#f59e0b" stroke-width="1.5" class="duke-crown" />
    <!-- Crown jewels -->
    <circle cx="88" cy="65" r="3" fill="#dc2626" />
    <circle cx="100" cy="58" r="3" fill="#2563eb" />
    <circle cx="112" cy="65" r="3" fill="#16a34a" />
    <!-- Coins floating -->
    <g class="duke-coins">
      <circle cx="50" cy="130" r="10" fill="#fbbf24" stroke="#f59e0b" stroke-width="1.5" />
      <text x="50" y="134" text-anchor="middle" font-size="12" fill="#92400e" font-weight="bold">$</text>
      <circle cx="150" cy="120" r="10" fill="#fbbf24" stroke="#f59e0b" stroke-width="1.5" />
      <text x="150" y="124" text-anchor="middle" font-size="12" fill="#92400e" font-weight="bold">$</text>
      <circle cx="60" cy="100" r="8" fill="#fbbf24" stroke="#f59e0b" stroke-width="1" />
      <text x="60" y="103" text-anchor="middle" font-size="10" fill="#92400e" font-weight="bold">$</text>
    </g>
  </svg>
</template>

<script setup>
defineProps({ svgClass: { type: String, default: '' } });
</script>

<style scoped>
.duke-crown {
  animation: duke-float 2s ease-in-out infinite;
  transform-origin: center;
}
.duke-coins {
  animation: duke-coins-float 1.5s ease-in-out infinite alternate;
}
.duke-body {
  animation: duke-pulse 2s ease-in-out infinite;
}
@keyframes duke-float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}
@keyframes duke-coins-float {
  0% { opacity: 0.5; transform: translateY(0); }
  100% { opacity: 1; transform: translateY(-8px); }
}
@keyframes duke-pulse {
  0%, 100% { transform: scaleX(1); }
  50% { transform: scaleX(1.03); }
}
</style>
