// Podpowiedzi pogodowe oparte na regułach (bez AI).
//
//   import { buildSuggestions } from "@/utils/suggestions";
//   const list = buildSuggestions(currentWeather, forecast, { formatTemp, formatWind, uvi });
//   // -> [{ id, icon, text, severity }]  severity: "danger" | "warning" | "info" | "good"
//
// Wejście: odpowiedzi /api/weather/... (jednostki metryczne: °C, m/s, prawdopodobieństwo 0–1).
// Formatery (np. z useUnits()) służą tylko do tekstu – progi zawsze liczone w jednostkach metrycznych.
// Moduł jest czysty: ten sam wynik dla tych samych danych (czas "teraz" = current.dt lub opts.now).
//
// Progi (metryczne):
//   Parasol         – max. prawdopodobieństwo opadów w ciągu 6 h ≥ 50% (≥ 70% → ostrzeżenie)
//                     lub aktualnie pada deszcz / mżawka
//   Burza           – burza teraz lub w prognozie na 12 h (kody 2xx)
//   Śnieg           – śnieg teraz lub w prognozie na 12 h (kody 6xx)
//   Oblodzenie rano – prognoza na 24 h, godz. 4–10 czasu lokalnego: temp. ≤ 1°C
//                     oraz (wilgotność ≥ 85% lub opady ≥ 30% lub opady w ostatnich godzinach)
//   Przymrozek      – min. temperatura w ciągu 24 h ≤ 0°C (gdy nie ma ostrzeżenia o oblodzeniu)
//   Silny wiatr     – wiatr ≥ 10 m/s lub porywy ≥ 15 m/s (teraz lub w ciągu 12 h);
//                     porywy ≥ 20 m/s → niebezpieczeństwo
//   Upał            – temp. odczuwalna ≥ 30°C (niebezpieczeństwo), ≥ 27°C (ostrzeżenie)
//   Mróz            – temp. odczuwalna ≤ −10°C
//   Mgła            – widoczność < 1000 m
//   Różnica dzień/noc – max − min w ciągu 24 h ≥ 12°C
//   UV              – tylko gdy podano opts.uvi: ≥ 8 bardzo wysokie, ≥ 6 wysokie, ≥ 3 umiarkowane
//   Ubiór           – wg temp. odczuwalnej: ≤ −5, ≤ 5, ≤ 12, ≤ 18, ≤ 24, > 24°C
//   Rower           – bez opadów (teraz i < 20% w ciągu 6 h), wiatr < 7 m/s, odczuwalna 10–28°C, widoczność ≥ 1000 m
//   Spacer          – bez opadów (teraz i < 20% w ciągu 6 h), wiatr < 6 m/s, odczuwalna 15–25°C, w dzień

export const THRESHOLDS = Object.freeze({
  umbrellaPop: 0.5,
  umbrellaPopHigh: 0.7,
  umbrellaHours: 6,
  stormHours: 12,
  icingTemp: 1,
  icingHumidity: 85,
  icingPop: 0.3,
  icingMorningFrom: 4,
  icingMorningTo: 10,
  frostTemp: 0,
  windSpeed: 10,
  windGust: 15,
  windGustDanger: 20,
  heatDanger: 30,
  heatWarning: 27,
  coldFeels: -10,
  fogVisibility: 1000,
  tempRange: 12,
  uvModerate: 3,
  uvHigh: 6,
  uvVeryHigh: 8,
  dryPop: 0.2,
  bikeWind: 7,
  bikeFeelsMin: 10,
  bikeFeelsMax: 28,
  walkWind: 6,
  walkFeelsMin: 15,
  walkFeelsMax: 25,
});

const SEVERITY_ORDER = { danger: 0, warning: 1, info: 2, good: 3 };

const defaultTemp = (c) => `${Math.round(c)}°C`;
const defaultWind = (ms) => `${Math.round(ms * 3.6)} km/h`;

const isNum = (v) => typeof v === "number" && !Number.isNaN(v);
const group = (item) => Math.floor((item?.weather?.[0]?.id ?? 0) / 100);
const isRainCode = (item) => [2, 3, 5].includes(group(item));
const isPrecipCode = (item) => [2, 3, 5, 6].includes(group(item));
const precipAmount = (item) =>
  (item?.rain?.["1h"] ?? item?.rain?.["3h"] ?? 0) + (item?.snow?.["1h"] ?? item?.snow?.["3h"] ?? 0);

// Godzina lokalna miasta (przesunięcie strefy w sekundach z API)
const localHour = (dt, tz) => new Date((dt + tz) * 1000).getUTCHours();

/**
 * @param {object} current  odpowiedź /api/weather/{id} (lub null)
 * @param {object} forecast odpowiedź /api/weather/forecast/{id} (lub null)
 * @param {object} opts     { formatTemp, formatWind, uvi, now }
 * @returns {{id: string, icon: string, text: string, severity: string}[]}
 */
export function buildSuggestions(current, forecast, opts = {}) {
  const T = THRESHOLDS;
  const fTemp = opts.formatTemp || defaultTemp;
  const fWind = opts.formatWind || defaultWind;
  const list = [];
  const add = (id, icon, text, severity) => list.push({ id, icon, text, severity });

  if (!current?.main) return list;

  const now = opts.now ?? current.dt ?? Math.floor(Date.now() / 1000);
  const tz = forecast?.city?.timezone ?? current.timezone ?? 0;
  const entries = (forecast?.list || []).filter((e) => isNum(e?.dt) && e.dt + 3 * 3600 > now);
  const within = (hours) => entries.filter((e) => e.dt <= now + hours * 3600);

  const feels = current.main.feels_like ?? current.main.temp;
  const wind = current.wind?.speed ?? 0;
  const gust = current.wind?.gust ?? 0;
  const rainingNow = isRainCode(current);
  const snowingNow = group(current) === 6;
  const next6 = within(T.umbrellaHours);
  const next12 = within(T.stormHours);
  const next24 = within(24);
  const maxPop6 = Math.max(0, ...next6.map((e) => e.pop ?? 0));
  const isDay = current.sys?.sunrise && current.sys?.sunset
    ? now >= current.sys.sunrise && now < current.sys.sunset
    : true;

  // --- burza / śnieg ---
  if (group(current) === 2 || next12.some((e) => group(e) === 2)) {
    add("storm", "⛈️", group(current) === 2
      ? "Burza w okolicy — unikaj otwartych przestrzeni i wysokich drzew"
      : "Możliwe burze w ciągu 12 h — śledź komunikaty", "danger");
  }
  if (snowingNow || next12.some((e) => group(e) === 6)) {
    add("snow", "🌨️", snowingNow
      ? "Pada śnieg — zaplanuj więcej czasu na dojazd"
      : "W ciągu 12 h możliwe opady śniegu", "warning");
  }

  // --- parasol ---
  if (rainingNow) {
    add("umbrella", "☂️", "Pada deszcz — weź parasol", "warning");
  } else if (maxPop6 >= T.umbrellaPop) {
    add("umbrella", "☂️",
      `Weź parasol — w ciągu 6 h prawdopodobieństwo opadów ${Math.round(maxPop6 * 100)}%`,
      maxPop6 >= T.umbrellaPopHigh ? "warning" : "info");
  }

  // --- oblodzenie / przymrozek ---
  const recentPrecip = rainingNow || snowingNow || precipAmount(current) > 0;
  const morning = next24.filter((e) => {
    const h = localHour(e.dt, tz);
    return h >= T.icingMorningFrom && h <= T.icingMorningTo;
  });
  const icy = morning.filter((e) =>
    isNum(e.main?.temp) && e.main.temp <= T.icingTemp &&
    ((e.main.humidity ?? 0) >= T.icingHumidity || (e.pop ?? 0) >= T.icingPop || recentPrecip || isPrecipCode(e))
  );
  const minTemp24 = Math.min(current.main.temp, ...next24.map((e) => e.main?.temp ?? Infinity));
  if (icy.length > 0) {
    const coldest = Math.min(...icy.map((e) => e.main.temp));
    add("icing", "🧊", `Ryzyko oblodzenia rano (do ${fTemp(coldest)}) — uważaj na drogach i chodnikach`, "warning");
  } else if (minTemp24 <= T.frostTemp) {
    add("frost", "❄️", `Przymrozek — temperatura spadnie do ${fTemp(minTemp24)}`, "info");
  }

  // --- wiatr ---
  const maxWind = Math.max(wind, ...next12.map((e) => e.wind?.speed ?? 0));
  const maxGust = Math.max(gust, ...next12.map((e) => e.wind?.gust ?? 0));
  if (maxWind >= T.windSpeed || maxGust >= T.windGust) {
    const value = maxGust > maxWind ? `porywy do ${fWind(maxGust)}` : `do ${fWind(maxWind)}`;
    add("wind", "💨", `Silny wiatr (${value}) — uważaj`, maxGust >= T.windGustDanger ? "danger" : "warning");
  }

  // --- temperatura odczuwalna ---
  if (feels >= T.heatDanger) {
    add("heat", "🥵", `Upał (odczuwalna ${fTemp(feels)}) — pij dużo wody i unikaj słońca w południe`, "danger");
  } else if (feels >= T.heatWarning) {
    add("heat", "☀️", `Gorąco (odczuwalna ${fTemp(feels)}) — pamiętaj o nawodnieniu`, "warning");
  } else if (feels <= T.coldFeels) {
    add("cold", "🥶", `Silny mróz (odczuwalna ${fTemp(feels)}) — ogranicz czas na zewnątrz`, "warning");
  }

  // --- mgła ---
  if (isNum(current.visibility) && current.visibility < T.fogVisibility) {
    add("fog", "🌫️", "Słaba widoczność — zachowaj ostrożność na drodze", "warning");
  }

  // --- UV (tylko gdy są dane) ---
  if (isNum(opts.uvi)) {
    const uv = Math.round(opts.uvi * 10) / 10;
    if (opts.uvi >= T.uvVeryHigh) add("uv", "🕶️", `Bardzo wysokie UV (${uv}) — krem z filtrem, nakrycie głowy, unikaj słońca`, "danger");
    else if (opts.uvi >= T.uvHigh) add("uv", "🧴", `Wysokie UV (${uv}) — użyj kremu z filtrem`, "warning");
    else if (opts.uvi >= T.uvModerate) add("uv", "🧢", `Umiarkowane UV (${uv}) — w południe przyda się czapka`, "info");
  }

  // --- różnica dzień/noc ---
  if (next24.length > 0) {
    const temps = [current.main.temp, ...next24.map((e) => e.main?.temp).filter(isNum)];
    const max = Math.max(...temps);
    const min = Math.min(...temps);
    if (max - min >= T.tempRange) {
      add("range", "🌡️", `Duża różnica temperatur dzień/noc (od ${fTemp(min)} do ${fTemp(max)}) — ubierz się na cebulkę`, "info");
    }
  }

  // --- ubiór ---
  add("wear", "🧥", `Co na siebie: ${clothing(feels)} (odczuwalna ${fTemp(feels)})`, "info");

  // --- aktywności ---
  const dry = !rainingNow && !snowingNow && maxPop6 < T.dryPop;
  const goodVisibility = !isNum(current.visibility) || current.visibility >= T.fogVisibility;
  if (dry && goodVisibility && maxWind < T.bikeWind && feels >= T.bikeFeelsMin && feels <= T.bikeFeelsMax) {
    add("bike", "🚲", "Dobry dzień na rower", "good");
  }
  if (dry && isDay && maxWind < T.walkWind && feels >= T.walkFeelsMin && feels <= T.walkFeelsMax) {
    add("walk", "🚶", "Idealne warunki na spacer", "good");
  }

  return list.sort((a, b) => SEVERITY_ORDER[a.severity] - SEVERITY_ORDER[b.severity]);
}

// Warstwy ubrania wg temperatury odczuwalnej (°C)
export function clothing(feels) {
  if (feels <= -5) return "zimowa kurtka, czapka, szalik i rękawiczki";
  if (feels <= 5) return "ciepła kurtka, czapka i rękawiczki";
  if (feels <= 12) return "kurtka lub płaszcz i sweter";
  if (feels <= 18) return "lekka kurtka lub bluza";
  if (feels <= 24) return "koszulka i coś z długim rękawem na wieczór";
  return "lekkie, przewiewne ubranie";
}

export default buildSuggestions;
