<template>
  <!-- Coup: Explosive military strike -->
  <svg viewBox="0 0 200 200" :class="svgClass">
    <!-- Explosion bursts -->
    <g class="coup-explosion">
      <polygon points="100,20 115,70 170,60 130,95 155,150 100,120 45,150 70,95 30,60 85,70" fill="#fbbf24" opacity="0.8" />
      <polygon points="100,35 112,75 155,67 125,95 145,140 100,115 55,140 75,95 45,67 88,75" fill="#f59e0b" opacity="0.9" />
    </g>
    <!-- Sword -->
    <g class="coup-sword">
      <rect x="97" y="50" width="6" height="60" rx="1" fill="#e2e8f0" />
      <polygon points="94,50 106,50 100,35" fill="#f1f5f9" />
      <rect x="88" y="108" width="24" height="6" rx="2" fill="#78350f" />
      <rect x="95" y="114" width="10" height="14" rx="2" fill="#451a03" />
    </g>
    <!-- Impact rings -->
    <circle cx="100" cy="100" r="60" fill="none" stroke="#fbbf24" stroke-width="2" class="coup-ring1" />
    <circle cx="100" cy="100" r="75" fill="none" stroke="#f59e0b" stroke-width="1.5" class="coup-ring2" />
    <!-- "7" coins cost -->
    <g class="coup-cost">
      <circle cx="100" cy="170" r="15" fill="#fbbf24" stroke="#f59e0b" stroke-width="2" />
      <text x="100" y="176" text-anchor="middle" font-size="16" fill="#92400e" font-weight="bold">7</text>
    </g>
  </svg>
</template>

<script setup>
defineProps({ svgClass: { type: String, default: '' } });
</script>

<style scoped>
.coup-explosion {
  animation: coup-explode 1.5s ease-out infinite;
  transform-origin: center;
}
.coup-sword {
  animation: coup-strike 1.5s ease-in-out infinite;
  transform-origin: 100px 110px;
}
.coup-ring1 {
  animation: coup-ring-expand 1.5s ease-out infinite;
  transform-origin: center;
}
.coup-ring2 {
  animation: coup-ring-expand 1.5s ease-out infinite 0.3s;
  transform-origin: center;
}
.coup-cost {
  animation: coup-cost-bounce 1s ease-in-out infinite;
}
@keyframes coup-explode {
  0% { transform: scale(0.8); opacity: 0; }
  20% { transform: scale(1.1); opacity: 1; }
  100% { transform: scale(1); opacity: 0.8; }
}
@keyframes coup-strike {
  0%, 60% { transform: translateY(-20px); }
  80% { transform: translateY(5px); }
  100% { transform: translateY(0); }
}
@keyframes coup-ring-expand {
  0% { transform: scale(0.3); opacity: 0.8; }
  100% { transform: scale(1.3); opacity: 0; }
}
@keyframes coup-cost-bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-5px); }
}
</style>
