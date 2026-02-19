<template>
  <!-- Assassin: Dark figure with dagger -->
  <svg viewBox="0 0 200 200" :class="svgClass">
    <defs>
      <linearGradient id="assassin-cloak" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style="stop-color:#be123c" />
        <stop offset="100%" style="stop-color:#9f1239" />
      </linearGradient>
    </defs>
    <!-- Cloak body -->
    <path d="M55,160 Q60,105 100,95 Q140,105 145,160 Z" fill="url(#assassin-cloak)" class="assassin-cloak" />
    <!-- Hood/head -->
    <path d="M70,100 Q72,60 100,55 Q128,60 130,100 Q115,95 100,93 Q85,95 70,100Z" fill="#1e1e1e" class="assassin-hood" />
    <!-- Eyes (glowing) -->
    <ellipse cx="88" cy="82" rx="5" ry="3" fill="#be123c" class="assassin-eye-left" />
    <ellipse cx="112" cy="82" rx="5" ry="3" fill="#be123c" class="assassin-eye-right" />
    <!-- Dagger -->
    <g class="assassin-dagger">
      <rect x="138" y="85" width="4" height="30" rx="1" fill="#94a3b8" transform="rotate(25,140,100)" />
      <polygon points="137,84 143,84 140,72" fill="#e2e8f0" transform="rotate(25,140,100)" />
      <rect x="135" y="114" width="10" height="6" rx="2" fill="#78350f" transform="rotate(25,140,100)" />
    </g>
    <!-- Blood drops -->
    <g class="assassin-blood">
      <circle cx="145" cy="140" r="4" fill="#dc2626" opacity="0.8" />
      <circle cx="155" cy="155" r="3" fill="#dc2626" opacity="0.6" />
      <circle cx="140" cy="160" r="2.5" fill="#dc2626" opacity="0.5" />
    </g>
    <!-- Slash effect -->
    <path d="M60,70 Q100,90 160,60" stroke="#be123c" fill="none" stroke-width="3" stroke-linecap="round" class="assassin-slash" opacity="0.7" />
  </svg>
</template>

<script setup>
defineProps({ svgClass: { type: String, default: '' } });
</script>

<style scoped>
.assassin-eye-left, .assassin-eye-right {
  animation: assassin-glow 2s ease-in-out infinite alternate;
}
.assassin-dagger {
  animation: assassin-stab 1.8s ease-in-out infinite;
  transform-origin: 140px 100px;
}
.assassin-blood {
  animation: assassin-drip 2s ease-in infinite;
}
.assassin-slash {
  stroke-dasharray: 120;
  stroke-dashoffset: 120;
  animation: assassin-slash-draw 1.5s ease-out infinite;
}
.assassin-cloak {
  animation: assassin-sway 3s ease-in-out infinite;
  transform-origin: center bottom;
}
@keyframes assassin-glow {
  0% { opacity: 0.5; }
  100% { opacity: 1; }
}
@keyframes assassin-stab {
  0%, 70%, 100% { transform: rotate(0deg); }
  80% { transform: rotate(-15deg) translateX(-5px); }
  90% { transform: rotate(5deg) translateX(3px); }
}
@keyframes assassin-drip {
  0% { opacity: 0; transform: translateY(-10px); }
  50% { opacity: 0.7; }
  100% { opacity: 0; transform: translateY(10px); }
}
@keyframes assassin-slash-draw {
  0% { stroke-dashoffset: 120; opacity: 0; }
  30% { opacity: 0.9; }
  60% { stroke-dashoffset: 0; opacity: 0.7; }
  100% { stroke-dashoffset: 0; opacity: 0; }
}
@keyframes assassin-sway {
  0%, 100% { transform: skewX(0deg); }
  50% { transform: skewX(2deg); }
}
</style>
