<template>
  <!-- Captain: Naval officer stealing coins -->
  <svg viewBox="0 0 200 200" :class="svgClass">
    <defs>
      <linearGradient id="captain-coat" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style="stop-color:#2563eb" />
        <stop offset="100%" style="stop-color:#1d4ed8" />
      </linearGradient>
    </defs>
    <!-- Body -->
    <rect x="65" y="110" width="70" height="60" rx="10" fill="url(#captain-coat)" class="captain-body" />
    <!-- Epaulettes -->
    <ellipse cx="70" cy="115" rx="12" ry="6" fill="#fbbf24" />
    <ellipse cx="130" cy="115" rx="12" ry="6" fill="#fbbf24" />
    <!-- Head -->
    <circle cx="100" cy="80" r="25" fill="#fcd34d" />
    <!-- Hat brim -->
    <ellipse cx="100" cy="63" rx="35" ry="8" fill="#1e3a5f" />
    <!-- Hat top -->
    <rect x="75" y="48" width="50" height="16" rx="5" fill="#1e3a5f" />
    <!-- Hat emblem -->
    <circle cx="100" cy="56" r="6" fill="#fbbf24" />
    <text x="100" y="59" text-anchor="middle" font-size="8" fill="#1e3a5f" font-weight="bold">⚓</text>
    <!-- Eyes -->
    <circle cx="90" cy="78" r="4" fill="#1e3a5f" />
    <circle cx="110" cy="78" r="4" fill="#1e3a5f" />
    <!-- Grin -->
    <path d="M88 90 Q100 98 112 90" stroke="#1e3a5f" fill="none" stroke-width="2" stroke-linecap="round" />
    <!-- Grabbing hands with coins -->
    <g class="captain-grab-left">
      <circle cx="45" cy="140" r="8" fill="#fcd34d" />
      <circle cx="38" cy="135" r="9" fill="#fbbf24" stroke="#f59e0b" stroke-width="1" />
      <text x="38" y="138" text-anchor="middle" font-size="10" fill="#92400e" font-weight="bold">$</text>
    </g>
    <g class="captain-grab-right">
      <circle cx="155" cy="140" r="8" fill="#fcd34d" />
      <circle cx="162" cy="135" r="9" fill="#fbbf24" stroke="#f59e0b" stroke-width="1" />
      <text x="162" y="138" text-anchor="middle" font-size="10" fill="#92400e" font-weight="bold">$</text>
    </g>
    <!-- Anchor symbol -->
    <text x="100" y="150" text-anchor="middle" font-size="24" fill="#93c5fd" opacity="0.4" class="captain-anchor">⚓</text>
  </svg>
</template>

<script setup>
defineProps({ svgClass: { type: String, default: '' } });
</script>

<style scoped>
.captain-grab-left {
  animation: captain-grab-l 2s ease-in-out infinite;
}
.captain-grab-right {
  animation: captain-grab-r 2s ease-in-out infinite;
}
.captain-anchor {
  animation: captain-anchor-spin 4s linear infinite;
  transform-origin: 100px 145px;
}
.captain-body {
  animation: captain-sway 3s ease-in-out infinite;
  transform-origin: center bottom;
}
@keyframes captain-grab-l {
  0%, 100% { transform: translateX(0); }
  50% { transform: translateX(-8px) rotate(-10deg); }
}
@keyframes captain-grab-r {
  0%, 100% { transform: translateX(0); }
  50% { transform: translateX(8px) rotate(10deg); }
}
@keyframes captain-anchor-spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
@keyframes captain-sway {
  0%, 100% { transform: rotate(0deg); }
  25% { transform: rotate(-2deg); }
  75% { transform: rotate(2deg); }
}
</style>
