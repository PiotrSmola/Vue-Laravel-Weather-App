import { defineStore } from "pinia";
import { api } from "@/api/client";
import { SETTINGS_STORAGE_KEY } from "@/config";
import { UNIT_SYSTEMS, WIND_UNITS } from "@/utils/units";

// Ustawienia użytkownika.
// - Gość: zapisywane w localStorage.
// - Zalogowany: pobierane z API (z `user.settings` lub GET /api/user/settings),
//   zapisywane przez PUT /api/user/settings. Po zalogowaniu obowiązują ustawienia z serwera.
// - Po wylogowaniu wracają lokalne ustawienia gościa.

export const LANGUAGES = ["pl", "en"];

export const DEFAULT_SETTINGS = Object.freeze({
  units: "metric",
  wind_unit: "kmh",
  lang: "pl",
  // ID miasta w OpenWeather (openweather_id), wybierane z ulubionych
  home_city_id: null,
});

const KEYS = Object.keys(DEFAULT_SETTINGS);

// Pozostawia tylko znane klucze z poprawnymi wartościami
function sanitize(input) {
  const out = {};
  if (!input || typeof input !== "object") return out;
  if (UNIT_SYSTEMS.includes(input.units)) out.units = input.units;
  if (WIND_UNITS.includes(input.wind_unit)) out.wind_unit = input.wind_unit;
  if (LANGUAGES.includes(input.lang)) out.lang = input.lang;
  if ("home_city_id" in input) {
    const id = input.home_city_id;
    out.home_city_id = id === null || id === "" || id === undefined ? null : Number(id);
  }
  return out;
}

function readLocal() {
  try {
    const raw = localStorage.getItem(SETTINGS_STORAGE_KEY);
    return raw ? sanitize(JSON.parse(raw)) : {};
  } catch {
    return {};
  }
}

function writeLocal(values) {
  try {
    localStorage.setItem(SETTINGS_STORAGE_KEY, JSON.stringify(values));
  } catch {
    // brak dostępu do localStorage (tryb prywatny itp.) – ignorujemy
  }
}

// Ustawienie wyłącznie lokalne (nie jest wysyłane do API): "używaj mojej lokalizacji przy starcie"
const LOCATION_ON_START_KEY = "use_location_on_start";

function readLocationOnStart() {
  try {
    return localStorage.getItem(LOCATION_ON_START_KEY) === "1";
  } catch {
    return false;
  }
}

export const useSettingsStore = defineStore("settingsStore", {
  state: () => ({
    ...DEFAULT_SETTINGS,
    ...readLocal(),
    // "local" – ustawienia gościa, "server" – ustawienia konta
    source: "local",
    loading: false,
    saving: false,
    error: null,
    use_location_on_start: readLocationOnStart(),
  }),

  getters: {
    values: (state) => Object.fromEntries(KEYS.map((k) => [k, state[k]])),
    isImperial: (state) => state.units === "imperial",
  },

  actions: {
    apply(values) {
      const clean = sanitize(values);
      for (const key of KEYS) {
        if (key in clean) this[key] = clean[key];
      }
    },

    // Wywoływane przez auth store, gdy znany jest zalogowany użytkownik
    async loadForUser(user) {
      this.error = null;
      if (user && user.settings && typeof user.settings === "object") {
        this.$patch({ ...DEFAULT_SETTINGS, ...sanitize(user.settings), source: "server" });
        return this.values;
      }
      return this.fetchFromServer();
    },

    async fetchFromServer() {
      this.loading = true;
      try {
        const data = await api.get("/user/settings");
        this.$patch({ ...DEFAULT_SETTINGS, ...sanitize(data), source: "server" });
      } catch (error) {
        // Backend może jeszcze nie obsługiwać ustawień – zostajemy przy lokalnych
        console.warn("Nie udało się pobrać ustawień użytkownika:", error.message);
        this.source = "local";
      } finally {
        this.loading = false;
      }
      return this.values;
    },

    // Wywoływane po wylogowaniu
    resetToLocal() {
      this.$patch({ ...DEFAULT_SETTINGS, ...readLocal(), source: "local", error: null });
    },

    setUseLocationOnStart(value) {
      this.use_location_on_start = !!value;
      try {
        localStorage.setItem(LOCATION_ON_START_KEY, value ? "1" : "0");
      } catch {
        // brak dostępu do localStorage – ustawienie obowiązuje do odświeżenia strony
      }
    },

    // Częściowa aktualizacja, np. update({ units: "imperial" })
    async update(partial) {
      const clean = sanitize(partial);
      const previous = this.values;
      this.apply(clean);
      this.error = null;

      if (this.source !== "server") {
        writeLocal(this.values);
        return true;
      }

      this.saving = true;
      try {
        const data = await api.put("/user/settings", clean);
        if (data && typeof data === "object") this.apply(data);
        return true;
      } catch (error) {
        console.error("Nie udało się zapisać ustawień:", error);
        this.error = error.message || "Nie udało się zapisać ustawień";
        this.apply(previous);
        return false;
      } finally {
        this.saving = false;
      }
    },
  },
});
