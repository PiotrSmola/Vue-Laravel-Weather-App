<script setup>
// Wybór miast do porównania: wybrane (z kolorami), szybki wybór (ulubione / miasta domyślne)
// oraz wyszukiwarka (akcja searchCities ze store'u pogody).
// v-model: [{ id (OpenWeather), name, country }]
import { ref, computed, onBeforeUnmount } from 'vue';
import { useWeatherStore } from '@/stores/weather';
import { useAuthStore } from '@/stores/auth';

const props = defineProps({
  modelValue: { type: Array, required: true },
  max: { type: Number, default: 4 },
  colors: { type: Array, required: true },
});
const emit = defineEmits(['update:modelValue']);

const weatherStore = useWeatherStore();
const authStore = useAuthStore();

const query = ref('');
const results = ref([]);
const searching = ref(false);
const searchError = ref(null);
let timer = null;
let searchId = 0;

const selectedIds = computed(() => props.modelValue.map(c => c.id));
const isFull = computed(() => props.modelValue.length >= props.max);

const normalize = (city) => ({
  id: Number(city.openweather_id ?? city.id),
  name: city.name,
  country: city.country || '',
});

// Szybki wybór: najpierw ulubione (dla zalogowanych), potem miasta domyślne
const quickPicks = computed(() => {
  const favorites = authStore.user ? weatherStore.userFavorites.map(normalize) : [];
  const defaults = weatherStore.defaultCities.map(normalize);
  const seen = new Set();
  return [...favorites.map(c => ({ ...c, favorite: true })), ...defaults]
    .filter(c => c.id && !seen.has(c.id) && seen.add(c.id));
});

const add = (city) => {
  const normalized = normalize(city);
  if (!normalized.id || selectedIds.value.includes(normalized.id) || isFull.value) return;
  emit('update:modelValue', [...props.modelValue, normalized]);
  query.value = '';
  results.value = [];
};

const remove = (id) => {
  emit('update:modelValue', props.modelValue.filter(c => c.id !== id));
};

const onInput = () => {
  clearTimeout(timer);
  searchError.value = null;
  const q = query.value.trim();
  if (q.length < 3) {
    results.value = [];
    searching.value = false;
    return;
  }
  searching.value = true;
  timer = setTimeout(async () => {
    const id = ++searchId;
    try {
      const found = await weatherStore.searchCities(q);
      if (id !== searchId) return;
      results.value = (found || []).filter(c => c.id || c.openweather_id);
      if (!results.value.length) searchError.value = 'Nie znaleziono miast';
    } catch {
      if (id === searchId) searchError.value = 'Nie udało się wyszukać miast';
    } finally {
      if (id === searchId) searching.value = false;
    }
  }, 400);
};

onBeforeUnmount(() => clearTimeout(timer));

const colorFor = (index) => props.colors[index % props.colors.length];
</script>

<template>
  <div class="picker">
    <div class="picker-selected" aria-live="polite">
      <span v-for="(city, index) in modelValue" :key="city.id" class="chip"
        :style="{ borderColor: colorFor(index) }">
        <span class="chip-dot" :style="{ backgroundColor: colorFor(index) }"></span>
        <span>{{ city.name }}<span v-if="city.country" class="chip-country">, {{ city.country }}</span></span>
        <button type="button" class="chip-remove" :aria-label="`Usuń ${city.name} z porównania`" @click="remove(city.id)">×</button>
      </span>
      <span v-if="!modelValue.length" class="ui-muted">Nie wybrano jeszcze żadnego miasta.</span>
    </div>

    <div v-if="!isFull" class="picker-add">
      <label class="ui-label" for="compare-search">Dodaj miasto ({{ modelValue.length }}/{{ max }})</label>
      <div class="search-box">
        <input id="compare-search" v-model="query" type="search" placeholder="Wpisz nazwę miasta (min. 3 znaki)"
          autocomplete="off" @input="onInput" @keydown.enter.prevent="results[0] && add(results[0])" />
        <ul v-if="results.length" class="search-results" role="listbox">
          <li v-for="city in results" :key="`${city.id}-${city.lat}-${city.lon}`">
            <button type="button" class="search-result" :disabled="selectedIds.includes(Number(city.id ?? city.openweather_id))"
              @click="add(city)">
              {{ city.name }}<span class="ui-muted">{{ city.state ? `, ${city.state}` : '' }}{{ city.country ? `, ${city.country}` : '' }}</span>
            </button>
          </li>
        </ul>
      </div>
      <p v-if="searching" class="ui-hint">Wyszukiwanie...</p>
      <p v-else-if="searchError" class="ui-hint">{{ searchError }}</p>

      <div class="quick-picks">
        <span class="ui-hint quick-label">{{ authStore.user && weatherStore.userFavorites.length ? 'Ulubione i popularne:' : 'Popularne miasta:' }}</span>
        <button v-for="city in quickPicks" :key="city.id" type="button"
          :class="['quick-pick', { favorite: city.favorite }]" :disabled="selectedIds.includes(city.id)" @click="add(city)">
          <span v-if="city.favorite" aria-hidden="true">★ </span>{{ city.name }}
        </button>
      </div>
    </div>
    <p v-else class="ui-hint">Wybrano maksymalną liczbę miast. Usuń któreś, aby dodać inne.</p>
  </div>
</template>

<style scoped>
.picker-selected {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;
  min-height: 2.25rem;
  align-items: center;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.35rem 0.5rem 0.35rem 0.75rem;
  background-color: white;
  border: 2px solid;
  border-radius: 9999px;
  font-size: 0.875rem;
  font-weight: 500;
  color: #1e293b;
}

.chip-dot {
  width: 0.6rem;
  height: 0.6rem;
  border-radius: 50%;
}

.chip-country {
  color: #64748b;
  font-weight: 400;
}

.chip-remove {
  border: none;
  background: none;
  color: #64748b;
  font-size: 1.1rem;
  line-height: 1;
  cursor: pointer;
  padding: 0 0.25rem;
  border-radius: 50%;
}

.chip-remove:hover {
  color: #ef4444;
}

.search-box {
  position: relative;
}

.search-results {
  position: absolute;
  z-index: 20;
  left: 0;
  right: 0;
  top: calc(100% + 4px);
  background-color: white;
  border-radius: 0.375rem;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}

.search-result {
  display: block;
  width: 100%;
  text-align: left;
  padding: 0.6rem 0.75rem;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 0.9rem;
}

.search-result:hover:not(:disabled) {
  background-color: #eff6ff;
}

.search-result:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.quick-picks {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
  margin-top: 0.75rem;
}

.quick-label {
  margin: 0 0.25rem 0 0;
}

.quick-pick {
  padding: 0.35rem 0.75rem;
  background-color: #e2e8f0;
  border: none;
  border-radius: 0.25rem;
  font-size: 0.8rem;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: background-color 0.2s;
}

.quick-pick:hover:not(:disabled) {
  background-color: #cbd5e1;
}

.quick-pick.favorite {
  color: #1d4ed8;
}

.quick-pick:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
</style>
