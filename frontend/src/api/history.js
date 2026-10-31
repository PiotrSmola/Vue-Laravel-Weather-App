// Historia pomiarów ulubionego miasta i porównanie miast.
//
//   const range = historyRange("30d");                 // { from, to } (ISO 8601)
//   await fetchHistorical(759734, range);              // pomiary (GET /cities/{id}/historical)
//   await fetchHistoryDaily(759734, range);            // agregaty dzienne
//   await fetchHistorySummary(759734, range);          // rekordy, średnie, porównania
//   await exportHistoryCsv(759734, range);             // pobranie pliku CSV
//   await fetchCompare([756135, 3094802]);             // porównanie 2-4 miast
//
// Dni są grupowane w strefie czasowej przeglądarki (parametr tz).

import { api } from "@/api/client";
import { downloadFile } from "@/api/download";
import { weatherParams } from "@/stores/weather";

export const HISTORY_PRESETS = [
  { key: "24h", label: "24h", days: 1 },
  { key: "7d", label: "7 dni", days: 7 },
  { key: "30d", label: "30 dni", days: 30 },
  { key: "90d", label: "90 dni", days: 90 },
  { key: "custom", label: "Własny zakres" },
];

export function browserTimezone() {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || undefined;
  } catch {
    return undefined;
  }
}

// "YYYY-MM-DD" w lokalnej strefie (dla <input type="date">)
export function toDateInput(date) {
  const d = new Date(date);
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

// Zakres dla presetu lub własnych dat (lokalne początki/końce dni → ISO z UTC)
export function historyRange(preset, custom = {}) {
  const now = new Date();

  if (preset === "custom") {
    if (!custom.from || !custom.to) return null;
    const from = new Date(`${custom.from}T00:00:00`);
    const to = new Date(`${custom.to}T23:59:59`);
    if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime())) return null;
    return { from: from.toISOString(), to: to.toISOString() };
  }

  const days = HISTORY_PRESETS.find((p) => p.key === preset)?.days ?? 7;
  const from = new Date(now.getTime() - days * 24 * 3600 * 1000);
  return { from: from.toISOString(), to: now.toISOString() };
}

// Długość zakresu w dniach
export function rangeDays(range) {
  if (!range) return 0;
  return (new Date(range.to) - new Date(range.from)) / (24 * 3600 * 1000);
}

const rangeParams = (range) => ({ from: range?.from, to: range?.to, tz: browserTimezone() });

export function fetchHistorical(cityId, range) {
  return api.get(`/cities/${cityId}/historical`, { params: { from: range?.from, to: range?.to } });
}

export function fetchHistoryDaily(cityId, range) {
  return api.get(`/cities/${cityId}/history/daily`, { params: rangeParams(range) });
}

export function fetchHistorySummary(cityId, range) {
  return api.get(`/cities/${cityId}/history/summary`, { params: rangeParams(range) });
}

export function exportHistoryCsv(cityId, range) {
  return downloadFile(`/cities/${cityId}/history/export`, {
    params: { ...rangeParams(range), format: "csv" },
    filename: `historia-${cityId}.csv`,
  });
}

export const COMPARE_MIN = 2;
export const COMPARE_MAX = 4;

export function fetchCompare(ids, options = {}) {
  return api.get("/compare", { params: weatherParams({ ids: ids.join(",") }), ...options });
}
