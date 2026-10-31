// Progi alertów: backend przechowuje je metrycznie (°C, m/s, %, hPa),
// a użytkownik wpisuje i widzi je w swoich jednostkach (ustawienia: units, wind_unit).

import {
  TEMP_LABELS,
  WIND_LABELS,
  PRESSURE_LABELS,
  convertTemp,
  convertWind,
  convertPressure,
  fToC,
  kmhToMs,
  mphToMs,
  round,
} from "@/utils/units";

const TEMP_TYPES = ["temp_below", "temp_above", "frost_forecast"];
const PERCENT_TYPES = ["humidity_above", "rain_forecast_tomorrow"];

// Rodzaj wielkości dla typu reguły
export function quantityOf(type) {
  if (TEMP_TYPES.includes(type)) return "temp";
  if (type === "wind_above") return "wind";
  if (type === "pressure_below") return "pressure";
  if (PERCENT_TYPES.includes(type)) return "percent";
  return null; // "condition" – bez progu
}

export function unitLabel(type, { units = "metric", wind_unit = "kmh" } = {}) {
  switch (quantityOf(type)) {
    case "temp":
      return TEMP_LABELS[units] || "°C";
    case "wind":
      return WIND_LABELS[wind_unit] || "km/h";
    case "pressure":
      return PRESSURE_LABELS[units] || "hPa";
    case "percent":
      return "%";
    default:
      return "";
  }
}

// Liczba miejsc po przecinku przy wyświetlaniu progu
export function decimalsFor(type, { units = "metric", wind_unit = "kmh" } = {}) {
  switch (quantityOf(type)) {
    case "temp":
      return 1;
    case "wind":
      return wind_unit === "ms" ? 1 : 0;
    case "pressure":
      return units === "imperial" ? 2 : 0;
    default:
      return 0;
  }
}

// Wartość metryczna -> jednostki użytkownika (zaokrąglona)
export function toDisplay(type, metric, settings = {}) {
  if (metric === null || metric === undefined || metric === "") return null;
  const { units = "metric", wind_unit = "kmh" } = settings;
  let value = Number(metric);
  switch (quantityOf(type)) {
    case "temp":
      value = convertTemp(value, units);
      break;
    case "wind":
      value = convertWind(value, wind_unit);
      break;
    case "pressure":
      value = convertPressure(value, units);
      break;
  }
  return round(value, decimalsFor(type, settings));
}

// Wartość w jednostkach użytkownika -> metryczna (do wysłania do API)
export function toMetric(type, value, { units = "metric", wind_unit = "kmh" } = {}) {
  if (value === null || value === undefined || value === "") return null;
  const v = Number(value);
  if (Number.isNaN(v)) return null;
  switch (quantityOf(type)) {
    case "temp":
      return round(units === "imperial" ? fToC(v) : v, 2);
    case "wind":
      return round(wind_unit === "kmh" ? kmhToMs(v) : wind_unit === "mph" ? mphToMs(v) : v, 2);
    case "pressure":
      return round(units === "imperial" ? v / 0.0295299830714 : v, 2);
    default:
      return round(v, 2);
  }
}

// Tekst progu z jednostką, np. "-5°C", "36 km/h", "1000 hPa", "80%"
export function formatThreshold(type, metric, settings = {}) {
  const value = toDisplay(type, metric, settings);
  if (value === null) return "—";
  const label = unitLabel(type, settings);
  return quantityOf(type) === "temp" || label === "%" ? `${value}${label}` : `${value} ${label}`;
}

export const COOLDOWN_OPTIONS = [
  { value: 30, label: "30 minut" },
  { value: 60, label: "1 godzina" },
  { value: 180, label: "3 godziny" },
  { value: 360, label: "6 godzin" },
  { value: 720, label: "12 godzin" },
  { value: 1440, label: "24 godziny" },
  { value: 2880, label: "2 dni" },
  { value: 10080, label: "7 dni" },
];

export function cooldownLabel(minutes) {
  const option = COOLDOWN_OPTIONS.find((o) => o.value === Number(minutes));
  if (option) return option.label;
  const m = Number(minutes) || 0;
  return m % 60 === 0 ? `${m / 60} h` : `${m} min`;
}
