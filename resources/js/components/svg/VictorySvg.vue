<template>
  <!-- Victory: Trophy and celebration -->
  <svg viewBox="0 0 200 200" :class="svgClass">
    <defs>
      <linearGradient id="trophy-gold" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style="stop-color:#fbbf24" />
        <stop offset="100%" style="stop-color:#f59e0b" />
      </linearGradient>
    </defs>
    <!-- Trophy cup -->
    <g class="victory-trophy">
      <path d="M70,70 Q70,120 100,130 Q130,120 130,70 Z" fill="url(#trophy-gold)" />
      <!-- Handles -->
      <path d="M70,80 Q50,80 50,95 Q50,110 70,110" stroke="#f59e0b" fill="none" stroke-width="5" stroke-linecap="round" />
      <path d="M130,80 Q150,80 150,95 Q150,110 130,110" stroke="#f59e0b" fill="none" stroke-width="5" stroke-linecap="round" />
      <!-- Base -->
      <rect x="90" y="130" width="20" height="15" rx="2" fill="#d97706" />
      <rect x="80" y="145" width="40" height="10" rx="3" fill="#b45309" />
      <!-- Star on trophy -->
      <polygon points="100,82 104,92 115,93 107,100 109,111 100,105 91,111 93,100 85,93 96,92" fill="#fef3c7" />
    </g>
    <!-- Confetti -->
    <g class="victory-confetti">
      <rect x="30" y="40" width="8" height="5" rx="1" fill="#dc2626" transform="rotate(30,34,42)" />
      <rect x="160" y="35" width="8" height="5" rx="1" fill="#2563eb" transform="rotate(-20,164,37)" />
      <rect x="50" y="25" width="6" height="4" rx="1" fill="#16a34a" transform="rotate(45,53,27)" />
      <rect x="140" y="50" width="7" height="4" rx="1" fill="#7c3aed" transform="rotate(-35,143,52)" />
      <circle cx="45" cy="55" r="4" fill="#fbbf24" />
      <circle cx="155" cy="25" r="3" fill="#dc2626" />
      <circle cx="80" cy="30" r="3.5" fill="#2563eb" />
      <circle cx="120" cy="20" r="3" fill="#16a34a" />
    </g>
    <!-- Sparkles -->
    <text x="35" y="90" font-size="18" class="victory-sparkle1">✨</text>
    <text x="160" y="80" font-size="16" class="victory-sparkle2">✨</text>
    <text x="100" y="50" text-anchor="middle" font-size="20" class="victory-sparkle3">⭐</text>
    <!-- Rays -->
    <g class="victory-rays" opacity="0.15">
      <line x1="100" y1="100" x2="100" y2="10" stroke="#fbbf24" stroke-width="2" />
      <line x1="100" y1="100" x2="170" y2="30" stroke="#fbbf24" stroke-width="2" />
      <line x1="100" y1="100" x2="30" y2="30" stroke="#fbbf24" stroke-width="2" />
      <line x1="100" y1="100" x2="190" y2="100" stroke="#fbbf24" stroke-width="2" />
      <line x1="100" y1="100" x2="10" y2="100" stroke="#fbbf24" stroke-width="2" />
    </g>
  </svg>
</template>

<script setup>
defineProps({ svgClass: { type: String, default: '' } });
</script>

<style scoped>
.victory-trophy {
  animation: victory-bounce 1.5s ease-in-out infinite;
  transform-origin: 100px 130px;
}
.victory-confetti {
  animation: victory-confetti-fall 2s ease-in-out infinite;
}
.victory-sparkle1 {
  animation: victory-twinkle 1s ease-in-out infinite;
}
.victory-sparkle2 {
  animation: victory-twinkle 1s ease-in-out infinite 0.3s;
}
.victory-sparkle3 {
  animation: victory-twinkle 1s ease-in-out infinite 0.6s;
}
.victory-rays {
  animation: victory-rays-spin 6s linear infinite;
  transform-origin: 100px 100px;
}
@keyframes victory-bounce {
  0%, 100% { transform: translateY(0) scale(1); }
  50% { transform: translateY(-8px) scale(1.05); }
}
@keyframes victory-confetti-fall {
  0% { transform: translateY(-10px); opacity: 0.3; }
  50% { opacity: 1; }
  100% { transform: translateY(10px); opacity: 0.3; }
}
@keyframes victory-twinkle {
  0%, 100% { opacity: 0.3; transform: scale(0.8); }
  50% { opacity: 1; transform: scale(1.2); }
}
@keyframes victory-rays-spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
