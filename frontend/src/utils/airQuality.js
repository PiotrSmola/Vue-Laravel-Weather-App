// Jakość powietrza – poziomy, kolory i zalecenia zdrowotne.
// Indeks OpenWeather (aqi 1–5) zwracany przez /api/air/... oraz dodatkowe indeksy
// europejski (EEA, 1–6) i polski (GIOŚ, 1–6) liczone na backendzie.

export const AQI_LEVELS = {
  1: {
    label: "Dobra",
    color: "#22c55e",
    text: "#ffffff",
    advice: "Jakość powietrza jest dobra. To świetny moment na aktywność na zewnątrz i wietrzenie mieszkania.",
    sensitive: "Brak zaleceń – również dla osób wrażliwych.",
  },
  2: {
    label: "Dostateczna",
    color: "#a3d633",
    text: "#1f2937",
    advice: "Jakość powietrza jest akceptowalna. Można bez obaw przebywać na zewnątrz.",
    sensitive: "Osoby bardzo wrażliwe na zanieczyszczenia mogą odczuwać lekki dyskomfort podczas długiego wysiłku.",
  },
  3: {
    label: "Średnia",
    color: "#facc15",
    text: "#1f2937",
    advice: "Jakość powietrza jest średnia. Ogranicz długotrwały, intensywny wysiłek na zewnątrz.",
    sensitive:
      "Dzieci, seniorzy, kobiety w ciąży oraz osoby z astmą lub chorobami serca powinny ograniczyć dłuższy pobyt na zewnątrz.",
  },
  4: {
    label: "Zła",
    color: "#f97316",
    text: "#ffffff",
    advice: "Jakość powietrza jest zła. Unikaj wysiłku na zewnątrz i ogranicz wietrzenie mieszkania.",
    sensitive:
      "Osoby wrażliwe powinny pozostać w pomieszczeniach. Rozważ użycie oczyszczacza powietrza i maski z filtrem przy wyjściu.",
  },
  5: {
    label: "Bardzo zła",
    color: "#dc2626",
    text: "#ffffff",
    advice: "Jakość powietrza jest bardzo zła. Pozostań w domu, zamknij okna i zrezygnuj z aktywności na zewnątrz.",
    sensitive:
      "Osoby wrażliwe nie powinny wychodzić na zewnątrz. W razie duszności lub bólu w klatce piersiowej skontaktuj się z lekarzem.",
  },
};

const UNKNOWN_LEVEL = {
  label: "Brak danych",
  color: "#9ca3af",
  text: "#ffffff",
  advice: "Brak aktualnych danych o jakości powietrza.",
  sensitive: "",
};

export function aqiLevel(aqi) {
  return AQI_LEVELS[aqi] || UNKNOWN_LEVEL;
}

// Kolory skal: europejskiej (EEA) i polskiej (GIOŚ) – poziomy 1–6
export const EUROPEAN_COLORS = ["#50f0e6", "#50ccaa", "#f0e641", "#ff5050", "#960032", "#7d2181"];
export const POLISH_COLORS = ["#57b108", "#b0dd10", "#ffd911", "#e58100", "#e50000", "#990000"];

export function scaleColor(colors, level) {
  return colors[(level || 1) - 1] || UNKNOWN_LEVEL.color;
}

// Nazwy i opisy składników (stężenia w µg/m³)
export const POLLUTANTS = {
  pm2_5: { name: "PM2.5", description: "Pył zawieszony drobny" },
  pm10: { name: "PM10", description: "Pył zawieszony" },
  no2: { name: "NO₂", description: "Dwutlenek azotu" },
  o3: { name: "O₃", description: "Ozon" },
  so2: { name: "SO₂", description: "Dwutlenek siarki" },
  co: { name: "CO", description: "Tlenek węgla" },
  no: { name: "NO", description: "Tlenek azotu" },
  nh3: { name: "NH₃", description: "Amoniak" },
};

export const UNIT = "µg/m³";

export function pollutantName(key) {
  return POLLUTANTS[key]?.name || key;
}

export function formatConcentration(value) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return "–";
  const num = Number(value);
  return num >= 100 ? Math.round(num).toLocaleString("pl-PL") : num.toLocaleString("pl-PL", { maximumFractionDigits: 1 });
}
