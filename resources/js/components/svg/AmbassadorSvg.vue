<template>
  <!-- Ambassador: Diplomatic figure with scrolls -->
  <svg viewBox="0 0 200 200" :class="svgClass">
    <defs>
      <linearGradient id="ambassador-robe" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style="stop-color:#16a34a" />
        <stop offset="100%" style="stop-color:#15803d" />
      </linearGradient>
    </defs>
    <!-- Body/robe -->
    <path d="M60,165 Q65,110 100,100 Q135,110 140,165 Z" fill="url(#ambassador-robe)" class="amb-body" />
    <!-- Sash -->
    <path d="M80,120 Q100,115 120,120 Q115,145 100,155 Q85,145 80,120Z" fill="#22c55e" opacity="0.5" />
    <!-- Head -->
    <circle cx="100" cy="75" r="26" fill="#fde68a" />
    <!-- Spectacles -->
    <circle cx="88" cy="73" r="8" fill="none" stroke="#78350f" stroke-width="2" />
    <circle cx="112" cy="73" r="8" fill="none" stroke="#78350f" stroke-width="2" />
    <line x1="96" y1="73" x2="104" y2="73" stroke="#78350f" stroke-width="2" />
    <!-- Eyes behind glasses -->
    <circle cx="88" cy="73" r="3" fill="#1e3a5f" />
    <circle cx="112" cy="73" r="3" fill="#1e3a5f" />
    <!-- Calm smile -->
    <path d="M92 86 Q100 91 108 86" stroke="#78350f" fill="none" stroke-width="1.5" stroke-linecap="round" />
    <!-- Scroll left -->
    <g class="amb-scroll-left">
      <rect x="35" y="100" width="25" height="50" rx="4" fill="#fef3c7" stroke="#d97706" stroke-width="1" />
      <line x1="40" y1="110" x2="55" y2="110" stroke="#d97706" stroke-width="0.8" />
      <line x1="40" y1="116" x2="55" y2="116" stroke="#d97706" stroke-width="0.8" />
      <line x1="40" y1="122" x2="55" y2="122" stroke="#d97706" stroke-width="0.8" />
      <circle cx="47" cy="99" r="5" fill="#fef3c7" stroke="#d97706" stroke-width="1" />
      <circle cx="47" cy="151" r="5" fill="#fef3c7" stroke="#d97706" stroke-width="1" />
    </g>
    <!-- Scroll right -->
    <g class="amb-scroll-right">
      <rect x="140" y="105" width="25" height="45" rx="4" fill="#fef3c7" stroke="#d97706" stroke-width="1" />
      <line x1="145" y1="115" x2="160" y2="115" stroke="#d97706" stroke-width="0.8" />
      <line x1="145" y1="121" x2="160" y2="121" stroke="#d97706" stroke-width="0.8" />
      <circle cx="152" cy="104" r="5" fill="#fef3c7" stroke="#d97706" stroke-width="1" />
      <circle cx="152" cy="151" r="5" fill="#fef3c7" stroke="#d97706" stroke-width="1" />
    </g>
    <!-- Cards swapping arrows -->
    <g class="amb-swap">
      <path d="M70,50 Q100,35 130,50" stroke="#22c55e" fill="none" stroke-width="2.5" marker-end="url(#amb-arrow)" />
      <path d="M130,45 Q100,60 70,45" stroke="#22c55e" fill="none" stroke-width="2.5" marker-end="url(#amb-arrow)" />
    </g>
    <defs>
      <marker id="amb-arrow" viewBox="0 0 6 6" refX="5" refY="3" markerWidth="5" markerHeight="5" orient="auto">
        <polygon points="0,0 6,3 0,6" fill="#22c55e" />
      </marker>
    </defs>
  </svg>
</template>

<script setup>
defineProps({ svgClass: { type: String, default: '' } });
</script>

<style scoped>
.amb-scroll-left {
  animation: amb-scroll-float 2.5s ease-in-out infinite;
}
.amb-scroll-right {
  animation: amb-scroll-float 2.5s ease-in-out infinite reverse;
}
.amb-swap {
  animation: amb-swap-pulse 2s ease-in-out infinite;
}
.amb-body {
  animation: amb-breathe 3s ease-in-out infinite;
  transform-origin: center bottom;
}
@keyframes amb-scroll-float {
  0%, 100% { transform: translateY(0) rotate(0deg); }
  50% { transform: translateY(-5px) rotate(-3deg); }
}
@keyframes amb-swap-pulse {
  0%, 100% { opacity: 0.4; transform: scale(1); }
  50% { opacity: 1; transform: scale(1.05); }
}
@keyframes amb-breathe {
  0%, 100% { transform: scaleY(1); }
  50% { transform: scaleY(1.02); }
}
</style>
