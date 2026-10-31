<script setup>
// Kompaktowa plakietka jakości powietrza dla aktualnie wyświetlanego miasta (strona główna).
import { computed, ref, watch } from "vue";
import { fetchAirQuality } from "@/api/air";
import { aqiLevel, formatConcentration, UNIT } from "@/utils/airQuality";

const props = defineProps({
  // ID miasta w OpenWeather
  cityId: { type: [Number, String], default: null },
});

const data = ref(null);
const loading = ref(false);
const failed = ref(false);
let requestId = 0;

async function load() {
  if (!props.cityId) return;
  const id = ++requestId;
  loading.value = true;
  failed.value = false;
  try {
    const result = await fetchAirQuality(props.cityId);
    if (id === requestId) data.value = result;
  } catch {
    if (id !== requestId) return;
    data.value = null;
    failed.value = true;
  } finally {
    if (id === requestId) loading.value = false;
  }
}

watch(() => props.cityId, load, { immediate: true });

const level = computed(() => aqiLevel(data.value?.aqi));
</script>

<template>
  <div v-if="cityId" class="aq-badge" :class="{ 'is-loading': loading }">
    <template v-if="data">
      <span class="aq-badge-level" :style="{ backgroundColor: level.color, color: level.text }">{{ data.aqi }}</span>
      <div class="aq-badge-text">
        <div class="aq-badge-title">
          Jakość powietrza: <strong :style="{ color: level.color }">{{ data.label }}</strong>
        </div>
        <div class="aq-badge-values">
          PM2.5 {{ formatConcentration(data.components.pm2_5) }} · PM10
          {{ formatConcentration(data.components.pm10) }} {{ UNIT }}
        </div>
      </div>
      <router-link :to="{ name: 'weather-details', params: { id: cityId } }" class="aq-badge-link">
        Szczegóły →
      </router-link>
    </template>

    <div v-else-if="loading" class="aq-badge-text aq-badge-muted">Ładowanie jakości powietrza...</div>

    <div v-else-if="failed" class="aq-badge-text aq-badge-muted">
      Brak danych o jakości powietrza
      <button class="aq-badge-retry" @click="load">Spróbuj ponownie</button>
    </div>
  </div>
</template>

<style scoped>
.aq-badge {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  margin-top: 1rem;
  padding: 0.875rem 1.25rem;
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transition: opacity 0.2s;
}

.aq-badge.is-loading {
  opacity: 0.7;
}

.aq-badge-level {
  flex-shrink: 0;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.1rem;
}

.aq-badge-text {
  flex: 1;
  min-width: 0;
}

.aq-badge-title {
  color: #1f2937;
}

.aq-badge-title strong {
  filter: brightness(0.85);
}

.aq-badge-values {
  font-size: 0.85rem;
  color: #6b7280;
}

.aq-badge-muted {
  color: #6b7280;
  font-size: 0.9rem;
}

.aq-badge-link {
  flex-shrink: 0;
  color: #3b82f6;
  font-weight: 500;
  font-size: 0.9rem;
  text-decoration: none;
}

.aq-badge-link:hover {
  text-decoration: underline;
}

.aq-badge-retry {
  margin-left: 0.5rem;
  background: none;
  border: none;
  color: #3b82f6;
  font-weight: 500;
  cursor: pointer;
}

@media (max-width: 480px) {
  .aq-badge {
    flex-wrap: wrap;
    padding: 0.75rem 1rem;
  }

  .aq-badge-link {
    width: 100%;
    text-align: right;
  }
}
</style>
