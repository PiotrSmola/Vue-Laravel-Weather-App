<script setup>
// Dane One Call 3.0 (indeks UV, oficjalne ostrzeżenia, prognoza 8-dniowa, opady w najbliższej godzinie).
// Pokazuje tylko te części, dla których są dane; bez danych nie renderuje niczego.
import { computed } from 'vue';
import { useUnits } from '@/composables/useUnits';

const props = defineProps({
  data: { type: Object, default: null },
});

const { formatTemp } = useUnits();

const alerts = computed(() => props.data?.alerts || []);
const daily = computed(() => props.data?.daily || []);
const uvi = computed(() => (typeof props.data?.uvi === 'number' ? props.data.uvi : null));

// Opady w ciągu najbliższej godziny (mm/h) – tylko gdy jakieś są
const minutelyRain = computed(() => {
  const list = props.data?.minutely || [];
  if (!list.length) return null;
  const wet = list.filter((m) => m.precipitation > 0);
  if (!wet.length) return null;
  const first = wet[0];
  const minutes = Math.max(0, Math.round((first.dt - list[0].dt) / 60));
  return {
    max: Math.max(...wet.map((m) => m.precipitation)),
    minutes,
  };
});

const hasContent = computed(
  () => uvi.value !== null || alerts.value.length > 0 || daily.value.length > 0 || minutelyRain.value
);

const uvLevel = computed(() => {
  const v = uvi.value;
  if (v === null) return null;
  if (v < 3) return { label: 'niski', cls: 'low' };
  if (v < 6) return { label: 'umiarkowany', cls: 'moderate' };
  if (v < 8) return { label: 'wysoki', cls: 'high' };
  if (v < 11) return { label: 'bardzo wysoki', cls: 'very-high' };
  return { label: 'ekstremalny', cls: 'extreme' };
});

const tz = computed(() => props.data?.timezone_offset || 0);

const formatDay = (dt) =>
  new Date((dt + tz.value) * 1000).toLocaleDateString('pl-PL', {
    weekday: 'short',
    day: 'numeric',
    month: 'numeric',
    timeZone: 'UTC',
  });

const formatDateTime = (dt) =>
  dt
    ? new Date((dt + tz.value) * 1000).toLocaleString('pl-PL', {
        weekday: 'short',
        day: 'numeric',
        month: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'UTC',
      })
    : '';

const iconUrl = (icon) => `https://openweathermap.org/img/wn/${icon}@2x.png`;
</script>

<template>
  <section v-if="data && hasContent" class="onecall" aria-label="Dane One Call">
    <div v-if="alerts.length" class="onecall-alerts">
      <h3 class="onecall-title">Oficjalne ostrzeżenia</h3>
      <details v-for="(alert, i) in alerts" :key="i" class="onecall-alert">
        <summary>
          <strong>⚠️ {{ alert.event }}</strong>
          <span class="onecall-alert-time">{{ formatDateTime(alert.start) }} – {{ formatDateTime(alert.end) }}</span>
        </summary>
        <p v-if="alert.sender" class="onecall-alert-sender">Źródło: {{ alert.sender }}</p>
        <p class="onecall-alert-desc">{{ alert.description }}</p>
      </details>
    </div>

    <div v-if="uvLevel || minutelyRain" class="onecall-now">
      <div v-if="uvLevel" :class="['onecall-uv', `onecall-uv--${uvLevel.cls}`]">
        <span class="onecall-label">Indeks UV</span>
        <span class="onecall-value">{{ Math.round(uvi * 10) / 10 }} · {{ uvLevel.label }}</span>
      </div>
      <div v-if="minutelyRain" class="onecall-uv onecall-uv--rain">
        <span class="onecall-label">Opady w ciągu godziny</span>
        <span class="onecall-value">
          {{ minutelyRain.minutes === 0 ? 'teraz' : `za ok. ${minutelyRain.minutes} min` }} · do {{ Math.round(minutelyRain.max * 10) / 10 }} mm/h
        </span>
      </div>
    </div>

    <div v-if="daily.length" class="onecall-daily">
      <h3 class="onecall-title">Prognoza 8-dniowa</h3>
      <div class="onecall-days">
        <div v-for="day in daily" :key="day.dt" class="onecall-day">
          <div class="onecall-day-date">{{ formatDay(day.dt) }}</div>
          <img
            v-if="day.weather?.icon"
            :src="iconUrl(day.weather.icon)"
            :alt="day.weather.description"
            class="onecall-day-icon"
          />
          <div class="onecall-day-temp">
            {{ formatTemp(day.temp_max) }} <span class="onecall-day-min">{{ formatTemp(day.temp_min) }}</span>
          </div>
          <div v-if="day.pop" class="onecall-day-pop">💧 {{ Math.round(day.pop * 100) }}%</div>
          <div class="onecall-day-desc">{{ day.weather?.description }}</div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.onecall {
  padding: 1.5rem;
  background-color: #f3f4f6;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.onecall-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 1rem;
  text-align: center;
}

.onecall-alert {
  background-color: #fef2f2;
  border-left: 4px solid #ef4444;
  border-radius: 8px;
  padding: 0.75rem 1rem;
  margin-bottom: 0.5rem;
  color: #1f2937;
}

.onecall-alert summary {
  cursor: pointer;
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 0.75rem;
  align-items: baseline;
}

.onecall-alert-time,
.onecall-alert-sender {
  font-size: 0.8rem;
  color: #6b7280;
}

.onecall-alert-sender {
  margin: 0.5rem 0 0;
}

.onecall-alert-desc {
  margin: 0.5rem 0 0;
  font-size: 0.9rem;
  white-space: pre-line;
}

.onecall-now {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 0.75rem;
}

.onecall-uv {
  display: flex;
  flex-direction: column;
  background-color: white;
  border-radius: 8px;
  padding: 0.75rem 1rem;
  border-left: 4px solid #10b981;
}

.onecall-uv--moderate { border-left-color: #f59e0b; }
.onecall-uv--high { border-left-color: #f97316; }
.onecall-uv--very-high { border-left-color: #ef4444; }
.onecall-uv--extreme { border-left-color: #7c3aed; }
.onecall-uv--rain { border-left-color: #3b82f6; }

.onecall-label {
  font-size: 0.75rem;
  color: #6b7280;
}

.onecall-value {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1f2937;
}

.onecall-days {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 0.75rem;
}

.onecall-day {
  background-color: white;
  border-radius: 8px;
  padding: 0.75rem;
  text-align: center;
}

.onecall-day-date {
  font-weight: 600;
  color: #3b82f6;
  text-transform: capitalize;
}

.onecall-day-icon {
  width: 56px;
  height: 56px;
  margin: 0 auto;
}

.onecall-day-temp {
  font-size: 1.125rem;
  font-weight: 700;
  color: #1f2937;
}

.onecall-day-min {
  font-weight: 500;
  color: #6b7280;
  font-size: 0.95rem;
}

.onecall-day-pop {
  font-size: 0.8rem;
  color: #2563eb;
}

.onecall-day-desc {
  font-size: 0.8rem;
  color: #6b7280;
  text-transform: capitalize;
}
</style>
