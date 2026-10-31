<script setup>
// Prognoza jakości powietrza (~4–5 dni, co godzinę): podsumowanie dzienne + wykres.
import { computed, ref, watch } from "vue";
import { fetchAirForecast } from "@/api/air";
import { aqiLevel } from "@/utils/airQuality";
import AirQualityChart from "./AirQualityChart.vue";

const props = defineProps({
  cityId: { type: [Number, String], required: true },
});

const forecast = ref(null);
const loading = ref(false);
const error = ref(null);
let requestId = 0;

async function load() {
  const id = ++requestId;
  loading.value = true;
  error.value = null;
  try {
    const result = await fetchAirForecast(props.cityId);
    if (id === requestId) forecast.value = result;
  } catch {
    if (id !== requestId) return;
    forecast.value = null;
    error.value = "Nie udało się pobrać prognozy jakości powietrza";
  } finally {
    if (id === requestId) loading.value = false;
  }
}

watch(() => props.cityId, () => props.cityId && load(), { immediate: true });

const points = computed(() => forecast.value?.list || []);

// Najgorszy poziom AQI w każdym dniu prognozy
const days = computed(() => {
  const map = new Map();
  for (const p of points.value) {
    const date = new Date(p.timestamp);
    const key = date.toDateString();
    const entry = map.get(key) || { key, date, aqi: 0, pm2_5: 0, pm10: 0 };
    entry.aqi = Math.max(entry.aqi, p.aqi || 0);
    entry.pm2_5 = Math.max(entry.pm2_5, p.pm2_5 || 0);
    entry.pm10 = Math.max(entry.pm10, p.pm10 || 0);
    map.set(key, entry);
  }
  const today = new Date().toDateString();
  return [...map.values()].map((d) => ({
    ...d,
    name: d.key === today ? "Dziś" : d.date.toLocaleDateString("pl-PL", { weekday: "short", day: "numeric" }),
    level: aqiLevel(d.aqi),
  }));
});
</script>

<template>
  <section class="aq-section">
    <h2 class="aq-section-title">Prognoza jakości powietrza</h2>

    <div v-if="loading && !forecast" class="aq-state">
      <div class="ui-spinner"></div>
      <p>Ładowanie prognozy...</p>
    </div>

    <div v-else-if="error" class="aq-state aq-state--error">
      <p>{{ error }}</p>
      <button class="ui-btn ui-btn--sm" @click="load">Spróbuj ponownie</button>
    </div>

    <div v-else-if="points.length === 0" class="aq-state">
      <p>Brak prognozy jakości powietrza dla tego miasta.</p>
    </div>

    <template v-else>
      <div class="aq-days">
        <div v-for="day in days" :key="day.key" class="aq-day">
          <div class="aq-day-name">{{ day.name }}</div>
          <div class="aq-day-badge" :style="{ backgroundColor: day.level.color, color: day.level.text }">
            {{ day.aqi }}
          </div>
          <div class="aq-day-label">{{ day.level.label }}</div>
        </div>
      </div>
      <p class="aq-note">Najgorszy przewidywany poziom w ciągu dnia (skala 1–5).</p>

      <AirQualityChart :points="points" />
    </template>
  </section>
</template>

<style scoped>
.aq-section {
  background-color: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  padding: 1.5rem;
  margin-bottom: 1rem;
}

.aq-section-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 1rem;
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

.aq-days {
  display: flex;
  gap: 0.5rem;
  overflow-x: auto;
  padding-bottom: 0.25rem;
}

.aq-day {
  flex: 1;
  min-width: 70px;
  background-color: #f8fafc;
  border-radius: 8px;
  padding: 0.6rem 0.4rem;
  text-align: center;
}

.aq-day-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: #475569;
  text-transform: capitalize;
}

.aq-day-badge {
  width: 2rem;
  height: 2rem;
  margin: 0.35rem auto;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

.aq-day-label {
  font-size: 0.75rem;
  color: #4b5563;
}

.aq-note {
  font-size: 0.75rem;
  color: #9ca3af;
  margin: 0.4rem 0 1rem;
}

@media (max-width: 480px) {
  .aq-section {
    padding: 1rem;
  }
}
</style>
