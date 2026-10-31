<script setup>
// Formularz reguły alertu (tworzenie / edycja) w oknie modalnym.
// Próg wpisywany jest w jednostkach użytkownika i przeliczany na metryczne przed wysłaniem.
import { reactive, ref, computed, watch } from "vue";
import { api, ApiError } from "@/api/client";
import { useSettingsStore } from "@/stores/settings";
import { COOLDOWN_OPTIONS, quantityOf, toDisplay, toMetric, unitLabel } from "@/utils/alertUnits";

const props = defineProps({
  rule: { type: Object, default: null },
  favorites: { type: Array, default: () => [] },
  meta: { type: Object, required: true },
  defaultCityId: { type: Number, default: null },
});

const emit = defineEmits(["saved", "cancel"]);

const settings = useSettingsStore();
const unitSettings = computed(() => ({ units: settings.units, wind_unit: settings.wind_unit }));

const editing = computed(() => !!props.rule);
const types = computed(() => props.meta.types || []);
const conditions = computed(() => props.meta.conditions || []);

const form = reactive({
  city_id: props.rule?.city_id ?? props.defaultCityId ?? props.favorites[0]?.openweather_id ?? null,
  type: props.rule?.type ?? "temp_below",
  threshold: props.rule ? toDisplay(props.rule.type, props.rule.threshold, unitSettings.value) : 0,
  condition: props.rule?.condition ?? "rain",
  channels: props.rule ? [...props.rule.channels] : ["mail"],
  cooldown_minutes: props.rule?.cooldown_minutes ?? props.meta.cooldown?.default ?? 360,
  label: props.rule?.label ?? "",
});

const saving = ref(false);
const error = ref(null);
const fieldErrors = ref({});

const currentType = computed(() => types.value.find((t) => t.value === form.type) || null);
const isCondition = computed(() => form.type === "condition");
const isForecast = computed(() => !!currentType.value?.forecast);
const unit = computed(() => unitLabel(form.type, unitSettings.value));

// Zakres progu w jednostkach użytkownika (z metrycznego zakresu API)
const range = computed(() => {
  const r = currentType.value?.range;
  if (!r) return null;
  const [a, b] = [toDisplay(form.type, r[0], unitSettings.value), toDisplay(form.type, r[1], unitSettings.value)];
  return [Math.min(a, b), Math.max(a, b)];
});

const thresholdStep = computed(() => {
  const q = quantityOf(form.type);
  if (q === "pressure" && settings.units === "imperial") return 0.01;
  return q === "temp" || (q === "wind" && settings.wind_unit === "ms") ? 0.1 : 1;
});

const defaultThresholdHint = computed(() => {
  const d = currentType.value?.default_threshold;
  if (d === null || d === undefined) return null;
  return `${toDisplay(form.type, d, unitSettings.value)}${unit.value === "%" || quantityOf(form.type) === "temp" ? "" : " "}${unit.value}`;
});

const typeHints = {
  temp_below: "Alert, gdy zmierzona temperatura spadnie poniżej progu.",
  temp_above: "Alert, gdy zmierzona temperatura przekroczy próg.",
  wind_above: "Alert, gdy średnia prędkość wiatru przekroczy próg.",
  humidity_above: "Alert, gdy wilgotność powietrza przekroczy próg.",
  pressure_below: "Alert, gdy ciśnienie spadnie poniżej progu.",
  condition: "Alert, gdy w mieście wystąpi wybrane zjawisko.",
  rain_forecast_tomorrow: "Sprawdzane co 3 godziny na podstawie prognozy – próg to prawdopodobieństwo deszczu.",
  frost_forecast: "Sprawdzane co 3 godziny – alert, gdy prognozowana temperatura w ciągu 24 h spadnie poniżej progu.",
};

// Zmiana typu: domyślny próg dla prognoz, czyszczenie błędów
watch(
  () => form.type,
  (type, previous) => {
    if (!previous) return;
    fieldErrors.value = {};
    const t = types.value.find((x) => x.value === type);
    if (quantityOf(type) !== quantityOf(previous)) {
      form.threshold =
        t?.default_threshold !== null && t?.default_threshold !== undefined
          ? toDisplay(type, t.default_threshold, unitSettings.value)
          : null;
    }
  }
);

const toggleChannel = (channel) => {
  form.channels = form.channels.includes(channel)
    ? form.channels.filter((c) => c !== channel)
    : [...form.channels, channel];
};

const submit = async () => {
  error.value = null;
  fieldErrors.value = {};

  if (form.channels.length === 0) {
    fieldErrors.value = { channels: "Wybierz co najmniej jeden kanał powiadomień." };
    return;
  }

  const payload = {
    city_id: form.city_id,
    type: form.type,
    channels: form.channels,
    cooldown_minutes: Number(form.cooldown_minutes),
    label: form.label?.trim() || null,
  };

  if (isCondition.value) {
    payload.condition = form.condition;
  } else {
    payload.threshold = toMetric(form.type, form.threshold, unitSettings.value);
  }

  saving.value = true;
  try {
    const data = editing.value
      ? await api.put(`/alerts/${props.rule.id}`, payload)
      : await api.post("/alerts", payload);
    emit("saved", data.data);
  } catch (e) {
    if (e instanceof ApiError && e.errors) {
      fieldErrors.value = Object.fromEntries(
        Object.entries(e.errors).map(([k, v]) => [k.split(".")[0], Array.isArray(v) ? v[0] : v])
      );
      // Komunikat zakresu z API jest metryczny – pokazujemy zakres w jednostkach użytkownika
      if (fieldErrors.value.threshold && range.value && fieldErrors.value.threshold.includes("zakresie")) {
        fieldErrors.value.threshold = `Próg musi mieścić się w zakresie od ${range.value[0]} do ${range.value[1]} ${unit.value}.`;
      }
    } else {
      error.value = e?.message || "Nie udało się zapisać alertu";
    }
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <div class="ui-modal-overlay" @click.self="emit('cancel')">
    <div class="ui-modal alert-form-modal" role="dialog" aria-modal="true" aria-labelledby="alert-form-title">
      <h3 id="alert-form-title" class="ui-modal-title">{{ editing ? "Edytuj alert" : "Nowy alert" }}</h3>

      <form class="ui-form" @submit.prevent="submit" novalidate>
        <div class="ui-form-group">
          <label for="alert-city" class="ui-label">Miasto</label>
          <select id="alert-city" v-model="form.city_id" class="ui-input" required>
            <option v-for="city in favorites" :key="city.openweather_id" :value="city.openweather_id">
              {{ city.name }}{{ city.country ? `, ${city.country}` : "" }}
            </option>
          </select>
          <p v-if="fieldErrors.city_id" class="ui-error-message">{{ fieldErrors.city_id }}</p>
        </div>

        <div class="ui-form-group">
          <label for="alert-type" class="ui-label">Rodzaj alertu</label>
          <select id="alert-type" v-model="form.type" class="ui-input">
            <optgroup label="Bieżąca pogoda">
              <option v-for="t in types.filter((t) => !t.forecast)" :key="t.value" :value="t.value">{{ t.label }}</option>
            </optgroup>
            <optgroup label="Prognoza">
              <option v-for="t in types.filter((t) => t.forecast)" :key="t.value" :value="t.value">{{ t.label }}</option>
            </optgroup>
          </select>
          <p class="ui-hint">{{ typeHints[form.type] }}</p>
          <p v-if="fieldErrors.type" class="ui-error-message">{{ fieldErrors.type }}</p>
        </div>

        <div v-if="isCondition" class="ui-form-group">
          <label for="alert-condition" class="ui-label">Zjawisko</label>
          <select id="alert-condition" v-model="form.condition" class="ui-input">
            <option v-for="c in conditions" :key="c.value" :value="c.value">
              {{ c.label.charAt(0).toUpperCase() + c.label.slice(1) }}
            </option>
          </select>
          <p v-if="fieldErrors.condition" class="ui-error-message">{{ fieldErrors.condition }}</p>
        </div>

        <div v-else class="ui-form-group">
          <label for="alert-threshold" class="ui-label">
            Próg{{ isForecast ? " (opcjonalnie)" : "" }}
          </label>
          <div class="threshold-row">
            <input
              id="alert-threshold"
              v-model.number="form.threshold"
              type="number"
              inputmode="decimal"
              class="ui-input"
              :step="thresholdStep"
              :min="range?.[0]"
              :max="range?.[1]"
              :placeholder="defaultThresholdHint ? `Domyślnie ${defaultThresholdHint}` : ''"
            />
            <span class="threshold-unit">{{ unit }}</span>
          </div>
          <p v-if="range" class="ui-hint">Zakres: {{ range[0] }} – {{ range[1] }} {{ unit }}</p>
          <p v-if="fieldErrors.threshold" class="ui-error-message">{{ fieldErrors.threshold }}</p>
        </div>

        <div class="ui-form-group">
          <span class="ui-label">Kanały powiadomień</span>
          <div class="channels">
            <label class="channel-option">
              <input type="checkbox" :checked="form.channels.includes('mail')" @change="toggleChannel('mail')" />
              E-mail
            </label>
            <label class="channel-option">
              <input type="checkbox" :checked="form.channels.includes('push')" @change="toggleChannel('push')" />
              Push (przeglądarka / telefon)
            </label>
          </div>
          <p v-if="fieldErrors.channels" class="ui-error-message">{{ fieldErrors.channels }}</p>
        </div>

        <div class="ui-form-group">
          <label for="alert-cooldown" class="ui-label">Nie częściej niż co</label>
          <select id="alert-cooldown" v-model.number="form.cooldown_minutes" class="ui-input">
            <option v-for="o in COOLDOWN_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
          <p v-if="fieldErrors.cooldown_minutes" class="ui-error-message">{{ fieldErrors.cooldown_minutes }}</p>
        </div>

        <div class="ui-form-group">
          <label for="alert-label" class="ui-label">Nazwa (opcjonalnie)</label>
          <input id="alert-label" v-model="form.label" type="text" maxlength="100" class="ui-input" placeholder="np. Mróz na działce" />
          <p v-if="fieldErrors.label" class="ui-error-message">{{ fieldErrors.label }}</p>
        </div>

        <div v-if="error" class="ui-form-error">{{ error }}</div>

        <div class="ui-btn-row">
          <button type="button" class="ui-btn ui-btn--secondary" @click="emit('cancel')">Anuluj</button>
          <button type="submit" class="ui-btn" :disabled="saving || !form.city_id">
            {{ saving ? "Zapisywanie..." : editing ? "Zapisz zmiany" : "Dodaj alert" }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.alert-form-modal {
  max-width: 480px;
  max-height: 90vh;
  overflow-y: auto;
}

.threshold-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.threshold-unit {
  min-width: 3rem;
  color: #4b5563;
  font-weight: 500;
}

.channels {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 1.25rem;
}

.channel-option {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  color: #1f2937;
  cursor: pointer;
}

.channel-option input {
  width: 1rem;
  height: 1rem;
  accent-color: #3b82f6;
}
</style>
