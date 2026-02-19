<template>
  <!-- Challenge: Clash of wills -->
  <svg viewBox="0 0 200 200" :class="svgClass">
    <!-- Left hand pointing -->
    <g class="challenge-hand-left">
      <circle cx="55" cy="100" r="15" fill="#fcd34d" />
      <rect x="55" y="92" width="35" height="16" rx="4" fill="#fcd34d" />
      <polygon points="90,92 105,100 90,108" fill="#fcd34d" />
    </g>
    <!-- Right hand pointing -->
    <g class="challenge-hand-right">
      <circle cx="145" cy="100" r="15" fill="#fcd34d" />
      <rect x="110" y="92" width="35" height="16" rx="4" fill="#fcd34d" />
      <polygon points="110,92 95,100 110,108" fill="#fcd34d" />
    </g>
    <!-- "VS" text -->
    <g class="challenge-vs">
      <circle cx="100" cy="100" r="22" fill="#dc2626" />
      <text x="100" y="107" text-anchor="middle" font-size="18" fill="white" font-weight="black">VS</text>
    </g>
    <!-- Lightning bolts -->
    <polygon points="75,40 85,70 78,70 88,95 72,68 80,68" fill="#fbbf24" class="challenge-bolt1" />
    <polygon points="125,40 115,70 122,70 112,95 128,68 120,68" fill="#fbbf24" class="challenge-bolt2" />
    <!-- Question marks -->
    <text x="50" y="55" font-size="20" fill="#f59e0b" class="challenge-q1">?</text>
    <text x="140" y="55" font-size="20" fill="#f59e0b" class="challenge-q2">?</text>
    <!-- Exclamation -->
    <text x="100" y="155" text-anchor="middle" font-size="28" fill="#fbbf24" font-weight="bold" class="challenge-exclaim">!</text>
  </svg>
</template>

<script setup>
defineProps({ svgClass: { type: String, default: '' } });
</script>

<style scoped>
.challenge-hand-left {
  animation: challenge-push-l 1.5s ease-in-out infinite;
}
.challenge-hand-right {
  animation: challenge-push-r 1.5s ease-in-out infinite;
}
.challenge-vs {
  animation: challenge-vs-pulse 1s ease-in-out infinite;
  transform-origin: center;
}
.challenge-bolt1, .challenge-bolt2 {
  animation: challenge-flash 0.8s ease-in-out infinite alternate;
}
.challenge-bolt2 { animation-delay: 0.4s; }
.challenge-q1, .challenge-q2 {
  animation: challenge-float 2s ease-in-out infinite;
}
.challenge-q2 { animation-delay: 0.5s; }
.challenge-exclaim {
  animation: challenge-exclaim-pop 1.2s ease-in-out infinite;
}
@keyframes challenge-push-l {
  0%, 100% { transform: translateX(0); }
  50% { transform: translateX(10px); }
}
@keyframes challenge-push-r {
  0%, 100% { transform: translateX(0); }
  50% { transform: translateX(-10px); }
}
@keyframes challenge-vs-pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.15); }
}
@keyframes challenge-flash {
  0% { opacity: 0.3; }
  100% { opacity: 1; }
}
@keyframes challenge-float {
  0%, 100% { transform: translateY(0); opacity: 0.5; }
  50% { transform: translateY(-8px); opacity: 1; }
}
@keyframes challenge-exclaim-pop {
  0%, 100% { transform: scale(1); opacity: 0.6; }
  50% { transform: scale(1.3); opacity: 1; }
}
</style>
