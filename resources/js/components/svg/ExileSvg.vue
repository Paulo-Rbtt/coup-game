<template>
  <!-- Exile: Skull with darkening -->
  <svg viewBox="0 0 200 200" :class="svgClass">
    <!-- Dark vortex background -->
    <circle cx="100" cy="100" r="70" fill="none" stroke="#374151" stroke-width="1.5" class="exile-ring1" />
    <circle cx="100" cy="100" r="55" fill="none" stroke="#4b5563" stroke-width="1" class="exile-ring2" />
    <circle cx="100" cy="100" r="40" fill="none" stroke="#6b7280" stroke-width="0.8" class="exile-ring3" />
    <!-- Skull -->
    <g class="exile-skull">
      <!-- Skull shape -->
      <ellipse cx="100" cy="85" rx="35" ry="38" fill="#e5e7eb" />
      <!-- Eye sockets -->
      <ellipse cx="86" cy="80" rx="10" ry="12" fill="#1f2937" />
      <ellipse cx="114" cy="80" rx="10" ry="12" fill="#1f2937" />
      <!-- Eye embers -->
      <circle cx="86" cy="80" r="4" fill="#dc2626" class="exile-ember-l" />
      <circle cx="114" cy="80" r="4" fill="#dc2626" class="exile-ember-r" />
      <!-- Nose -->
      <polygon points="97,92 103,92 100,98" fill="#1f2937" />
      <!-- Teeth -->
      <rect x="85" y="104" width="30" height="6" rx="1" fill="#e5e7eb" stroke="#9ca3af" stroke-width="0.5" />
      <line x1="91" y1="104" x2="91" y2="110" stroke="#9ca3af" stroke-width="0.5" />
      <line x1="97" y1="104" x2="97" y2="110" stroke="#9ca3af" stroke-width="0.5" />
      <line x1="103" y1="104" x2="103" y2="110" stroke="#9ca3af" stroke-width="0.5" />
      <line x1="109" y1="104" x2="109" y2="110" stroke="#9ca3af" stroke-width="0.5" />
    </g>
    <!-- Crossbones -->
    <g class="exile-bones">
      <line x1="55" y1="140" x2="145" y2="170" stroke="#d1d5db" stroke-width="6" stroke-linecap="round" />
      <line x1="145" y1="140" x2="55" y2="170" stroke="#d1d5db" stroke-width="6" stroke-linecap="round" />
      <circle cx="55" cy="140" r="5" fill="#d1d5db" />
      <circle cx="145" cy="140" r="5" fill="#d1d5db" />
      <circle cx="55" cy="170" r="5" fill="#d1d5db" />
      <circle cx="145" cy="170" r="5" fill="#d1d5db" />
    </g>
    <!-- Falling card -->
    <g class="exile-card">
      <rect x="140" y="40" width="25" height="35" rx="3" fill="#6366f1" opacity="0.7" />
      <text x="152" y="62" text-anchor="middle" font-size="16" fill="white">?</text>
    </g>
  </svg>
</template>

<script setup>
defineProps({ svgClass: { type: String, default: '' } });
</script>

<style scoped>
.exile-skull {
  animation: exile-float 3s ease-in-out infinite;
  transform-origin: center;
}
.exile-ember-l, .exile-ember-r {
  animation: exile-ember 1.5s ease-in-out infinite alternate;
}
.exile-ember-r { animation-delay: 0.5s; }
.exile-ring1 {
  animation: exile-ring-spin 8s linear infinite;
  transform-origin: center;
}
.exile-ring2 {
  animation: exile-ring-spin 6s linear infinite reverse;
  transform-origin: center;
}
.exile-ring3 {
  animation: exile-ring-spin 4s linear infinite;
  transform-origin: center;
}
.exile-bones {
  animation: exile-bones-shake 2s ease-in-out infinite;
  transform-origin: center;
}
.exile-card {
  animation: exile-card-fall 2s ease-in infinite;
}
@keyframes exile-float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-6px); }
}
@keyframes exile-ember {
  0% { opacity: 0.3; r: 3; }
  100% { opacity: 1; r: 5; }
}
@keyframes exile-ring-spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
@keyframes exile-bones-shake {
  0%, 100% { transform: rotate(0deg); }
  25% { transform: rotate(-2deg); }
  75% { transform: rotate(2deg); }
}
@keyframes exile-card-fall {
  0% { transform: translateY(-20px) rotate(0deg); opacity: 1; }
  100% { transform: translateY(30px) rotate(15deg); opacity: 0; }
}
</style>
