import { describe, it, expect } from "vitest";
import { buildSuggestions, clothing } from "./suggestions";

// 2024-10-31 12:00 UTC, strefa +1 h
const NOW = 1730376000;
const TZ = 3600;

function current(overrides = {}) {
  return {
    dt: NOW,
    timezone: TZ,
    weather: [{ id: 800, main: "Clear" }],
    main: { temp: 20, feels_like: 20, humidity: 50 },
    wind: { speed: 3 },
    visibility: 10000,
    sys: { sunrise: NOW - 5 * 3600, sunset: NOW + 5 * 3600 },
    ...overrides,
  };
}

// Prognoza co 3 h od "teraz"; fn(i) zwraca nadpisania wpisu
function forecast(fn = () => ({}), count = 8) {
  return {
    city: { timezone: TZ },
    list: Array.from({ length: count }, (_, i) => ({
      dt: NOW + (i + 1) * 3 * 3600,
      main: { temp: 18, humidity: 50 },
      weather: [{ id: 800 }],
      wind: { speed: 3, gust: 4 },
      pop: 0,
      ...fn(i),
    })),
  };
}

const ids = (list) => list.map((s) => s.id);

describe("buildSuggestions", () => {
  it("returns nothing without current weather", () => {
    expect(buildSuggestions(null, null)).toEqual([]);
  });

  it("suggests an umbrella with the max precipitation probability in 6 h", () => {
    const list = buildSuggestions(current(), forecast((i) => ({ pop: i === 1 ? 0.7 : 0.1 })));
    const umbrella = list.find((s) => s.id === "umbrella");
    expect(umbrella.text).toBe("Weź parasol — w ciągu 6 h prawdopodobieństwo opadów 70%");
    expect(umbrella.severity).toBe("warning");
    expect(ids(list)).not.toContain("walk");
  });

  it("ignores rain later than 6 h for the umbrella", () => {
    const list = buildSuggestions(current(), forecast((i) => ({ pop: i >= 3 ? 0.9 : 0 })));
    expect(ids(list)).not.toContain("umbrella");
  });

  it("warns about morning icing", () => {
    // wpisy 18:00 ... 09:00 czasu lokalnego; rano 1°C przy wilgotności 90%
    const fc = forecast((i) => ({ main: { temp: i >= 4 ? 0.5 : 5, humidity: 90 } }));
    const list = buildSuggestions(current({ main: { temp: 6, feels_like: 4, humidity: 80 } }), fc);
    expect(list.find((s) => s.id === "icing").text).toContain("Ryzyko oblodzenia rano");
    expect(ids(list)).not.toContain("frost");
  });

  it("detects strong wind and uses the wind formatter", () => {
    const fc = forecast((i) => ({ wind: { speed: 9, gust: i === 0 ? 21 : 10 } }));
    const list = buildSuggestions(current(), fc, { formatWind: (ms) => `${ms} m/s` });
    const wind = list.find((s) => s.id === "wind");
    expect(wind.text).toBe("Silny wiatr (porywy do 21 m/s) — uważaj");
    expect(wind.severity).toBe("danger");
    expect(list[0].severity).toBe("danger");
  });

  it("detects a large day/night temperature difference", () => {
    const fc = forecast((i) => ({ main: { temp: i === 5 ? 6 : 15, humidity: 40 } }));
    const list = buildSuggestions(current({ main: { temp: 20, feels_like: 20 } }), fc);
    expect(list.find((s) => s.id === "range").text).toContain("od 6°C do 20°C");
  });

  it("skips UV without data and reports high UV with data", () => {
    expect(ids(buildSuggestions(current(), forecast()))).not.toContain("uv");
    const list = buildSuggestions(current(), forecast(), { uvi: 6.4 });
    expect(list.find((s) => s.id === "uv").text).toBe("Wysokie UV (6.4) — użyj kremu z filtrem");
  });

  it("suggests bike and walk in nice weather", () => {
    const list = buildSuggestions(current(), forecast());
    expect(ids(list)).toEqual(expect.arrayContaining(["bike", "walk", "wear"]));
    expect(list.at(-1).severity).toBe("good");
  });

  it("uses the temperature formatter in texts", () => {
    const list = buildSuggestions(current({ main: { temp: 31, feels_like: 33 } }), forecast(), {
      formatTemp: (c) => `${Math.round((c * 9) / 5 + 32)}°F`,
    });
    expect(list.find((s) => s.id === "heat").text).toContain("odczuwalna 91°F");
  });
});

describe("clothing", () => {
  it("maps feels-like temperature to layers", () => {
    expect(clothing(-10)).toContain("zimowa kurtka");
    expect(clothing(3)).toContain("ciepła kurtka");
    expect(clothing(16)).toContain("lekka kurtka");
    expect(clothing(30)).toContain("przewiewne");
  });
});
