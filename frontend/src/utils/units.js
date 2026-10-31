// Czyste konwertery i formatery jednostek. Backend zawsze zwraca wartości metryczne:
// temperatura °C, wiatr m/s, ciśnienie hPa, widoczność/odległość w metrach lub km.
// W komponentach używaj composable `useUnits()` – reaguje na zmiany ustawień.

export const UNIT_SYSTEMS = ["metric", "imperial"];
export const WIND_UNITS = ["ms", "kmh", "mph"];

export const TEMP_LABELS = { metric: "°C", imperial: "°F" };
export const WIND_LABELS = { ms: "m/s", kmh: "km/h", mph: "mph" };
export const PRESSURE_LABELS = { metric: "hPa", imperial: "inHg" };
export const DISTANCE_LABELS = { metric: "km", imperial: "mi" };

const isNum = (v) => v !== null && v !== undefined && v !== "" && !Number.isNaN(Number(v));

// --- surowe konwertery ---
export const cToF = (c) => (c * 9) / 5 + 32;
export const fToC = (f) => ((f - 32) * 5) / 9;
export const msToKmh = (ms) => ms * 3.6;
export const msToMph = (ms) => ms * 2.236936;
export const kmhToMs = (kmh) => kmh / 3.6;
export const mphToMs = (mph) => mph / 2.236936;
export const hPaToInHg = (hPa) => hPa * 0.0295299830714;
export const kmToMi = (km) => km * 0.621371;
export const miToKm = (mi) => mi / 0.621371;
export const mToKm = (m) => m / 1000;

// --- konwersje wg ustawień ---
export function convertTemp(celsius, units = "metric") {
  if (!isNum(celsius)) return null;
  return units === "imperial" ? cToF(Number(celsius)) : Number(celsius);
}

export function convertWind(ms, windUnit = "kmh") {
  if (!isNum(ms)) return null;
  const v = Number(ms);
  if (windUnit === "kmh") return msToKmh(v);
  if (windUnit === "mph") return msToMph(v);
  return v;
}

export function convertPressure(hPa, units = "metric") {
  if (!isNum(hPa)) return null;
  return units === "imperial" ? hPaToInHg(Number(hPa)) : Number(hPa);
}

export function convertDistance(km, units = "metric") {
  if (!isNum(km)) return null;
  return units === "imperial" ? kmToMi(Number(km)) : Number(km);
}

export function convertVisibility(meters, units = "metric") {
  if (!isNum(meters)) return null;
  return convertDistance(mToKm(Number(meters)), units);
}

// Zaokrąglenie do `decimals` miejsc; -0 → 0
export function round(value, decimals = 0) {
  if (!isNum(value)) return null;
  const f = 10 ** decimals;
  const r = Math.round(Number(value) * f) / f;
  return Object.is(r, -0) ? 0 : r;
}

export const EMPTY_VALUE = "—";

export function formatNumber(value, decimals = 0) {
  const r = round(value, decimals);
  if (r === null) return EMPTY_VALUE;
  return decimals > 0 ? r.toFixed(decimals) : String(r);
}

const withUnit = (num, label, unit, space = true) =>
  num === EMPTY_VALUE || unit === false ? num : `${num}${space ? " " : ""}${label}`;

// Opcje formaterów: { decimals, unit: false (bez jednostki) }
export function formatTemp(celsius, units = "metric", { decimals = 0, unit = true } = {}) {
  return withUnit(formatNumber(convertTemp(celsius, units), decimals), TEMP_LABELS[units] || "°C", unit, false);
}

export function formatWind(ms, windUnit = "kmh", { decimals, unit = true } = {}) {
  const d = decimals ?? (windUnit === "ms" ? 1 : 0);
  return withUnit(formatNumber(convertWind(ms, windUnit), d), WIND_LABELS[windUnit] || "km/h", unit);
}

export function formatPressure(hPa, units = "metric", { decimals, unit = true } = {}) {
  const d = decimals ?? (units === "imperial" ? 2 : 0);
  return withUnit(formatNumber(convertPressure(hPa, units), d), PRESSURE_LABELS[units] || "hPa", unit);
}

export function formatDistance(km, units = "metric", { decimals = 0, unit = true } = {}) {
  return withUnit(formatNumber(convertDistance(km, units), decimals), DISTANCE_LABELS[units] || "km", unit);
}

export function formatVisibility(meters, units = "metric", { decimals = 0, unit = true } = {}) {
  return withUnit(formatNumber(convertVisibility(meters, units), decimals), DISTANCE_LABELS[units] || "km", unit);
}
