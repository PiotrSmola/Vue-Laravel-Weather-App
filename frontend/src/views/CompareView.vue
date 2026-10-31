<script setup>
// Porównanie 2–4 miast: aktualne warunki obok siebie, prognoza temperatury,
// prawdopodobieństwo opadów i wiatr. Stan zapisany w URL: /compare?ids=756135,3094802
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useWeatherStore } from '@/stores/weather';
import { useAuthStore } from '@/stores/auth';
import { useUnits } from '@/composables/useUnits';
import { fetchCompare, COMPARE_MIN, COMPARE_MAX } from '@/api/history';
import CompareCityPicker from '@/components/compare/CompareCityPicker.vue';
import CompareLineChart from '@/components/compare/CompareLineChart.vue';
import CompareBarChart from '@/components/compare/CompareBarChart.vue';

// Kolory serii – zgodne z paletą aplikacji (niebieski, pomarańczowy, zielony, fiolet)
const COLORS = ['#3b82f6', '#ff9800', '#10b981', '#8b5cf6'];

const route = useRoute();
const router = useRouter();
const weatherStore = useWeatherStore();
const authStore = useAuthStore();
const { formatTemp, formatWind, formatPressure, convertTemp, convertWind, tempUnit, windUnit } = useUnits();

const selected = ref([]);
const cities = ref([]);
const loading = ref(false);
const error = ref(null);
const copied = ref(false);
let controller = null;

const parseIds = (value) => [...new Set(String(value || '')
  .split(',')
  .map(v => Number.parseInt(v, 10))
  .filter(v => Number.isInteger(v) && v > 0))].slice(0, COMPARE_MAX);

// Nazwa miasta z list znanych w aplikacji (zanim odpowie API)
const knownCity = (id) => {
  const all = [...weatherStore.userFavorites, ...weatherStore.defaultCities];
  const city = all.find(c => Number(c.openweather_id) === id);
  return city ? { id, name: city.name, country: city.country || '' } : { id, name: `Miasto #${id}`, country: '' };
};

const ids = computed(() => selected.value.map(c => c.id));
const colorOf = (id) => COLORS[Math.max(0, ids.value.indexOf(id)) % COLORS.length];

// Wyniki w kolejności wyboru (API zwraca w kolejności ids)
const ordered = computed(() => ids.value
  .map(id => cities.value.find(c => c.id === id))
  .filter(Boolean));

const load = async () => {
  if (controller) controller.abort();

  if (ids.value.length < COMPARE_MIN) {
    cities.value = [];
    error.value = null;
    loading.value = false;
    return;
  }

  controller = new AbortController();
  const signal = controller.signal;
  loading.value = true;
  error.value = null;

  try {
    const result = await fetchCompare(ids.value, { signal });
    cities.value = result?.cities || [];
    // Uzupełnienie nazw w wybranych miastach (np. po wejściu z linku)
    selected.value = selected.value.map(c => {
      const found = cities.value.find(x => x.id === c.id);
      return found ? { id: c.id, name: found.name, country: found.country || '' } : c;
    });
  } catch (e) {
    if (e.name === 'AbortError') return;
    console.error('Błąd porównania miast:', e);
    cities.value = [];
    error.value = e.status === 404
      ? 'Nie znaleziono jednego z miast. Usuń je z porównania i spróbuj ponownie.'
      : e.status === 429
        ? 'Zbyt wiele zapytań. Spróbuj ponownie za chwilę.'
        : e.message || 'Nie udało się pobrać danych do porównania';
  } finally {
    if (!signal.aborted) loading.value = false;
  }
};

// Zmiana wyboru -> URL (do udostępnienia) i ponowne pobranie danych
watch(ids, (next, prev) => {
  if (prev && next.join(',') === prev.join(',')) return;
  const query = { ...route.query };
  if (next.length) query.ids = next.join(',');
  else delete query.ids;
  if ((route.query.ids || '') !== (query.ids || '')) router.replace({ query });
  load();
});

// Nawigacja wstecz/naprzód lub nowy link z innym ?ids=
watch(() => route.query.ids, (value) => {
  const next = parseIds(value);
  if (next.join(',') !== ids.value.join(',')) {
    selected.value = next.map(id => selected.value.find(c => c.id === id) || knownCity(id));
  }
});

onMounted(async () => {
  if (authStore.user && !weatherStore.userFavorites.length) {
    await weatherStore.getUserFavorites();
  }
  selected.value = parseIds(route.query.ids).map(knownCity);
});

const shareLink = async () => {
  try {
    await navigator.clipboard.writeText(window.location.href);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
  } catch {
    window.prompt('Skopiuj link do porównania:', window.location.href);
  }
};

const iconUrl = (icon) => `https://openweathermap.org/img/wn/${icon}@2x.png`;

const windDirection = (deg) => {
  if (deg === null || deg === undefined) return '';
  return ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'][Math.round(deg / 45) % 8];
};

const formatTime = (dt) => dt
  ? new Date(dt * 1000).toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' })
  : '';

// Najcieplejsze / najzimniejsze miasto teraz
const extremes = computed(() => {
  const list = ordered.value.filter(c => c.current?.temp !== null && c.current?.temp !== undefined);
  if (list.length < 2) return null;
  const sorted = [...list].sort((a, b) => b.current.temp - a.current.temp);
  return { warmest: sorted[0].id, coldest: sorted[sorted.length - 1].id };
});

const legend = computed(() => ordered.value.map(c => ({ label: c.name, color: colorOf(c.id) })));

const tempSeries = computed(() => ordered.value.map(c => ({
  label: c.name,
  color: colorOf(c.id),
  points: c.forecast.map(f => ({ t: f.dt * 1000, v: convertTemp(f.temp) })),
})));

const windSeries = computed(() => ordered.value.map(c => ({
  label: c.name,
  color: colorOf(c.id),
  points: c.forecast.map(f => ({ t: f.dt * 1000, v: convertWind(f.wind_speed) })),
})));

const formatTempAxis = (v) => `${Math.round(v)}${tempUnit.value}`;
const formatWindAxis = (v) => `${Number(v).toLocaleString('pl-PL', { maximumFractionDigits: windUnit.value === 'm/s' ? 1 : 0 })} ${windUnit.value}`;
const formatPop = (v) => `${Math.round(v || 0)}%`;

// Maksymalne prawdopodobieństwo opadów w kolejnych dniach (lokalnie)
const popGroups = computed(() => {
  const days = new Map();
  for (const city of ordered.value) {
    for (const f of city.forecast) {
      const date = new Date(f.dt * 1000);
      const key = date.toDateString();
      if (!days.has(key)) {
        days.set(key, {
          label: date.toLocaleDateString('pl-PL', { weekday: 'short', day: 'numeric' }),
          byCity: new Map(),
        });
      }
      const byCity = days.get(key).byCity;
      byCity.set(city.id, Math.max(byCity.get(city.id) ?? 0, (f.pop || 0) * 100));
    }
  }
  return [...days.values()].map(day => ({
    label: day.label,
    values: ordered.value.map(c => ({ label: c.name, color: colorOf(c.id), value: day.byCity.get(c.id) ?? 0 })),
  }));
});
</script>

<template>
  <div class="compare-page">
    <h1 class="ui-title">Porównanie miast</h1>

    <section class="compare-section picker-section">
      <CompareCityPicker v-model="selected" :max="COMPARE_MAX" :colors="COLORS" />
      <div v-if="ids.length >= COMPARE_MIN" class="share-row">
        <button type="button" class="ui-btn ui-btn--sm ui-btn--secondary" @click="shareLink">
          {{ copied ? 'Skopiowano link!' : 'Kopiuj link do porównania' }}
        </button>
      </div>
    </section>

    <div v-if="ids.length < COMPARE_MIN" class="ui-state">
      <p>Wybierz co najmniej {{ COMPARE_MIN }} miasta (maksymalnie {{ COMPARE_MAX }}), aby je porównać.</p>
      <p class="ui-hint">Możesz skorzystać z wyszukiwarki lub listy popularnych miast powyżej.</p>
    </div>

    <div v-else-if="loading && !ordered.length" class="ui-state">
      <div class="ui-spinner"></div>
      <p>Ładowanie danych pogodowych...</p>
    </div>

    <div v-else-if="error" class="ui-state ui-state--error">
      <p>{{ error }}</p>
      <button type="button" class="ui-btn ui-btn--sm compare-retry" @click="load">Spróbuj ponownie</button>
    </div>

    <template v-else-if="ordered.length">
      <div :class="['compare-results', { 'is-refreshing': loading }]">
        <section class="compare-section">
          <h2 class="section-title">Aktualne warunki</h2>
          <div :class="['city-cards', `cols-${ordered.length}`]">
            <article v-for="city in ordered" :key="city.id" class="city-card" :style="{ borderTopColor: colorOf(city.id) }">
              <div class="city-card-header">
                <h3 class="city-card-name">{{ city.name }}<span class="city-card-country">{{ city.country ? `, ${city.country}` : '' }}</span></h3>
                <span v-if="extremes && extremes.warmest === city.id" class="badge badge-warm">Najcieplej</span>
                <span v-else-if="extremes && extremes.coldest === city.id" class="badge badge-cold">Najzimniej</span>
              </div>
              <div class="city-card-main">
                <img v-if="city.current.icon" :src="iconUrl(city.current.icon)" :alt="city.current.description" class="city-card-icon" />
                <div>
                  <div class="city-card-temp">{{ formatTemp(city.current.temp) }}</div>
                  <div class="city-card-desc">{{ city.current.description }}</div>
                </div>
              </div>
              <dl class="city-card-metrics">
                <div><dt>Odczuwalna</dt><dd>{{ formatTemp(city.current.feels_like) }}</dd></div>
                <div><dt>Wilgotność</dt><dd>{{ city.current.humidity ?? '—' }}%</dd></div>
                <div><dt>Ciśnienie</dt><dd>{{ formatPressure(city.current.pressure) }}</dd></div>
                <div>
                  <dt>Wiatr</dt>
                  <dd>
                    {{ formatWind(city.current.wind_speed) }}
                    <span v-if="city.current.wind_deg !== null && city.current.wind_deg !== undefined" class="wind-dir"
                      :title="`Kierunek: ${city.current.wind_deg}°`">
                      <span class="wind-arrow" :style="{ transform: `rotate(${city.current.wind_deg + 180}deg)` }">↑</span>
                      {{ windDirection(city.current.wind_deg) }}
                    </span>
                  </dd>
                </div>
              </dl>
              <div class="city-card-footer">
                <span class="ui-hint">Pomiar: {{ formatTime(city.current.dt) }}</span>
                <RouterLink :to="{ name: 'weather-details', params: { id: city.id } }" class="ui-link">Szczegóły →</RouterLink>
              </div>
            </article>
          </div>
        </section>

        <section class="compare-section">
          <h2 class="section-title">Prognoza temperatury (5 dni)</h2>
          <CompareLineChart :series="tempSeries" :format-value="formatTempAxis" :min-span="4"
            aria-label="Prognoza temperatury dla porównywanych miast" />
        </section>

        <section class="compare-section">
          <h2 class="section-title">Prawdopodobieństwo opadów (maksimum dzienne)</h2>
          <CompareBarChart :groups="popGroups" :legend="legend" :max="100" :format-value="formatPop" />
        </section>

        <section class="compare-section">
          <h2 class="section-title">Prognoza prędkości wiatru</h2>
          <CompareLineChart :series="windSeries" :format-value="formatWindAxis" :min-span="2" zero-based
            aria-label="Prognoza prędkości wiatru dla porównywanych miast" />
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
.compare-page {
  /* element flex w App.vue – bez min-width: 0 szeroki wykres rozpychałby stronę na telefonach */
  min-width: 0;
  width: 100%;
  max-width: 1000px;
  margin: 0 auto;
  padding: 2rem 1rem;
}

.compare-section {
  margin-bottom: 2rem;
}

.picker-section {
  background-color: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.share-row {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}

.compare-retry {
  margin-top: 1rem;
}

.section-title {
  font-size: 1.25rem;
  color: #1e293b;
  margin-bottom: 1rem;
  text-align: center;
}

.compare-results.is-refreshing {
  opacity: 0.6;
  transition: opacity 0.2s;
}

.city-cards {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
}

@media (min-width: 640px) {
  .city-cards {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .city-cards.cols-3 {
    grid-template-columns: repeat(3, 1fr);
  }

  .city-cards.cols-4 {
    grid-template-columns: repeat(4, 1fr);
  }
}

.city-card {
  background-color: white;
  border-radius: 8px;
  border-top: 4px solid;
  padding: 1rem 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
}

.city-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
}

.city-card-name {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
}

.city-card-country {
  color: #64748b;
  font-weight: 400;
}

.badge {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 0.15rem 0.5rem;
  border-radius: 9999px;
  white-space: nowrap;
}

.badge-warm {
  background-color: #ffedd5;
  color: #c2410c;
}

.badge-cold {
  background-color: #dbeafe;
  color: #1d4ed8;
}

.city-card-main {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0.5rem 0;
}

.city-card-icon {
  width: 64px;
  height: 64px;
  margin-left: -0.5rem;
}

.city-card-temp {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.1;
}

.city-card-desc {
  font-size: 0.875rem;
  color: #475569;
  text-transform: capitalize;
}

.city-card-metrics {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  margin: 0.5rem 0 1rem;
}

.city-card-metrics > div {
  background-color: #f8fafc;
  border-radius: 6px;
  padding: 0.5rem;
}

.city-card-metrics dt {
  font-size: 0.75rem;
  color: #64748b;
}

.city-card-metrics dd {
  font-size: 0.9rem;
  font-weight: 600;
  color: #1e293b;
}

.wind-dir {
  display: inline-flex;
  align-items: center;
  gap: 0.15rem;
  font-weight: 500;
  color: #475569;
}

.wind-arrow {
  display: inline-block;
}

.city-card-footer {
  margin-top: auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
