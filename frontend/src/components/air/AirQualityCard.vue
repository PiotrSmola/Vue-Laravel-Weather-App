<script setup>
// Aktualna jakość powietrza dla miasta: poziom AQI ze skalą kolorów, zalecenia, składniki.
import { computed, ref, watch } from "vue";
import { fetchAirQuality } from "@/api/air";
import {
  AQI_LEVELS,
  aqiLevel,
  EUROPEAN_COLORS,
  POLISH_COLORS,
  POLLUTANTS,
  UNIT,
  formatConcentration,
  pollutantName,
  scaleColor,
} from "@/utils/airQuality";

const props = defineProps({
  // ID miasta w OpenWeather
  cityId: { type: [Number, String], required: true },
});

const data = ref(null);
const loading = ref(false);
const error = ref(null);
let requestId = 0;

async function load() {
  const id = ++requestId;
  loading.value = true;
  error.value = null;
  try {
    const result = await fetchAirQuality(props.cityId);
    if (id === requestId) data.value = result;
  } catch (err) {
    if (id !== requestId) return;
    data.value = null;
    error.value = err.status === 404 && err.data?.error ? err.data.error : "Nie udało się pobrać danych o jakości powietrza";
  } finally {
    if (id === requestId) loading.value = false;
  }
}

watch(() => props.cityId, () => props.cityId && load(), { immediate: true });

const level = computed(() => aqiLevel(data.value?.aqi));

const measuredAt = computed(() =>
  data.value
    ? new Date(data.value.timestamp).toLocaleString("pl-PL", {
        day: "numeric",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
      })
    : ""
);

// Kolejność wyświetlania składników – pyły na początku
const MAIN = ["pm2_5", "pm10"];
const OTHER = ["no2", "o3", "so2", "co", "no", "nh3"];

const components = computed(() => data.value?.components || {});

const scale = Object.entries(AQI_LEVELS).map(([value, info]) => ({ value: Number(value), ...info }));

const indexes = computed(() => {
  const list = [];
  const european = data.value?.indexes?.european;
  const polish = data.value?.indexes?.polish;
  if (european) {
    list.push({
      key: "european",
      name: "Indeks europejski (EEA)",
      level: european.level,
      max: 6,
      label: european.label,
      pollutant: european.pollutant,
      color: scaleColor(EUROPEAN_COLORS, european.level),
    });
  }
  if (polish) {
    list.push({
      key: "polish",
      name: "Polski indeks (GIOŚ)",
      level: polish.level,
      max: 6,
      label: polish.label,
      pollutant: polish.pollutant,
      color: scaleColor(POLISH_COLORS, polish.level),
    });
  }
  return list;
});
</script>

<template>
  <section class="aq-card">
    <div class="aq-card-header">
      <h2 class="aq-card-title">Jakość powietrza</h2>
      <span v-if="data" class="aq-card-time">Pomiar: {{ measuredAt }}</span>
    </div>

    <div v-if="loading && !data" class="aq-state">
      <div class="ui-spinner"></div>
      <p>Ładowanie danych o jakości powietrza...</p>
    </div>

    <div v-else-if="error" class="aq-state aq-state--error">
      <p>{{ error }}</p>
      <button class="ui-btn ui-btn--sm" @click="load">Spróbuj ponownie</button>
    </div>

    <template v-else-if="data">
      <div class="aq-summary">
        <div class="aq-level" :style="{ backgroundColor: level.color, color: level.text }">
          <span class="aq-level-value">{{ data.aqi }}</span>
          <span class="aq-level-max">/ 5</span>
        </div>
        <div class="aq-summary-text">
          <div class="aq-label" :style="{ color: level.color }">{{ data.label }}</div>
          <div class="aq-scale-name">Indeks jakości powietrza (OpenWeather)</div>
          <div v-if="data.dominant_pollutant" class="aq-dominant">
            Dominujące zanieczyszczenie:
            <strong>{{ data.dominant_pollutant.name }}</strong>
            ({{ formatConcentration(data.dominant_pollutant.value) }} {{ UNIT }})
          </div>
        </div>
      </div>

      <div class="aq-scale" role="img" :aria-label="`Poziom ${data.aqi} z 5: ${data.label}`">
        <div
          v-for="item in scale"
          :key="item.value"
          class="aq-scale-segment"
          :class="{ active: item.value === data.aqi }"
          :style="{ backgroundColor: item.color }"
        >
          <span class="aq-scale-label">{{ item.label }}</span>
        </div>
      </div>

      <div class="aq-advice" :style="{ borderLeftColor: level.color }">
        <p>{{ level.advice }}</p>
        <p v-if="level.sensitive" class="aq-advice-sensitive">
          <strong>Osoby wrażliwe:</strong> {{ level.sensitive }}
        </p>
      </div>

      <div class="aq-main-components">
        <div v-for="key in MAIN" :key="key" class="aq-main-item">
          <div class="aq-main-name">{{ POLLUTANTS[key].name }}</div>
          <div class="aq-main-value">
            {{ formatConcentration(components[key]) }} <span class="aq-unit">{{ UNIT }}</span>
          </div>
          <div class="aq-main-desc">{{ POLLUTANTS[key].description }}</div>
        </div>
      </div>

      <div class="aq-components">
        <div v-for="key in OTHER" :key="key" class="aq-component" :title="POLLUTANTS[key].description">
          <div class="aq-component-name">{{ POLLUTANTS[key].name }}</div>
          <div class="aq-component-value">{{ formatConcentration(components[key]) }}</div>
        </div>
      </div>
      <p class="aq-units-note">Stężenia w {{ UNIT }}</p>

      <div v-if="indexes.length" class="aq-indexes">
        <div v-for="index in indexes" :key="index.key" class="aq-index">
          <span class="aq-index-badge" :style="{ backgroundColor: index.color }">{{ index.level }}</span>
          <div>
            <div class="aq-index-name">{{ index.name }}</div>
            <div class="aq-index-value">
              {{ index.label }}
              <span v-if="index.level > 1" class="aq-index-pollutant">({{ pollutantName(index.pollutant) }})</span>
            </div>
          </div>
        </div>
        <p class="aq-units-note">
          Indeksy europejski i polski są szacowane na podstawie progów stężeń i danych modelowych OpenWeather – nie są
          oficjalnym komunikatem EEA ani GIOŚ.
        </p>
      </div>
    </template>
  </section>
</template>

<style scoped>
.aq-card {
  background-color: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  padding: 1.5rem;
  margin-bottom: 1rem;
}

.aq-card-header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: baseline;
  gap: 0.5rem;
  margin-bottom: 1.25rem;
}

.aq-card-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
}

.aq-card-time {
  font-size: 0.875rem;
  color: #6b7280;
}

.aq-state {
  padding: 1.5rem;
  background-color: #f3f4f6;
  border-radius: 8px;
  text-align: center;
  color: #4b5563;
}

.aq-state--error {
  border-left: 4px solid #ef4444;
}

.aq-state .ui-btn {
  margin-top: 0.75rem;
}

.aq-summary {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  margin-bottom: 1.25rem;
}

.aq-level {
  flex-shrink: 0;
  width: 84px;
  height: 84px;
  border-radius: 50%;
  display: flex;
  align-items: baseline;
  justify-content: center;
  padding-top: 1.35rem;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.aq-level-value {
  font-size: 2.25rem;
  font-weight: 700;
  line-height: 1;
}

.aq-level-max {
  font-size: 0.875rem;
  opacity: 0.85;
  margin-left: 0.15rem;
}

.aq-label {
  font-size: 1.6rem;
  font-weight: 700;
  line-height: 1.2;
  filter: brightness(0.85);
}

.aq-scale-name {
  font-size: 0.8rem;
  color: #6b7280;
}

.aq-dominant {
  margin-top: 0.4rem;
  font-size: 0.9rem;
  color: #374151;
}

.aq-scale {
  display: flex;
  gap: 3px;
  margin-bottom: 1.25rem;
}

.aq-scale-segment {
  flex: 1;
  height: 10px;
  border-radius: 3px;
  opacity: 0.35;
  position: relative;
  margin-bottom: 1.25rem;
}

.aq-scale-segment.active {
  opacity: 1;
  height: 14px;
  margin-top: -2px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
}

.aq-scale-label {
  position: absolute;
  top: calc(100% + 0.25rem);
  left: 0;
  right: 0;
  text-align: center;
  font-size: 0.7rem;
  color: #6b7280;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.aq-scale-segment.active .aq-scale-label {
  color: #1f2937;
  font-weight: 600;
}

.aq-advice {
  background-color: #f8fafc;
  border-left: 4px solid;
  border-radius: 6px;
  padding: 0.75rem 1rem;
  color: #374151;
  font-size: 0.925rem;
  margin-bottom: 1.25rem;
}

.aq-advice-sensitive {
  margin-top: 0.4rem;
  color: #4b5563;
}

.aq-main-components {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.aq-main-item {
  background-color: #f1f5f9;
  border-radius: 8px;
  padding: 0.875rem 1rem;
}

.aq-main-name {
  font-size: 0.875rem;
  font-weight: 600;
  color: #475569;
}

.aq-main-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
}

.aq-unit {
  font-size: 0.8rem;
  font-weight: 500;
  color: #64748b;
}

.aq-main-desc {
  font-size: 0.75rem;
  color: #6b7280;
}

.aq-components {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.5rem;
}

@media (min-width: 640px) {
  .aq-components {
    grid-template-columns: repeat(6, 1fr);
  }
}

.aq-component {
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0.5rem;
  text-align: center;
}

.aq-component-name {
  font-size: 0.75rem;
  color: #64748b;
}

.aq-component-value {
  font-weight: 600;
  color: #1f2937;
}

.aq-units-note {
  font-size: 0.75rem;
  color: #9ca3af;
  margin-top: 0.4rem;
}

.aq-indexes {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid #e2e8f0;
}

@media (min-width: 640px) {
  .aq-indexes {
    grid-template-columns: 1fr 1fr;
  }

  .aq-indexes .aq-units-note {
    grid-column: 1 / -1;
  }
}

.aq-index {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.aq-index-badge {
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border-radius: 6px;
  color: white;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
}

.aq-index-name {
  font-size: 0.8rem;
  color: #6b7280;
}

.aq-index-value {
  font-weight: 600;
  color: #1f2937;
}

.aq-index-pollutant {
  font-weight: 400;
  color: #6b7280;
}

@media (max-width: 480px) {
  .aq-card {
    padding: 1rem;
  }

  .aq-summary {
    gap: 0.875rem;
  }

  .aq-level {
    width: 68px;
    height: 68px;
    padding-top: 1rem;
  }

  .aq-level-value {
    font-size: 1.75rem;
  }

  .aq-label {
    font-size: 1.3rem;
  }

  .aq-scale-label {
    font-size: 0.6rem;
  }
}
</style>
