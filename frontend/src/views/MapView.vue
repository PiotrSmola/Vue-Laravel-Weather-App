<script setup>
// Mapa pogodowa: podkład OpenStreetMap + warstwy OpenWeather przez proxy backendu
// (klucz API nie trafia do przeglądarki), markery miast z ostatnim pomiarem,
// pogoda po kliknięciu w mapę i "Moja lokalizacja".
import { ref, computed, watch, onMounted, onBeforeUnmount, shallowRef } from 'vue';
import { useRouter } from 'vue-router';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { api } from '@/api/client';
import { weatherParams } from '@/stores/weather';
import { useSettingsStore } from '@/stores/settings';
import { useAuthStore } from '@/stores/auth';
import { useUnits } from '@/composables/useUnits';
import { useGeolocation } from '@/composables/useGeolocation';
import { MAP_LAYERS, OVERLAY_MAX_ZOOM, legendGradient } from '@/utils/mapLayers';

// Domyślne ikony markerów Leaflet nie działają z bundlerem – wskazujemy pliki z pakietu
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconUrl: markerIcon, iconRetinaUrl: markerIcon2x, shadowUrl: markerShadow });

const OPACITY_KEY = 'map_overlay_opacity';
const LAYER_KEY = 'map_overlay_layer';

const router = useRouter();
const settings = useSettingsStore();
const authStore = useAuthStore();
const { formatTemp, formatWind, formatPressure } = useUnits();
const { locate, locating, geoError } = useGeolocation();

const mapEl = ref(null);
const map = shallowRef(null);
let overlay = null;
let cityLayer = null;
let pointMarker = null;
let clickSeq = 0;

const readLocal = (key, fallback) => {
  try {
    return localStorage.getItem(key) ?? fallback;
  } catch {
    return fallback;
  }
};
const writeLocal = (key, value) => {
  try {
    localStorage.setItem(key, value);
  } catch {
    // brak localStorage – ignorujemy
  }
};

const layerId = ref(
  MAP_LAYERS.some((l) => l.id === readLocal(LAYER_KEY, '')) ? readLocal(LAYER_KEY, '') : 'temp_new'
);
const opacity = ref(Math.min(1, Math.max(0.1, Number(readLocal(OPACITY_KEY, 0.7)) || 0.7)));
const cities = ref([]);
const citiesError = ref(null);
const locationError = ref(null);

const activeLayer = computed(() => MAP_LAYERS.find((l) => l.id === layerId.value) || null);

// Etykiety legendy w jednostkach użytkownika
const legendLabels = computed(() => {
  const layer = activeLayer.value;
  if (!layer) return [];
  const format = {
    temp: (v) => formatTemp(v),
    wind: (v) => formatWind(v),
    pressure: (v) => formatPressure(v),
    precip: (v) => `${v} mm/h`,
    percent: (v) => `${v}%`,
  }[layer.kind];
  const stops = layer.stops;
  // Najwyżej 5 etykiet: pierwsza, ostatnia i równo rozłożone pośrodku
  const count = Math.min(5, stops.length);
  const idx = Array.from({ length: count }, (_, i) => Math.round((i * (stops.length - 1)) / (count - 1)));
  return [...new Set(idx)].map((i) => ({ value: format(stops[i][0]), left: (i / (stops.length - 1)) * 100 }));
});

const escapeHtml = (value) =>
  String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const iconUrl = (icon) => `https://openweathermap.org/img/wn/${encodeURIComponent(icon)}.png`;

const formatTime = (iso) =>
  iso ? new Date(iso).toLocaleString('pl-PL', { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

// --- warstwa pogodowa ---
const applyOverlay = () => {
  if (!map.value) return;
  if (overlay) {
    map.value.removeLayer(overlay);
    overlay = null;
  }
  if (!activeLayer.value) return;
  overlay = L.tileLayer(`/api/map/tiles/${activeLayer.value.id}/{z}/{x}/{y}.png`, {
    opacity: opacity.value,
    maxNativeZoom: OVERLAY_MAX_ZOOM,
    // Mniej zapytań o kafelki: bez bufora poza widokiem, odświeżanie po zakończeniu przesuwania
    keepBuffer: 0,
    updateWhenIdle: true,
    updateWhenZooming: false,
    attribution: 'Dane pogodowe © <a href="https://openweathermap.org/" target="_blank" rel="noopener">OpenWeather</a>',
  }).addTo(map.value);
};

watch(layerId, (id) => {
  writeLocal(LAYER_KEY, id);
  applyOverlay();
});

watch(opacity, (value) => {
  writeLocal(OPACITY_KEY, String(value));
  overlay?.setOpacity(value);
});

// --- markery miast ---
const cityPopup = (city) => {
  const title = city.alias ? `${escapeHtml(city.alias)} <span class="wmap-muted">(${escapeHtml(city.name)})</span>` : escapeHtml(city.name);
  const weather = city.temp === null || city.temp === undefined
    ? '<p class="wmap-muted">Brak zapisanego pomiaru</p>'
    : `<p class="wmap-temp">${city.icon ? `<img src="${iconUrl(city.icon)}" alt="" width="40" height="40">` : ''}${escapeHtml(formatTemp(city.temp))}</p>
       <p class="wmap-desc">${escapeHtml(city.description || city.condition || '')}</p>
       ${city.feels_like !== null && city.feels_like !== undefined ? `<p class="wmap-muted">Odczuwalna ${escapeHtml(formatTemp(city.feels_like))}</p>` : ''}
       ${city.measured_at ? `<p class="wmap-muted">Pomiar: ${escapeHtml(formatTime(city.measured_at))}</p>` : ''}`;
  return `<div class="wmap-popup">
    <p class="wmap-title">${city.is_favorite ? '❤️ ' : ''}${title}</p>
    ${weather}
    <a href="/weather/${Number(city.id)}" data-city-id="${Number(city.id)}" class="wmap-link">Szczegóły pogody →</a>
  </div>`;
};

const renderCities = () => {
  if (!map.value) return;
  cityLayer?.remove();
  cityLayer = L.layerGroup();
  for (const city of cities.value) {
    const label = city.temp === null || city.temp === undefined ? '—' : formatTemp(city.temp);
    const icon = L.divIcon({
      className: 'wmap-marker-wrapper',
      html: `<div class="wmap-marker${city.is_favorite ? ' wmap-marker--favorite' : ''}">
        ${city.icon ? `<img src="${iconUrl(city.icon)}" alt="">` : ''}<span>${escapeHtml(label)}</span>
      </div>`,
      iconSize: null,
      iconAnchor: [28, 16],
      popupAnchor: [0, -14],
    });
    L.marker([city.lat, city.lon], { icon, title: city.alias || city.name, keyboard: true })
      .bindPopup(() => cityPopup(city))
      .addTo(cityLayer);
  }
  cityLayer.addTo(map.value);
};

const loadCities = async () => {
  citiesError.value = null;
  try {
    const data = await api.get('/map/cities');
    cities.value = Array.isArray(data) ? data : [];
  } catch (error) {
    citiesError.value = error.message || 'Nie udało się pobrać miast';
    cities.value = [];
  }
  renderCities();
};

// Jednostki zmienione – odświeżamy etykiety markerów
watch(() => [settings.units, settings.wind_unit], renderCities);
// Zalogowanie / wylogowanie – inne ulubione
watch(() => authStore.user?.id, loadCities);

// --- pogoda dla punktu (kliknięcie lub lokalizacja) ---
const pointPopup = (data, isLocation) => {
  const w = data.weather?.[0] || {};
  const id = Number(data.id) || 0;
  return `<div class="wmap-popup">
    <p class="wmap-title">${isLocation ? '📍 Twoja lokalizacja' : escapeHtml(data.name || 'Wybrany punkt')}</p>
    ${isLocation && data.name ? `<p class="wmap-muted">${escapeHtml(data.name)}${data.sys?.country ? `, ${escapeHtml(data.sys.country)}` : ''}</p>` : ''}
    <p class="wmap-temp">${w.icon ? `<img src="${iconUrl(w.icon)}" alt="" width="40" height="40">` : ''}${escapeHtml(formatTemp(data.main?.temp))}</p>
    <p class="wmap-desc">${escapeHtml(w.description || '')}</p>
    <p class="wmap-muted">Odczuwalna ${escapeHtml(formatTemp(data.main?.feels_like))} · Wiatr ${escapeHtml(formatWind(data.wind?.speed))}</p>
    ${id > 0 ? `<a href="/weather/${id}" data-city-id="${id}" class="wmap-link">Szczegóły pogody${data.name ? ` (${escapeHtml(data.name)})` : ''} →</a>` : ''}
  </div>`;
};

const showPointWeather = async (lat, lon, { isLocation = false } = {}) => {
  if (!map.value) return;
  const seq = ++clickSeq;
  const latlng = L.latLng(lat, lon);

  pointMarker?.remove();
  pointMarker = L.marker(latlng, { title: isLocation ? 'Twoja lokalizacja' : 'Wybrany punkt' }).addTo(map.value);
  pointMarker.bindPopup('<div class="wmap-popup"><p class="wmap-muted">Ładowanie pogody...</p></div>').openPopup();

  try {
    const data = await api.get('/weather/coordinates', {
      params: weatherParams({ lat: lat.toFixed(4), lon: lon.toFixed(4) }),
    });
    if (seq !== clickSeq) return;
    pointMarker.setPopupContent(pointPopup(data, isLocation)).openPopup();
  } catch (error) {
    if (seq !== clickSeq) return;
    pointMarker
      .setPopupContent(`<div class="wmap-popup"><p class="wmap-error">${escapeHtml(error.message || 'Nie udało się pobrać pogody')}</p></div>`)
      .openPopup();
  }
};

const useMyLocation = async () => {
  locationError.value = null;
  const coords = await locate();
  if (!coords) {
    locationError.value = geoError.value;
    return;
  }
  map.value?.setView([coords.lat, coords.lon], Math.max(map.value.getZoom(), 9));
  showPointWeather(coords.lat, coords.lon, { isLocation: true });
};

// Linki w popupach (HTML Leaflet) – nawigacja routerem bez przeładowania strony
const onMapLinkClick = (event) => {
  const link = event.target.closest?.('a[data-city-id]');
  if (!link) return;
  event.preventDefault();
  router.push({ name: 'weather-details', params: { id: link.dataset.cityId } });
};

onMounted(() => {
  map.value = L.map(mapEl.value, {
    center: [52.07, 19.48], // środek Polski
    zoom: 6,
    minZoom: 3,
    maxZoom: 12,
    worldCopyJump: true,
  });

  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
  }).addTo(map.value);

  applyOverlay();
  map.value.on('click', (e) => showPointWeather(e.latlng.lat, e.latlng.lng));
  mapEl.value.addEventListener('click', onMapLinkClick);

  loadCities();
});

onBeforeUnmount(() => {
  mapEl.value?.removeEventListener('click', onMapLinkClick);
  map.value?.remove();
  map.value = null;
});
</script>

<template>
  <div class="map-page">
    <div class="map-card">
      <h1 class="ui-title map-title">Mapa pogodowa</h1>

      <div class="map-toolbar">
        <div class="map-layers" role="radiogroup" aria-label="Warstwa pogodowa">
          <button
            v-for="layer in MAP_LAYERS"
            :key="layer.id"
            type="button"
            role="radio"
            :aria-checked="layerId === layer.id"
            :class="['map-layer-button', { active: layerId === layer.id }]"
            @click="layerId = layer.id"
          >
            {{ layer.label }}
          </button>
          <button
            type="button"
            role="radio"
            :aria-checked="layerId === ''"
            :class="['map-layer-button', { active: layerId === '' }]"
            @click="layerId = ''"
          >
            Bez warstwy
          </button>
        </div>

        <button type="button" class="ui-btn ui-btn--sm map-locate" :disabled="locating" @click="useMyLocation">
          📍 {{ locating ? 'Ustalanie lokalizacji...' : 'Moja lokalizacja' }}
        </button>
      </div>

      <div v-if="activeLayer" class="map-legend-row">
        <div class="map-legend">
          <div class="map-legend-title">{{ activeLayer.label }}</div>
          <div class="map-legend-bar" :style="{ background: `${legendGradient(activeLayer)}, #9ca3af` }"></div>
          <div class="map-legend-labels">
            <span
              v-for="(label, i) in legendLabels"
              :key="i"
              class="map-legend-label"
              :style="{ left: `${label.left}%` }"
            >{{ label.value }}</span>
          </div>
        </div>
        <div class="map-opacity">
          <span class="ui-label map-opacity-label">Krycie warstwy: {{ Math.round(opacity * 100) }}%</span>
          <input
            v-model.number="opacity"
            type="range"
            min="0.1"
            max="1"
            step="0.05"
            aria-label="Krycie warstwy pogodowej"
            class="map-opacity-input"
          />
        </div>
      </div>

      <p v-if="locationError" class="ui-form-error map-message" role="alert">{{ locationError }}</p>
      <p v-if="citiesError" class="ui-form-error map-message" role="alert">{{ citiesError }}</p>

      <div class="map-wrapper">
        <div ref="mapEl" class="map-canvas" aria-label="Mapa pogodowa"></div>
      </div>

      <p class="ui-hint map-hint">
        Kliknij w dowolne miejsce na mapie, aby sprawdzić aktualną pogodę. Markery pokazują ostatni zapisany pomiar
        dla miast domyślnych<span v-if="authStore.user"> i Twoich ulubionych (❤️)</span>.
      </p>
    </div>
  </div>
</template>

<style scoped>
.map-page {
  max-width: 1000px;
  margin: 0 auto;
  padding: 1rem;
}

.map-card {
  background-color: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  padding: 1.5rem;
}

.map-title {
  margin-bottom: 1rem;
}

.map-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.map-layers {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.map-layer-button {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  background-color: #e5e7eb;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s ease;
}

.map-layer-button:hover {
  background-color: #d1d5db;
}

.map-layer-button.active {
  background-color: #3b82f6;
  color: white;
}

.map-legend-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 1rem 2rem;
  margin-bottom: 1rem;
}

.map-legend {
  flex: 1 1 280px;
  min-width: 0;
}

.map-legend-title {
  font-size: 0.875rem;
  font-weight: 500;
  color: #4b5563;
  margin-bottom: 0.25rem;
}

.map-legend-bar {
  height: 12px;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
}

.map-legend-labels {
  position: relative;
  height: 1.25rem;
  margin: 0.25rem 0.75rem 0;
}

.map-legend-label {
  position: absolute;
  transform: translateX(-50%);
  font-size: 0.75rem;
  color: #6b7280;
  white-space: nowrap;
}

.map-opacity {
  display: flex;
  flex-direction: column;
  flex: 0 1 240px;
}

.map-opacity-label {
  display: block;
}

.map-opacity-input {
  width: 100%;
  accent-color: #3b82f6;
}

.map-message {
  margin-bottom: 1rem;
}

/* Własny kontekst nakładania – warstwy Leaflet (z-index 400+) nie przykrywają menu i modali */
.map-wrapper {
  position: relative;
  z-index: 0;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e5e7eb;
}

.map-canvas {
  height: 65vh;
  min-height: 360px;
  max-height: 720px;
  width: 100%;
  background-color: #f0f9ff;
}

.map-hint {
  margin-top: 0.75rem;
}

@media (max-width: 640px) {
  .map-page {
    padding: 0.5rem;
  }

  .map-card {
    padding: 1rem;
  }

  .map-locate {
    width: 100%;
  }

  .map-canvas {
    height: 60vh;
  }
}
</style>

<style>
/* Elementy tworzone przez Leaflet (poza zasięgiem stylów scoped) */
.wmap-marker-wrapper {
  background: transparent;
  border: none;
}

.wmap-marker {
  display: inline-flex;
  align-items: center;
  gap: 0.125rem;
  padding: 0 0.5rem 0 0.125rem;
  height: 32px;
  background-color: white;
  border: 2px solid #3b82f6;
  border-radius: 16px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  font-weight: 600;
  font-size: 0.85rem;
  color: #1f2937;
  white-space: nowrap;
  font-family: "Poppins", sans-serif;
}

.wmap-marker img {
  width: 28px;
  height: 28px;
}

.wmap-marker--favorite {
  border-color: #ef4444;
}

.wmap-popup {
  font-family: "Poppins", sans-serif;
  min-width: 160px;
}

.wmap-popup p {
  margin: 0.125rem 0;
}

.wmap-title {
  font-weight: 600;
  font-size: 1rem;
  color: #1e40af;
}

.wmap-temp {
  display: flex;
  align-items: center;
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
}

.wmap-desc {
  text-transform: capitalize;
  color: #4b5563;
}

.wmap-muted {
  color: #6b7280;
  font-size: 0.8rem;
}

.wmap-error {
  color: #b91c1c;
}

.wmap-link {
  display: inline-block;
  margin-top: 0.5rem;
  color: #3b82f6;
  font-weight: 500;
  text-decoration: none;
}

.wmap-link:hover {
  text-decoration: underline;
}
</style>
