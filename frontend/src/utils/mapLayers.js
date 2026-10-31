// Warstwy pogodowe OpenWeather dostępne przez proxy /api/map/tiles/{layer}/{z}/{x}/{y}.png
// oraz ich legendy (wartości metryczne – formatowane w komponencie wg ustawień jednostek).
// Kolory odpowiadają paletom map OpenWeather (Weather Maps 1.0).

export const OVERLAY_MAX_ZOOM = 10; // backend przyjmuje z = 0–10, powyżej kafelki są skalowane

export const MAP_LAYERS = [
  {
    id: "temp_new",
    label: "Temperatura",
    kind: "temp",
    stops: [
      [-40, "rgb(130,22,146)"],
      [-30, "rgb(130,87,219)"],
      [-20, "rgb(32,140,236)"],
      [-10, "rgb(32,196,232)"],
      [0, "rgb(35,221,221)"],
      [10, "rgb(194,255,40)"],
      [20, "rgb(255,240,40)"],
      [25, "rgb(255,194,40)"],
      [30, "rgb(252,128,20)"],
    ],
  },
  {
    id: "precipitation_new",
    label: "Opady",
    kind: "precip",
    stops: [
      [0, "rgba(225,200,100,0)"],
      [0.5, "rgb(120,120,190)"],
      [1, "rgb(110,110,205)"],
      [10, "rgb(80,80,225)"],
      [140, "rgb(20,20,255)"],
    ],
  },
  {
    id: "clouds_new",
    label: "Zachmurzenie",
    kind: "percent",
    stops: [
      [0, "rgba(255,255,255,0)"],
      [50, "rgba(247,247,255,0.5)"],
      [100, "rgba(240,240,255,1)"],
    ],
  },
  {
    id: "wind_new",
    label: "Wiatr",
    kind: "wind",
    stops: [
      [1, "rgba(255,255,255,0)"],
      [5, "rgba(238,206,206,0.4)"],
      [15, "rgba(179,100,188,0.7)"],
      [25, "rgba(63,33,59,0.8)"],
      [50, "rgba(116,76,172,0.9)"],
    ],
  },
  {
    id: "pressure_new",
    label: "Ciśnienie",
    kind: "pressure",
    stops: [
      [950, "rgb(0,115,255)"],
      [980, "rgb(75,208,214)"],
      [1000, "rgb(141,231,199)"],
      [1010, "rgb(176,247,32)"],
      [1020, "rgb(240,184,0)"],
      [1040, "rgb(251,85,21)"],
      [1060, "rgb(243,54,59)"],
    ],
  },
];

// Gradient CSS z równymi odstępami między progami
export function legendGradient(layer) {
  const n = layer.stops.length - 1;
  const parts = layer.stops.map(([, color], i) => `${color} ${Math.round((i / n) * 100)}%`);
  return `linear-gradient(to right, ${parts.join(", ")})`;
}
