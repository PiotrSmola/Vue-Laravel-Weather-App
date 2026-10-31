<script setup>
// Wykres liniowy wielu serii w czasie (jedna linia na miasto) – w stylu wykresu
// historycznego z WeatherDetails (ręczny SVG + osie w HTML).
// series: [{ label, color, points: [{ t: ms, v: liczba w jednostkach użytkownika }] }]
import { computed } from 'vue';

const props = defineProps({
  series: { type: Array, required: true },
  // Formatowanie wartości osi i podpowiedzi, np. v => `${Math.round(v)}°C`
  formatValue: { type: Function, required: true },
  ariaLabel: { type: String, default: 'Wykres' },
  // Minimalna rozpiętość osi Y (np. 2 stopnie), aby płaskie dane nie wyglądały na skoki
  minSpan: { type: Number, default: 1 },
  // Czy oś Y zaczyna się od zera (np. wiatr)
  zeroBased: { type: Boolean, default: false },
});

const chart = computed(() => {
  const all = props.series.flatMap(s => s.points).filter(p => p.v !== null && p.v !== undefined);
  if (!all.length) return null;

  const tMin = Math.min(...all.map(p => p.t));
  const tMax = Math.max(...all.map(p => p.t));
  let vMin = props.zeroBased ? 0 : Math.floor(Math.min(...all.map(p => p.v)));
  let vMax = Math.ceil(Math.max(...all.map(p => p.v)));
  if (vMax - vMin < props.minSpan) {
    const pad = (props.minSpan - (vMax - vMin)) / 2;
    vMax += pad;
    if (!props.zeroBased) vMin -= pad;
  }

  const x = (t) => tMax === tMin ? 50 : ((t - tMin) / (tMax - tMin)) * 100;
  const y = (v) => 200 - ((v - vMin) / (vMax - vMin)) * 200;

  const lines = props.series.map(s => ({
    label: s.label,
    color: s.color,
    points: s.points.filter(p => p.v !== null && p.v !== undefined).map(p => `${x(p.t)},${y(p.v)}`).join(' '),
  }));

  // Etykiety osi X: początki kolejnych dni (lokalnie)
  const ticks = [];
  const day = new Date(tMin);
  day.setHours(0, 0, 0, 0);
  day.setDate(day.getDate() + 1);
  while (day.getTime() <= tMax) {
    ticks.push({
      left: x(day.getTime()),
      label: day.toLocaleDateString('pl-PL', { weekday: 'short', day: 'numeric' }),
    });
    day.setDate(day.getDate() + 1);
  }

  // Obszary podpowiedzi: wspólne punkty czasu wszystkich serii
  const times = [...new Set(all.map(p => p.t))].sort((a, b) => a - b);
  const slots = times.map((t, i) => {
    const prev = i > 0 ? x(times[i - 1]) : x(t);
    const next = i < times.length - 1 ? x(times[i + 1]) : x(t);
    const left = i > 0 ? (prev + x(t)) / 2 : x(t);
    const right = i < times.length - 1 ? (x(t) + next) / 2 : x(t);
    const lines = [new Date(t).toLocaleString('pl-PL', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })];
    for (const s of props.series) {
      const point = s.points.find(p => p.t === t);
      if (point && point.v !== null && point.v !== undefined) lines.push(`${s.label}: ${props.formatValue(point.v)}`);
    }
    return { x: left, width: Math.max(right - left, 0.5), title: lines.join('\n') };
  });

  return {
    lines,
    ticks,
    slots,
    axis: [0, 1, 2, 3, 4].map(i => vMax - ((vMax - vMin) * i) / 4),
  };
});
</script>

<template>
  <div class="compare-chart">
    <div class="chart-legend-labels">
      <div v-for="s in series" :key="s.label" class="legend-label" :style="{ color: s.color }">● {{ s.label }}</div>
    </div>

    <div v-if="chart">
      <div class="chart-area">
        <div class="y-axis">
          <div v-for="(value, i) in chart.axis" :key="i" class="axis-tick">{{ formatValue(value) }}</div>
        </div>
        <div class="chart-svg-container">
          <svg width="100%" height="220" viewBox="0 0 100 200" preserveAspectRatio="none" role="img" :aria-label="ariaLabel">
            <polyline v-for="line in chart.lines" :key="line.label" :points="line.points" fill="none"
              :stroke="line.color" stroke-width="2.5" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
            <rect v-for="(slot, i) in chart.slots" :key="`slot-${i}`" class="chart-slot" :x="slot.x" y="0"
              :width="slot.width" height="200" fill="transparent">
              <title>{{ slot.title }}</title>
            </rect>
          </svg>
        </div>
      </div>
      <div class="x-axis">
        <div v-for="(tick, i) in chart.ticks" :key="i" class="x-tick" :style="{ left: `${tick.left}%` }">{{ tick.label }}</div>
      </div>
    </div>
    <p v-else class="ui-muted chart-empty">Brak danych do wykresu</p>
  </div>
</template>

<style scoped>
.compare-chart {
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

.chart-area {
  display: flex;
  height: 220px;
}

.y-axis {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 10px 0;
  font-size: 0.75rem;
  color: #64748b;
  width: 60px;
  flex-shrink: 0;
}

.axis-tick {
  height: 1px;
  white-space: nowrap;
}

.chart-svg-container {
  flex: 1;
  min-width: 0;
  border-left: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
  background-color: #f8fafc;
}

.chart-slot {
  cursor: help;
}

.chart-slot:hover {
  fill: rgba(59, 130, 246, 0.08);
}

.x-axis {
  position: relative;
  height: 24px;
  margin-left: 60px;
  font-size: 0.75rem;
  color: #64748b;
}

.x-tick {
  position: absolute;
  top: 0.4rem;
  transform: translateX(-50%);
  white-space: nowrap;
}

.chart-empty {
  text-align: center;
  padding: 2rem 0;
}

@media (max-width: 480px) {
  .compare-chart {
    padding: 1rem 0.75rem;
  }

  .x-tick {
    font-size: 0.65rem;
  }
}
</style>
