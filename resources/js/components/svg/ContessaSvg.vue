<template>
  <!-- Contessa: Noble lady with shield -->
  <svg viewBox="0 0 200 200" :class="svgClass">
    <defs>
      <linearGradient id="contessa-dress" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style="stop-color:#dc2626" />
        <stop offset="100%" style="stop-color:#b91c1c" />
      </linearGradient>
      <radialGradient id="contessa-shield-g" cx="50%" cy="50%" r="50%">
        <stop offset="0%" style="stop-color:#fbbf24" />
        <stop offset="100%" style="stop-color:#f59e0b" />
      </radialGradient>
    </defs>
    <!-- Dress -->
    <path d="M55,170 Q60,120 100,105 Q140,120 145,170 Z" fill="url(#contessa-dress)" class="contessa-dress" />
    <!-- Sash ribbon -->
    <path d="M75,125 Q100,120 125,125" stroke="#fbbf24" fill="none" stroke-width="3" stroke-linecap="round" />
    <!-- Head -->
    <circle cx="100" cy="75" r="25" fill="#fde68a" />
    <!-- Hair/tiara -->
    <path d="M75,70 Q78,48 90,50 Q95,42 100,48 Q105,42 110,50 Q122,48 125,70" fill="#7f1d1d" />
    <!-- Tiara gems -->
    <circle cx="90" cy="52" r="3" fill="#fbbf24" />
    <circle cx="100" cy="48" r="3.5" fill="#fbbf24" />
    <circle cx="110" cy="52" r="3" fill="#fbbf24" />
    <!-- Eyes -->
    <ellipse cx="90" cy="74" rx="4" ry="4.5" fill="#1e3a5f" />
    <ellipse cx="110" cy="74" rx="4" ry="4.5" fill="#1e3a5f" />
    <!-- Eyelashes -->
    <line x1="86" y1="70" x2="84" y2="67" stroke="#1e3a5f" stroke-width="1.5" />
    <line x1="114" y1="70" x2="116" y2="67" stroke="#1e3a5f" stroke-width="1.5" />
    <!-- Stern expression -->
    <line x1="92" y1="86" x2="108" y2="86" stroke="#7f1d1d" stroke-width="2" stroke-linecap="round" />
    <!-- Shield -->
    <g class="contessa-shield">
      <path d="M140,90 L165,90 L165,120 Q152,140 140,120 Z" fill="url(#contessa-shield-g)" stroke="#d97706" stroke-width="2" />
      <text x="152.5" y="112" text-anchor="middle" font-size="16" fill="#dc2626" font-weight="bold">✕</text>
    </g>
    <!-- Blocking aura -->
    <circle cx="100" cy="120" r="55" fill="none" stroke="#dc2626" stroke-width="1.5" opacity="0.3" class="contessa-aura" />
    <circle cx="100" cy="120" r="40" fill="none" stroke="#dc2626" stroke-width="1" opacity="0.2" class="contessa-aura2" />
  </svg>
</template>

<script setup>
defineProps({ svgClass: { type: String, default: '' } });
</script>

<style scoped>
.contessa-shield {
  animation: contessa-shield-raise 2s ease-in-out infinite;
  transform-origin: 152px 110px;
}
.contessa-aura {
  animation: contessa-aura-pulse 2s ease-in-out infinite;
}
.contessa-aura2 {
  animation: contessa-aura-pulse 2s ease-in-out infinite 0.5s;
}
.contessa-dress {
  animation: contessa-sway 3s ease-in-out infinite;
  transform-origin: center bottom;
}
@keyframes contessa-shield-raise {
  0%, 100% { transform: translateY(0) scale(1); }
  50% { transform: translateY(-5px) scale(1.1); }
}
@keyframes contessa-aura-pulse {
  0%, 100% { opacity: 0.1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(1.08); }
}
@keyframes contessa-sway {
  0%, 100% { transform: skewX(0deg); }
  50% { transform: skewX(1.5deg); }
}
</style>
