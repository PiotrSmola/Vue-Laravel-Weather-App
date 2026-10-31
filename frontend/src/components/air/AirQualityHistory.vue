<script setup>
// Historia jakości powietrza (tylko zalogowani, miasto w ulubionych) z wyborem zakresu.
import { computed, ref, watch } from "vue";
import { fetchAirHistory } from "@/api/air";
import { useAuthStore } from "@/stores/auth";
import { useWeatherStore } from "@/stores/weather";
import { aqiLevel, formatConcentration, UNIT } from "@/utils/airQuality";
import AirQualityChart from "./AirQualityChart.vue";

const props = defineProps({
  cityId: { type: [Number, String], required: true },
});

const authStore = useAuthStore();
const weatherStore = useWeatherStore();

const RANGES = [
  { key: "24h", label: "24 godziny", hours: 24 },
  { key: "7d", label: "7 dni", hours: 24 * 7 },
  { key: "30d", label: "30 dni", hours: 24 * 30 },
];

const range = ref("7d");
const history = ref(null);
const loading = ref(false);
const error = ref(null);
// true, gdy miasto nie jest w ulubionych (403)
const notFavorite = ref(false);
let requestId = 0;

async function load() {
  if (!authStore.isAuthenticated) return;

  const id = ++requestId;
  const hours = RANGES.find((r) => r.key === range.value).hours;
  const to = new Date();
  const from = new Date(to.getTime() - hours * 3600 * 1000);

  loading.value = true;
  error.value = null;
  notFavorite.value = false;
  try {
    const result = await fetchAirHistory(props.cityId, { from, to });
    if (id === requestId) history.value = result;
  } catch (err) {
    if (id !== requestId) return;
    history.value = null;
    if (err.status === 403 || err.status === 404) notFavorite.value = true;
    else if (err.status !== 401) error.value = "Nie udało się pobrać historii jakości powietrza";
  } finally {
    if (id === requestId) loading.value = false;
  }
}

watch([() => props.cityId, range, () => authStore.isAuthenticated], load, { immediate: true });

// Miasto dodane do ulubionych na tej stronie – ponów zapytanie
watch(
  () => weatherStore.userFavorites.some((c) => Number(c.openweather_id) === Number(props.cityId)),
  (isFavorite) => {
    if (isFavorite && notFavorite.value) load();
  }
);

const points = computed(() => history.value?.data || []);

const stats = computed(() => {
  const list = points.value;
  if (list.length === 0) return null;
  const values = (key) => list.map((p) => Number(p[key])).filter(Number.isFinite);
  const avg = (arr) => (arr.length ? arr.reduce((a, b) => a + b, 0) / arr.length : null);
  const max = (arr) => (arr.length ? Math.max(...arr) : null);
  const pm25 = values("pm2_5");
  const pm10 = values("pm10");
  const aqi = values("aqi");
  // Udział pomiarów z dobrą lub dostateczną jakością powietrza (AQI 1–2)
  const goodShare = aqi.length ? Math.round((aqi.filter((v) => v <= 2).length / aqi.length) * 100) : null;
  return {
    count: list.length,
    pm25Avg: avg(pm25),
    pm25Max: max(pm25),
    pm10Avg: avg(pm10),
    pm10Max: max(pm10),
    worst: aqiLevel(max(aqi)),
    worstAqi: max(aqi),
    goodShare,
  };
});
</script>

<template>
  <section class="aq-section">
    <div class="aq-section-header">
      <h2 class="aq-section-title">Historia jakości powietrza</h2>
      <div v-if="authStore.isAuthenticated && !notFavorite" class="aq-range-selector">
        <button
          v-for="r in RANGES"
          :key="r.key"
          :class="['aq-range-button', { active: range === r.key }]"
          :disabled="loading"
          @click="range = r.key"
        >
          {{ r.label }}
        </button>
      </div>
    </div>

    <div v-if="!authStore.isAuthenticated" class="aq-state">
      <p>
        <router-link :to="{ name: 'login', query: { redirect: $route.fullPath } }" class="ui-link">Zaloguj się</router-link>,
        aby zobaczyć historię jakości powietrza dla miast dodanych do ulubionych.
      </p>
    </div>

    <div v-else-if="notFavorite" class="aq-state">
      <p>Dodaj to miasto do ulubionych, aby zbierać i przeglądać historię jakości powietrza.</p>
    </div>

    <div v-else-if="loading && !history" class="aq-state">
      <div class="ui-spinner"></div>
      <p>Ładowanie historii...</p>
    </div>

    <div v-else-if="error" class="aq-state aq-state--error">
      <p>{{ error }}</p>
      <button class="ui-btn ui-btn--sm" @click="load">Spróbuj ponownie</button>
    </div>

    <div v-else-if="points.length === 0" class="aq-state">
      <p>Brak pomiarów w wybranym okresie. Dane są zapisywane co godzinę – zajrzyj tu później.</p>
    </div>

    <template v-else>
      <div class="aq-stats" :class="{ 'is-loading': loading }">
        <div class="aq-stat">
          <div class="aq-stat-label">Średnie PM2.5</div>
          <div class="aq-stat-value">{{ formatConcentration(stats.pm25Avg) }} <span>{{ UNIT }}</span></div>
          <div class="aq-stat-sub">maks. {{ formatConcentration(stats.pm25Max) }}</div>
        </div>
        <div class="aq-stat">
          <div class="aq-stat-label">Średnie PM10</div>
          <div class="aq-stat-value">{{ formatConcentration(stats.pm10Avg) }} <span>{{ UNIT }}</span></div>
          <div class="aq-stat-sub">maks. {{ formatConcentration(stats.pm10Max) }}</div>
        </div>
        <div class="aq-stat">
          <div class="aq-stat-label">Najgorszy poziom</div>
          <div class="aq-stat-value">
            <span class="aq-dot" :style="{ backgroundColor: stats.worst.color }"></span>
            {{ stats.worst.label }}
          </div>
          <div class="aq-stat-sub">AQI {{ stats.worstAqi }}</div>
        </div>
        <div class="aq-stat">
          <div class="aq-stat-label">Dobra lub dostateczna</div>
          <div class="aq-stat-value">{{ stats.goodShare }}%</div>
          <div class="aq-stat-sub">z {{ stats.count }} pomiarów</div>
        </div>
      </div>

      <AirQualityChart :points="points" :class="{ 'is-loading': loading }" />
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

.aq-section-header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.aq-section-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
}

/* Jak przyciski wyboru wykresu w szczegółach pogody */
.aq-range-selector {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.aq-range-button {
  padding: 0.5rem 1rem;
  background-color: #e2e8f0;
  border: none;
  border-radius: 0.25rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
}

.aq-range-button:hover:not(:disabled) {
  background-color: #cbd5e1;
}

.aq-range-button.active {
  background-color: #3b82f6;
  color: white;
}

.aq-range-button:disabled {
  cursor: wait;
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

.aq-stats {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
  margin-bottom: 1rem;
}

@media (min-width: 768px) {
  .aq-stats {
    grid-template-columns: repeat(4, 1fr);
  }
}

.aq-stat {
  background-color: #f1f5f9;
  border-radius: 8px;
  padding: 0.75rem 1rem;
}

.aq-stat-label {
  font-size: 0.8rem;
  color: #64748b;
}

.aq-stat-value {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 1.15rem;
  font-weight: 700;
  color: #1f2937;
}

.aq-stat-value span:not(.aq-dot) {
  font-size: 0.75rem;
  font-weight: 500;
  color: #64748b;
}

.aq-stat-sub {
  font-size: 0.75rem;
  color: #6b7280;
}

.aq-dot {
  display: inline-block;
  width: 0.7rem;
  height: 0.7rem;
  border-radius: 50%;
}

.is-loading {
  opacity: 0.6;
  transition: opacity 0.2s;
}

@media (max-width: 480px) {
  .aq-section {
    padding: 1rem;
  }

  .aq-range-button {
    padding: 0.4rem 0.75rem;
  }
}
</style>
