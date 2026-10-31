<script setup>
// Zgrupowane słupki: dla każdej grupy (np. dnia) po jednym słupku na miasto.
// groups: [{ label, values: [{ label, color, value }] }], max – wartość odpowiadająca 100% wysokości
defineProps({
  groups: { type: Array, required: true },
  legend: { type: Array, required: true }, // [{ label, color }]
  max: { type: Number, default: 100 },
  formatValue: { type: Function, required: true },
});
</script>

<template>
  <div class="compare-bars">
    <div class="chart-legend-labels">
      <div v-for="item in legend" :key="item.label" class="legend-label" :style="{ color: item.color }">● {{ item.label }}</div>
    </div>

    <div class="bars-area" role="img" aria-label="Wykres słupkowy">
      <div v-for="group in groups" :key="group.label" class="bar-group">
        <div class="bar-columns">
          <div v-for="item in group.values" :key="item.label" class="bar"
            :style="{ height: `${Math.max(0, Math.min(1, (item.value || 0) / max)) * 100}%`, backgroundColor: item.color }"
            :title="`${group.label} – ${item.label}: ${formatValue(item.value)}`">
            <span v-if="item.value" class="bar-value">{{ formatValue(item.value) }}</span>
          </div>
        </div>
        <div class="bar-label">{{ group.label }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.compare-bars {
  background-color: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.chart-legend-labels {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.5rem 1.5rem;
  margin-bottom: 1rem;
}

.legend-label {
  font-weight: 500;
  font-size: 0.9rem;
}

.bars-area {
  display: flex;
  gap: 0.75rem;
  overflow-x: auto;
  padding-top: 1.25rem;
}

.bar-group {
  flex: 1 0 70px;
  display: flex;
  flex-direction: column;
  align-items: stretch;
}

.bar-columns {
  height: 160px;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 3px;
  border-bottom: 1px solid #e2e8f0;
  background-color: #f8fafc;
  border-radius: 4px 4px 0 0;
  padding: 0 4px;
}

.bar {
  position: relative;
  flex: 1;
  max-width: 22px;
  min-height: 2px;
  border-radius: 3px 3px 0 0;
  transition: height 0.3s;
}

.bar-value {
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  font-size: 0.6rem;
  color: #475569;
  white-space: nowrap;
  padding-bottom: 2px;
}

.bar-label {
  text-align: center;
  font-size: 0.75rem;
  color: #64748b;
  margin-top: 0.4rem;
  white-space: nowrap;
}

@media (max-width: 480px) {
  .compare-bars {
    padding: 1rem 0.75rem;
  }

  .bar-value {
    display: none;
  }
}
</style>
