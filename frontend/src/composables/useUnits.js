// Formatowanie wartości pogodowych zgodnie z ustawieniami użytkownika (reaktywnie).
//
//   const { formatTemp, formatWind, tempUnit } = useUnits();
//   {{ formatTemp(weather.main.temp) }}            -> "12°C" / "54°F"
//   {{ formatTemp(t, { unit: false }) }}{{ tempUnit }}
//   {{ formatWind(weather.wind.speed) }}           -> "14 km/h" (wejście w m/s)
//   {{ formatPressure(weather.main.pressure) }}    -> "1013 hPa" / "29.91 inHg"
//   {{ formatVisibility(weather.visibility) }}     -> "10 km" / "6 mi" (wejście w metrach)
//   {{ formatDistance(km) }}                       -> "5 km" / "3 mi"
//
// Funkcje czytają stan store'u ustawień, więc wywołane w template lub computed
// automatycznie przeliczają się po zmianie ustawień.

import { computed } from "vue";
import { useSettingsStore } from "@/stores/settings";
import * as U from "@/utils/units";

export function useUnits() {
  const settings = useSettingsStore();

  const units = computed(() => settings.units);
  const windUnitKey = computed(() => settings.wind_unit);

  return {
    units,
    windUnitKey,
    tempUnit: computed(() => U.TEMP_LABELS[settings.units]),
    windUnit: computed(() => U.WIND_LABELS[settings.wind_unit]),
    pressureUnit: computed(() => U.PRESSURE_LABELS[settings.units]),
    distanceUnit: computed(() => U.DISTANCE_LABELS[settings.units]),

    // surowe wartości liczbowe w jednostkach użytkownika
    convertTemp: (c) => U.convertTemp(c, settings.units),
    convertWind: (ms) => U.convertWind(ms, settings.wind_unit),
    convertPressure: (hPa) => U.convertPressure(hPa, settings.units),
    convertVisibility: (m) => U.convertVisibility(m, settings.units),
    convertDistance: (km) => U.convertDistance(km, settings.units),

    // sformatowane napisy z jednostką
    formatTemp: (c, opts) => U.formatTemp(c, settings.units, opts),
    formatWind: (ms, opts) => U.formatWind(ms, settings.wind_unit, opts),
    formatPressure: (hPa, opts) => U.formatPressure(hPa, settings.units, opts),
    formatVisibility: (m, opts) => U.formatVisibility(m, settings.units, opts),
    formatDistance: (km, opts) => U.formatDistance(km, settings.units, opts),
  };
}

export default useUnits;
