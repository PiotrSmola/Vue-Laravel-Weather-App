<script setup>
import { reactive, ref, computed, onMounted, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useSettingsStore } from "@/stores/settings";
import { useWeatherStore } from "@/stores/weather";
import { useUnits } from "@/composables/useUnits";

const authStore = useAuthStore();
const settingsStore = useSettingsStore();
const weatherStore = useWeatherStore();
const { formatTemp, formatWind } = useUnits();

const isLoggedIn = computed(() => !!authStore.user);

const form = reactive({ ...settingsStore.values });
const saved = ref(false);
const loadingFavorites = ref(false);

// Formularz odświeżany, gdy ustawienia zostaną wczytane z serwera (np. po zalogowaniu)
watch(
  () => settingsStore.values,
  (values) => Object.assign(form, values)
);

const unitOptions = [
  { value: "metric", label: "Metryczny (°C, hPa, km)" },
  { value: "imperial", label: "Imperialny (°F, inHg, mi)" },
];

const windOptions = [
  { value: "kmh", label: "km/h" },
  { value: "ms", label: "m/s" },
  { value: "mph", label: "mph" },
];

const langOptions = [
  { value: "pl", label: "Polski" },
  { value: "en", label: "Angielski" },
];

const favorites = computed(() => weatherStore.userFavorites || []);

onMounted(async () => {
  if (isLoggedIn.value && favorites.value.length === 0) {
    loadingFavorites.value = true;
    await weatherStore.getUserFavorites();
    loadingFavorites.value = false;
  }
});

const save = async () => {
  saved.value = false;
  const payload = {
    units: form.units,
    wind_unit: form.wind_unit,
    lang: form.lang,
  };
  if (isLoggedIn.value) {
    payload.home_city_id = form.home_city_id || null;
  }
  const ok = await settingsStore.update(payload);
  saved.value = ok;
};
</script>

<template>
  <main>
    <div class="ui-card ui-card--medium">
      <h1 class="ui-title">Ustawienia</h1>

      <form @submit.prevent="save" class="ui-form">
        <div class="ui-form-group">
          <label for="units" class="ui-label">System jednostek</label>
          <select id="units" v-model="form.units" class="ui-input">
            <option v-for="o in unitOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>

        <div class="ui-form-group">
          <label for="wind_unit" class="ui-label">Jednostka prędkości wiatru</label>
          <select id="wind_unit" v-model="form.wind_unit" class="ui-input">
            <option v-for="o in windOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>

        <div class="ui-form-group">
          <label for="lang" class="ui-label">Język opisów pogody</label>
          <select id="lang" v-model="form.lang" class="ui-input">
            <option v-for="o in langOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
          <p class="ui-hint">Dotyczy opisów warunków pogodowych zwracanych przez OpenWeatherMap.</p>
        </div>

        <div v-if="isLoggedIn" class="ui-form-group">
          <label for="home_city" class="ui-label">Miasto domowe</label>
          <select
            id="home_city"
            v-model="form.home_city_id"
            class="ui-input"
            :disabled="loadingFavorites"
          >
            <option :value="null">Brak (domyślna lista miast)</option>
            <option v-for="city in favorites" :key="city.id" :value="city.openweather_id">
              {{ city.name }}{{ city.country ? `, ${city.country}` : "" }}
            </option>
          </select>
          <p class="ui-hint">
            {{ favorites.length === 0 && !loadingFavorites
              ? "Dodaj miasto do ulubionych, aby wybrać je jako domowe."
              : "Miasto domowe jest wyświetlane jako pierwsze na stronie głównej." }}
          </p>
        </div>

        <p v-else class="ui-hint">
          Ustawienia są zapisywane w tej przeglądarce. Zaloguj się, aby zapisać je na koncie
          i wybrać miasto domowe.
        </p>

        <p class="ui-muted">
          Przykład: {{ formatTemp(21.5) }}, wiatr {{ formatWind(5) }}
        </p>

        <div v-if="settingsStore.error" class="ui-form-error">
          {{ settingsStore.error }}
        </div>
        <div v-else-if="saved" class="ui-form-success">
          Ustawienia zostały zapisane.
        </div>

        <button type="submit" class="ui-btn" :disabled="settingsStore.saving">
          {{ settingsStore.saving ? "Zapisywanie..." : "Zapisz ustawienia" }}
        </button>
      </form>
    </div>
  </main>
</template>
