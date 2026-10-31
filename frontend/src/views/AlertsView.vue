<script setup>
// Alerty pogodowe: reguły dla ulubionych miast, powiadomienia push na tym urządzeniu
// i lista wyzwolonych alertów.
import { ref, computed, onMounted, nextTick, watch } from "vue";
import { RouterLink, useRoute } from "vue-router";
import { api } from "@/api/client";
import { useSettingsStore } from "@/stores/settings";
import { formatThreshold, cooldownLabel } from "@/utils/alertUnits";
import AlertRuleForm from "@/components/alerts/AlertRuleForm.vue";
import AlertEventsList from "@/components/alerts/AlertEventsList.vue";
import PushSettings from "@/components/alerts/PushSettings.vue";

const settings = useSettingsStore();
const route = useRoute();

const rules = ref([]);
const limit = ref(20);
const meta = ref(null);
const favorites = ref([]);
const loading = ref(true);
const error = ref(null);
const actionError = ref(null);

const formOpen = ref(false);
const editedRule = ref(null);
const formCityId = ref(null);
const ruleToDelete = ref(null);
const deleting = ref(false);

const unitSettings = computed(() => ({ units: settings.units, wind_unit: settings.wind_unit }));
const limitReached = computed(() => rules.value.length >= limit.value);

const conditionLabels = computed(() =>
  Object.fromEntries((meta.value?.conditions || []).map((c) => [c.value, c.label]))
);

// Reguły pogrupowane wg miasta (kolejność jak w ulubionych, potem pozostałe)
const groups = computed(() => {
  const order = favorites.value.map((c) => c.openweather_id);
  const map = new Map();
  for (const rule of rules.value) {
    const key = rule.city_id;
    if (!map.has(key)) map.set(key, { city: rule.city, inFavorites: order.includes(key), rules: [] });
    map.get(key).rules.push(rule);
  }
  return [...map.values()].sort((a, b) => {
    const ia = order.indexOf(a.city?.id);
    const ib = order.indexOf(b.city?.id);
    return (ia === -1 ? 999 : ia) - (ib === -1 ? 999 : ib);
  });
});

const describe = (rule) => {
  const t = formatThreshold(rule.type, rule.effective_threshold, unitSettings.value);
  switch (rule.type) {
    case "temp_below":
      return `Temperatura poniżej ${t}`;
    case "temp_above":
      return `Temperatura powyżej ${t}`;
    case "wind_above":
      return `Wiatr powyżej ${t}`;
    case "humidity_above":
      return `Wilgotność powyżej ${t}`;
    case "pressure_below":
      return `Ciśnienie poniżej ${t}`;
    case "condition": {
      const label = conditionLabels.value[rule.condition] || rule.condition;
      return `Zjawisko: ${label}`;
    }
    case "rain_forecast_tomorrow":
      return `Deszcz jutro (szansa ≥ ${t})`;
    case "frost_forecast":
      return `Przymrozek w ciągu 24 h (poniżej ${t})`;
    default:
      return rule.type_label || rule.type;
  }
};

const channelsLabel = (rule) =>
  rule.channels.map((c) => (c === "mail" ? "e-mail" : c === "push" ? "push" : c)).join(", ");

const formatDate = (iso) =>
  iso ? new Date(iso).toLocaleString("pl-PL", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" }) : null;

async function load() {
  loading.value = true;
  error.value = null;
  try {
    const [rulesData, metaData, favoritesData] = await Promise.all([
      api.get("/alerts"),
      api.get("/alerts/meta"),
      api.get("/cities/user"),
    ]);
    rules.value = rulesData?.data || [];
    limit.value = rulesData?.limit || 20;
    meta.value = metaData;
    favorites.value = Array.isArray(favoritesData) ? favoritesData : [];
  } catch (e) {
    error.value = e?.message || "Nie udało się wczytać alertów";
  } finally {
    loading.value = false;
  }
}

const openCreate = (cityId = null) => {
  editedRule.value = null;
  formCityId.value = cityId;
  formOpen.value = true;
};

const openEdit = (rule) => {
  editedRule.value = rule;
  formOpen.value = true;
};

const onSaved = (saved) => {
  const index = rules.value.findIndex((r) => r.id === saved.id);
  if (index === -1) rules.value.push(saved);
  else rules.value.splice(index, 1, saved);
  formOpen.value = false;
};

const toggleActive = async (rule) => {
  actionError.value = null;
  const previous = rule.active;
  rule.active = !previous;
  try {
    const data = await api.patch(`/alerts/${rule.id}`, { active: rule.active });
    Object.assign(rule, data.data);
  } catch (e) {
    rule.active = previous;
    actionError.value = e?.message || "Nie udało się zmienić stanu alertu";
  }
};

const confirmDelete = async () => {
  if (!ruleToDelete.value) return;
  deleting.value = true;
  actionError.value = null;
  try {
    await api.delete(`/alerts/${ruleToDelete.value.id}`);
    rules.value = rules.value.filter((r) => r.id !== ruleToDelete.value.id);
    ruleToDelete.value = null;
  } catch (e) {
    actionError.value = e?.message || "Nie udało się usunąć alertu";
    ruleToDelete.value = null;
  } finally {
    deleting.value = false;
  }
};

// Link z dzwonka: /alerts#powiadomienia
const scrollToHash = async () => {
  if (!route.hash) return;
  await nextTick();
  document.querySelector(route.hash)?.scrollIntoView({ behavior: "smooth" });
};

watch(() => route.hash, scrollToHash);

onMounted(async () => {
  await load();
  scrollToHash();
});
</script>

<template>
  <main class="alerts-main">
    <div class="ui-card alerts-page">
      <h1 class="ui-title">Alerty pogodowe</h1>

      <PushSettings />

      <section class="rules-section">
        <div class="rules-header">
          <h2 class="ui-subtitle rules-title">Twoje alerty</h2>
          <div class="rules-header-actions">
            <span class="ui-muted">{{ rules.length }} / {{ limit }}</span>
            <button
              type="button"
              class="ui-btn ui-btn--sm"
              :disabled="loading || !meta || favorites.length === 0 || limitReached"
              @click="openCreate()"
            >
              + Dodaj alert
            </button>
          </div>
        </div>

        <div v-if="loading" class="rules-loading">
          <div class="ui-spinner"></div>
          <p class="ui-muted">Wczytywanie alertów...</p>
        </div>

        <div v-else-if="error" class="ui-form-error">{{ error }}</div>

        <template v-else>
          <div v-if="actionError" class="ui-form-error rules-feedback">{{ actionError }}</div>
          <p v-if="limitReached" class="ui-hint">Osiągnięto limit {{ limit }} alertów – usuń któryś, aby dodać nowy.</p>

          <div v-if="favorites.length === 0 && rules.length === 0" class="rules-empty">
            <p>Alerty można ustawić dla miast z ulubionych.</p>
            <RouterLink :to="{ name: 'home' }" class="ui-link">Dodaj miasto do ulubionych</RouterLink>
          </div>

          <div v-else-if="rules.length === 0" class="rules-empty">
            <p>Nie masz jeszcze żadnych alertów.</p>
            <p class="ui-muted">
              Np. powiadomienie o przymrozku, silnym wietrze albo burzy w Twoim mieście – e-mailem lub jako push.
            </p>
          </div>

          <div v-for="group in groups" :key="group.city?.id" class="city-group">
            <div class="city-group-header">
              <RouterLink
                v-if="group.city"
                :to="{ name: 'weather-details', params: { id: group.city.id } }"
                class="city-group-name"
              >
                {{ group.city.name }}<span v-if="group.city.country" class="city-group-country">, {{ group.city.country }}</span>
              </RouterLink>
              <button
                v-if="group.inFavorites && !limitReached"
                type="button"
                class="ui-link city-group-add"
                @click="openCreate(group.city.id)"
              >
                + alert
              </button>
            </div>
            <p v-if="!group.inFavorites" class="ui-hint">
              Miasto nie jest już w ulubionych – te alerty nie są sprawdzane.
            </p>

            <ul class="rule-list">
              <li v-for="rule in group.rules" :key="rule.id" class="rule-item" :class="{ 'is-inactive': !rule.active }">
                <label class="rule-switch" :title="rule.active ? 'Wyłącz alert' : 'Włącz alert'">
                  <input type="checkbox" :checked="rule.active" @change="toggleActive(rule)" />
                  <span class="rule-switch-track" aria-hidden="true"></span>
                  <span class="sr-only">{{ rule.active ? "Aktywny" : "Wyłączony" }}</span>
                </label>

                <div class="rule-info">
                  <span class="rule-name">{{ rule.label || describe(rule) }}</span>
                  <span v-if="rule.label" class="rule-detail">{{ describe(rule) }}</span>
                  <span class="rule-detail">
                    {{ channelsLabel(rule) }} · nie częściej niż co {{ cooldownLabel(rule.cooldown_minutes) }}
                    <template v-if="rule.forecast"> · prognoza</template>
                  </span>
                  <span v-if="rule.last_triggered_at" class="rule-detail">
                    Ostatnio: {{ formatDate(rule.last_triggered_at) }}
                  </span>
                </div>

                <div class="rule-actions">
                  <button type="button" class="rule-action" title="Edytuj" aria-label="Edytuj alert" @click="openEdit(rule)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="rule-action rule-action--danger"
                    title="Usuń"
                    aria-label="Usuń alert"
                    @click="ruleToDelete = rule"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </li>
            </ul>
          </div>
        </template>
      </section>

      <AlertEventsList />
    </div>

    <AlertRuleForm
      v-if="formOpen && meta"
      :rule="editedRule"
      :favorites="favorites"
      :meta="meta"
      :default-city-id="formCityId"
      @saved="onSaved"
      @cancel="formOpen = false"
    />

    <div v-if="ruleToDelete" class="ui-modal-overlay" @click.self="ruleToDelete = null">
      <div class="ui-modal" role="dialog" aria-modal="true">
        <h3 class="ui-modal-title">Usunąć alert?</h3>
        <p class="ui-modal-text">
          {{ ruleToDelete.label || describe(ruleToDelete) }} – {{ ruleToDelete.city?.name }}
        </p>
        <div class="ui-btn-row">
          <button type="button" class="ui-btn ui-btn--secondary" @click="ruleToDelete = null">Anuluj</button>
          <button type="button" class="ui-btn ui-btn--danger" :disabled="deleting" @click="confirmDelete">
            {{ deleting ? "Usuwanie..." : "Usuń" }}
          </button>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.alerts-page {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.alerts-page .ui-title {
  margin-bottom: 0;
}

/* Na telefonie karta na całą szerokość (globalny styl main ma szerokość 80%) */
@media (max-width: 640px) {
  .alerts-main {
    width: 100%;
    padding: 0 0.75rem;
  }

  .alerts-page {
    margin: 1rem 0;
    padding: 1.25rem 1rem;
  }

  .rule-item {
    gap: 0.5rem;
    padding: 0.6rem 0.5rem;
  }
}

.rules-header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.rules-title {
  margin-bottom: 0;
}

.rules-header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.rules-loading {
  text-align: center;
  padding: 1.5rem 0;
}

.rules-feedback {
  margin-bottom: 0.75rem;
}

.rules-empty {
  padding: 1.25rem;
  background-color: #f3f4f6;
  border-radius: 8px;
  text-align: center;
  color: #4b5563;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.city-group {
  margin-top: 1rem;
}

.city-group-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.city-group-name {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e40af;
}

.city-group-name:hover {
  text-decoration: underline;
}

.city-group-country {
  font-weight: 400;
  color: #4b5563;
  font-size: 0.9rem;
}

.city-group-add {
  font-size: 0.9rem;
}

.rule-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.rule-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 0.75rem;
  background-color: #dbeafe;
  border-radius: 6px;
  transition: background-color 0.2s, opacity 0.2s;
}

.rule-item.is-inactive {
  background-color: #f3f4f6;
}

.rule-item.is-inactive .rule-info {
  opacity: 0.6;
}

.rule-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.rule-name {
  font-weight: 500;
  color: #1e3a8a;
}

.rule-detail {
  font-size: 0.8rem;
  color: #4b5563;
}

.rule-actions {
  display: flex;
  gap: 0.25rem;
  flex-shrink: 0;
}

.rule-action {
  padding: 0.4rem;
  border-radius: 9999px;
  color: #3b82f6;
  transition: background-color 0.2s, color 0.2s;
}

.rule-action:hover {
  background-color: rgba(255, 255, 255, 0.6);
  color: #1d4ed8;
}

.rule-action--danger {
  color: #ef4444;
}

.rule-action--danger:hover {
  color: #dc2626;
}

.rule-action svg {
  width: 1.25rem;
  height: 1.25rem;
}

/* Przełącznik aktywności */
.rule-switch {
  position: relative;
  display: inline-flex;
  flex-shrink: 0;
  cursor: pointer;
}

.rule-switch input {
  position: absolute;
  opacity: 0;
  width: 100%;
  height: 100%;
  margin: 0;
  cursor: pointer;
}

.rule-switch-track {
  width: 2.5rem;
  height: 1.4rem;
  border-radius: 9999px;
  background-color: #d1d5db;
  position: relative;
  transition: background-color 0.2s;
}

.rule-switch-track::after {
  content: "";
  position: absolute;
  top: 0.15rem;
  left: 0.15rem;
  width: 1.1rem;
  height: 1.1rem;
  border-radius: 9999px;
  background-color: white;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
  transition: transform 0.2s;
}

.rule-switch input:checked + .rule-switch-track {
  background-color: #3b82f6;
}

.rule-switch input:checked + .rule-switch-track::after {
  transform: translateX(1.1rem);
}

.rule-switch input:focus-visible + .rule-switch-track {
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
}
</style>
