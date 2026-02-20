<template>
  <!-- Card Swap: a revealed card slides away while a mystery card slides in -->
  <svg viewBox="0 0 200 200" :class="svgClass">
    <!-- Deck (source of new card) — top-right -->
    <g class="swap-deck">
      <rect x="130" y="25" width="40" height="55" rx="4" fill="#374151" stroke="#4b5563" stroke-width="1.5" />
      <rect x="133" y="22" width="40" height="55" rx="4" fill="#1f2937" stroke="#4b5563" stroke-width="1.5" />
      <text x="153" y="55" text-anchor="middle" font-size="18" fill="#6b7280" font-weight="bold">?</text>
    </g>

    <!-- Old card sliding out (left-down) -->
    <g class="swap-old-card">
      <rect x="70" y="70" width="60" height="85" rx="6" fill="#fbbf24" stroke="#f59e0b" stroke-width="2" />
      <circle cx="100" cy="105" r="14" fill="#f59e0b" stroke="#d97706" stroke-width="1.5" />
      <text x="100" y="111" text-anchor="middle" font-size="14" fill="#451a03" font-weight="bold">✓</text>
      <!-- Revealed indicator -->
      <circle cx="82" cy="84" r="5" fill="#10b981" />
      <text x="82" y="87" text-anchor="middle" font-size="7" fill="white" font-weight="bold">✓</text>
    </g>

    <!-- New card sliding in (from deck) -->
    <g class="swap-new-card">
      <rect x="70" y="70" width="60" height="85" rx="6" fill="#1f2937" stroke="#6b7280" stroke-width="2" />
      <!-- Mystery back design -->
      <rect x="78" y="78" width="44" height="69" rx="3" fill="#111827" stroke="#4b5563" stroke-width="1" />
      <text x="100" y="120" text-anchor="middle" font-size="28" fill="#4b5563" font-weight="bold">?</text>
      <!-- Subtle shimmer lines -->
      <line x1="82" y1="85" x2="118" y2="85" stroke="#374151" stroke-width="0.8" />
      <line x1="82" y1="140" x2="118" y2="140" stroke="#374151" stroke-width="0.8" />
    </g>

    <!-- Arrows showing flow -->
    <g class="swap-arrows">
      <!-- Arrow: old card going out (down-left) -->
      <path d="M65,150 L40,175" stroke="#f59e0b" stroke-width="2.5" fill="none" stroke-linecap="round" />
      <polygon points="38,178 46,173 40,167" fill="#f59e0b" />
      <!-- Arrow: new card coming in (from deck) -->
      <path d="M135,78 L115,85" stroke="#6b7280" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-dasharray="4,3" />
      <polygon points="113,88 121,83 117,77" fill="#6b7280" />
    </g>

    <!-- Sparkle effects on new card -->
    <circle cx="75" cy="75" r="2" fill="#fbbf24" class="swap-sparkle1" />
    <circle cx="125" cy="80" r="1.5" fill="#fbbf24" class="swap-sparkle2" />
    <circle cx="130" cy="150" r="1.8" fill="#fbbf24" class="swap-sparkle3" />

    <!-- Swap cycle icon -->
    <g class="swap-cycle" transform="translate(100, 180)">
      <path d="M-12,-4 A10,10 0 0,1 8,-8" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" />
      <polygon points="9,-12 12,-6 5,-7" fill="#9ca3af" />
      <path d="M12,4 A10,10 0 0,1 -8,8" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" />
      <polygon points="-9,12 -12,6 -5,7" fill="#9ca3af" />
    </g>
  </svg>
</template>

<script setup>
defineProps({ svgClass: { type: String, default: '' } });
</script>

<style scoped>
/* Old card: starts in center, slides down-left and fades out */
.swap-old-card {
  animation: swap-slide-out 2.2s ease-in-out infinite;
  transform-origin: center;
}
@keyframes swap-slide-out {
  0%, 10% { transform: translate(0, 0) rotate(0deg); opacity: 1; }
  50% { transform: translate(-45px, 50px) rotate(-15deg); opacity: 0.3; }
  55%, 100% { transform: translate(-45px, 50px) rotate(-15deg); opacity: 0; }
}

/* New card: starts at deck position, slides into center */
.swap-new-card {
  animation: swap-slide-in 2.2s ease-in-out infinite;
  transform-origin: center;
}
@keyframes swap-slide-in {
  0%, 40% { transform: translate(60px, -50px) scale(0.7); opacity: 0; }
  45% { opacity: 0.5; }
  75% { transform: translate(0, 0) scale(1); opacity: 1; }
  100% { transform: translate(0, 0) scale(1); opacity: 1; }
}

/* Arrows pulse in sync */
.swap-arrows {
  animation: swap-arrows-fade 2.2s ease-in-out infinite;
}
@keyframes swap-arrows-fade {
  0%, 5% { opacity: 0; }
  15% { opacity: 1; }
  70% { opacity: 1; }
  85%, 100% { opacity: 0; }
}

/* Sparkles on new card arrival */
.swap-sparkle1 {
  animation: swap-sparkle 2.2s ease-in-out infinite;
}
.swap-sparkle2 {
  animation: swap-sparkle 2.2s ease-in-out infinite 0.15s;
}
.swap-sparkle3 {
  animation: swap-sparkle 2.2s ease-in-out infinite 0.3s;
}
@keyframes swap-sparkle {
  0%, 60% { opacity: 0; transform: scale(0); }
  75% { opacity: 1; transform: scale(1.5); }
  90%, 100% { opacity: 0; transform: scale(0.5); }
}

/* Deck subtle bounce when card is taken */
.swap-deck {
  animation: swap-deck-bump 2.2s ease-in-out infinite;
}
@keyframes swap-deck-bump {
  0%, 35% { transform: translate(0, 0); }
  45% { transform: translate(0, 2px); }
  55% { transform: translate(0, 0); }
  100% { transform: translate(0, 0); }
}

/* Cycle icon spins slowly */
.swap-cycle {
  animation: swap-cycle-spin 2.2s linear infinite;
  transform-origin: 100px 180px;
}
@keyframes swap-cycle-spin {
  0% { transform: translate(0, 0) rotate(0deg); }
  100% { transform: translate(0, 0) rotate(360deg); }
}
</style>
