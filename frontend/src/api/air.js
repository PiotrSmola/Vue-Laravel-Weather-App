// Zapytania jakości powietrza (/api/air/...). {cityId} to ID miasta w OpenWeather.
import { api } from "@/api/client";

export function fetchAirQuality(cityId, options = {}) {
  return api.get(`/air/${cityId}`, options);
}

export function fetchAirQualityByCoordinates(lat, lon, options = {}) {
  return api.get("/air/coordinates", { ...options, params: { lat, lon } });
}

export function fetchAirForecast(cityId, options = {}) {
  return api.get(`/air/${cityId}/forecast`, options);
}

// Historia (wymaga zalogowania i miasta w ulubionych). from/to: Date lub ISO 8601
export function fetchAirHistory(cityId, { from, to } = {}, options = {}) {
  const iso = (value) => (value instanceof Date ? value.toISOString() : value);
  return api.get(`/air/${cityId}/historical`, {
    ...options,
    params: { from: iso(from), to: iso(to) },
  });
}
