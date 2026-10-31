<script setup>
// Wykres jakości powietrza (SVG): linie PM2.5 i PM10 + pasek kolorów indeksu AQI pod wykresem.
// points: [{ timestamp (ms), aqi, pm2_5, pm10 }] – posortowane rosnąco po czasie.
import { computed, ref } from "vue";
import { aqiLevel, formatConcentration, UNIT } from "@/utils/airQuality";

const props = defineProps({
  points: { type: Array, required: true },
  height: { type: Number, default: 200 },
});

const WIDTH = 1000;

const SERIES = [
  { key: "pm2_5", label: "PM2.5", color: "#7c3aed" },
  { key: "pm10", label: "PM10", color: "#0ea5e9", dashed: true },
];

const sorted = computed(() =>
  props.points
    .filter((p) => Number.isFinite(p.timestamp))
    .slice()
    .sort((a, b) => a.timestamp - b.timestamp)
);

const timeRange = computed(() => {
  const list = sorted.value;
  if (list.length === 0) return { min: 0, max: 1 };
  const min = list[0].timestamp;
  const max = list[list.length - 1].timestamp;
  return { min, max: max > min ? max : min + 3600 * 1000 };
});

// Typowy odstęp między punktami (mediana) – do pasków AQI i wykrywania przerw w danych
const typicalStep = computed(() => {
  const list = sorted.value;
  if (list.length < 2) return 3600 * 1000;
  const steps = [];
  for (let i = 1; i < list.length; i++) steps.push(list[i].timestamp - list[i - 1].timestamp);
  steps.sort((a, b) => a - b);
  return steps[Math.floor(steps.length / 2)] || 3600 * 1000;
});

const niceMax = (value) => {
  if (value <= 10) return 10;
  const magnitude = 10 ** Math.floor(Math.log10(value));
  const steps = [1, 2, 2.5, 5, 10];
  const step = steps.find((s) => s * magnitude >= value) || 10;
  return step * magnitude;
};

const yMax = computed(() => {
  let max = 0;
  for (const p of sorted.value) {
    for (const s of SERIES) {
      const v = Number(p[s.key]);
      if (Number.isFinite(v) && v > max) max = v;
    }
  }
  return niceMax(max * 1.1);
});

const yTicks = computed(() => [4, 3, 2, 1, 0].map((i) => (yMax.value * i) / 4));

const xOf = (timestamp) => {
  const { min, max } = timeRange.value;
  return ((timestamp - min) / (max - min)) * WIDTH;
};
const yOf = (value) => props.height - (Number(value) / yMax.value) * props.height;

// Linie podzielone na segmenty – przerwa w danych (brak pomiarów) nie jest łączona linią
const seriesPaths = computed(() =>
  SERIES.map((s) => {
    const gap = Math.max(typicalStep.value * 3, 2 * 3600 * 1000);
    const segments = [];
    let current = [];
    let prev = null;

    for (const p of sorted.value) {
      const v = Number(p[s.key]);
      if (p[s.key] === null || p[s.key] === undefined || !Number.isFinite(v)) {
        if (current.length) segments.push(current);
        current = [];
        prev = null;
        continue;
      }
      if (prev && p.timestamp - prev.timestamp > gap) {
        segments.push(current);
        current = [];
      }
      current.push(`${xOf(p.timestamp).toFixed(1)},${yOf(v).toFixed(1)}`);
      prev = p;
    }
    if (current.length) segments.push(current);

    return { ...s, segments: segments.map((seg) => seg.join(" ")) };
  })
);

// Paski AQI: każdy punkt zajmuje przedział do następnego punktu (maks. typowy odstęp)
const aqiBars = computed(() => {
  const list = sorted.value;
  return list.map((p, i) => {
    const next = list[i + 1];
    const end = next ? Math.min(next.timestamp, p.timestamp + typicalStep.value) : p.timestamp + typicalStep.value;
    const x = xOf(p.timestamp);
    const x2 = Math.min(WIDTH, xOf(end));
    return { x, width: Math.max(1, x2 - x), color: aqiLevel(p.aqi).color };
  });
});

const spanHours = computed(() => (timeRange.value.max - timeRange.value.min) / 3600000);

function formatTick(timestamp) {
  const date = new Date(timestamp);
  if (spanHours.value <= 36) {
    return date.toLocaleTimeString("pl-PL", { hour: "2-digit", minute: "2-digit" });
  }
  if (spanHours.value <= 24 * 8) {
    return date.toLocaleDateString("pl-PL", { weekday: "short", day: "numeric" }) +
      " " + date.toLocaleTimeString("pl-PL", { hour: "2-digit", minute: "2-digit" });
  }
  return date.toLocaleDateString("pl-PL", { day: "numeric", month: "short" });
}

const xTicks = computed(() => {
  const { min, max } = timeRange.value;
  const count = 5;
  return Array.from({ length: count }, (_, i) => {
    const t = min + ((max - min) * i) / (count - 1);
    return { left: (i / (count - 1)) * 100, label: formatTick(t) };
  });
});

// Podpowiedź po najechaniu / dotknięciu wykresu
const hoverIndex = ref(null);
const plot = ref(null);

function onPointer(event) {
  const el = plot.value;
  const list = sorted.value;
  if (!el || list.length === 0) return;
  const rect = el.getBoundingClientRect();
  const ratio = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
  const { min, max } = timeRange.value;
  const t = min + ratio * (max - min);

  let best = 0;
  for (let i = 1; i < list.length; i++) {
    if (Math.abs(list[i].timestamp - t) < Math.abs(list[best].timestamp - t)) best = i;
  }
  hoverIndex.value = best;
}

const hovered = computed(() => (hoverIndex.value === null ? null : sorted.value[hoverIndex.value]));
const hoverLeft = computed(() => (hovered.value ? (xOf(hovered.value.timestamp) / WIDTH) * 100 : 0));

function formatFull(timestamp) {
  return new Date(timestamp).toLocaleString("pl-PL", {
    weekday: "short",
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  });
}
</script>

<template>
  <div class="aq-chart">
    <div class="aq-chart-legend">
      <span v-for="s in SERIES" :key="s.key" class="aq-legend-item">
        <span class="aq-legend-line" :class="{ dashed: s.dashed }" :style="{ borderColor: s.color }"></span>
        {{ s.label }} ({{ UNIT }})
      </span>
      <span class="aq-legend-item">
        <span class="aq-legend-strip"></span>
        Indeks AQI
      </span>
    </div>

    <div class="aq-chart-area">
      <div class="aq-y-axis" :style="{ height: `${height}px` }">
        <div v-for="tick in yTicks" :key="tick" class="aq-axis-tick">{{ formatConcentration(tick) }}</div>
      </div>

      <div class="aq-plot-wrapper">
        <div
          ref="plot"
          class="aq-plot"
          :style="{ height: `${height}px` }"
          @pointermove="onPointer"
          @pointerdown="onPointer"
          @pointerleave="hoverIndex = null"
        >
          <svg width="100%" :height="height" :viewBox="`0 0 ${WIDTH} ${height}`" preserveAspectRatio="none">
            <line
              v-for="tick in yTicks"
              :key="`grid-${tick}`"
              x1="0"
              :x2="WIDTH"
              :y1="yOf(tick)"
              :y2="yOf(tick)"
              class="aq-grid"
              vector-effect="non-scaling-stroke"
            />
            <g v-for="s in seriesPaths" :key="s.key">
              <polyline
                v-for="(segment, i) in s.segments"
                :key="`${s.key}-${i}`"
                :points="segment"
                fill="none"
                :stroke="s.color"
                stroke-width="2"
                stroke-linejoin="round"
                :stroke-dasharray="s.dashed ? '6,4' : null"
                vector-effect="non-scaling-stroke"
              />
            </g>
            <line
              v-if="hovered"
              :x1="xOf(hovered.timestamp)"
              :x2="xOf(hovered.timestamp)"
              y1="0"
              :y2="height"
              class="aq-hover-line"
              vector-effect="non-scaling-stroke"
            />
          </svg>

          <div
            v-if="hovered"
            class="aq-tooltip"
            :class="{ 'aq-tooltip--left': hoverLeft > 60 }"
            :style="{ left: `${hoverLeft}%` }"
          >
            <div class="aq-tooltip-date">{{ formatFull(hovered.timestamp) }}</div>
            <div class="aq-tooltip-row">
              <span class="aq-dot" :style="{ backgroundColor: aqiLevel(hovered.aqi).color }"></span>
              AQI {{ hovered.aqi }} – {{ aqiLevel(hovered.aqi).label }}
            </div>
            <div class="aq-tooltip-row">PM2.5: {{ formatConcentration(hovered.pm2_5) }} {{ UNIT }}</div>
            <div class="aq-tooltip-row">PM10: {{ formatConcentration(hovered.pm10) }} {{ UNIT }}</div>
          </div>
        </div>

        <svg class="aq-strip" width="100%" height="10" :viewBox="`0 0 ${WIDTH} 10`" preserveAspectRatio="none">
          <rect v-for="(bar, i) in aqiBars" :key="i" :x="bar.x" y="0" :width="bar.width" height="10" :fill="bar.color" />
        </svg>

        <div class="aq-x-axis">
          <span
            v-for="(tick, i) in xTicks"
            :key="i"
            class="aq-x-tick"
            :class="{ first: i === 0, last: i === xTicks.length - 1 }"
            :style="{ left: `${tick.left}%` }"
          >
            {{ tick.label }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.aq-chart {
  background-color: #f8fafc;
  border-radius: 8px;
  padding: 1rem;
}

.aq-chart-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  font-size: 0.8rem;
  color: #475569;
  margin-bottom: 0.75rem;
}

.aq-legend-item {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.aq-legend-line {
  display: inline-block;
  width: 18px;
  border-top: 2px solid;
}

.aq-legend-line.dashed {
  border-top-style: dashed;
}

.aq-legend-strip {
  display: inline-block;
  width: 24px;
  height: 8px;
  border-radius: 2px;
  background: linear-gradient(90deg, #22c55e, #a3d633, #facc15, #f97316, #dc2626);
}

.aq-chart-area {
  display: flex;
  gap: 0.5rem;
}

.aq-y-axis {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  font-size: 0.7rem;
  color: #64748b;
  text-align: right;
  min-width: 2rem;
}

.aq-axis-tick {
  line-height: 1;
  transform: translateY(-0.1rem);
}

.aq-plot-wrapper {
  flex: 1;
  min-width: 0;
}

.aq-plot {
  position: relative;
  touch-action: pan-y;
  cursor: crosshair;
}

.aq-plot svg {
  display: block;
  overflow: visible;
}

.aq-grid {
  stroke: #e2e8f0;
  stroke-width: 1;
}

.aq-hover-line {
  stroke: #94a3b8;
  stroke-width: 1;
  stroke-dasharray: 3, 3;
}

.aq-strip {
  display: block;
  margin-top: 0.4rem;
  border-radius: 2px;
}

.aq-x-axis {
  position: relative;
  height: 1.5rem;
  margin-top: 0.25rem;
  font-size: 0.7rem;
  color: #64748b;
}

.aq-x-tick {
  position: absolute;
  top: 0;
  transform: translateX(-50%);
  white-space: nowrap;
}

.aq-x-tick.first {
  transform: none;
}

.aq-x-tick.last {
  transform: translateX(-100%);
}

/* Na wąskim ekranie tylko skrajne i środkowa etykieta */
@media (max-width: 480px) {
  .aq-x-tick:nth-child(2),
  .aq-x-tick:nth-child(4) {
    display: none;
  }
}

.aq-tooltip {
  position: absolute;
  top: 0.5rem;
  margin-left: 0.5rem;
  background-color: white;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  padding: 0.5rem 0.75rem;
  font-size: 0.75rem;
  color: #1f2937;
  pointer-events: none;
  white-space: nowrap;
  z-index: 2;
}

.aq-tooltip--left {
  transform: translateX(-100%);
  margin-left: -0.5rem;
}

.aq-tooltip-date {
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.aq-tooltip-row {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.aq-dot {
  display: inline-block;
  width: 0.6rem;
  height: 0.6rem;
  border-radius: 50%;
}
</style>
