// Funkcje opcjonalne backendu (GET /api/features) i One Call 3.0 (GET /api/weather/onecall/{id}).
// Wyniki są zapamiętywane w pamięci, więc kilka komponentów może prosić o te same dane
// bez dodatkowych zapytań. Brak funkcji / błąd = null (komponenty nic wtedy nie pokazują).

import { api } from "@/api/client";

const ONECALL_TTL = 10 * 60 * 1000;

let featuresPromise = null;
const oneCallCache = new Map();

export function fetchFeatures() {
  if (!featuresPromise) {
    featuresPromise = api
      .get("/features", { auth: false })
      .then((data) => ({ onecall: !!data?.onecall }))
      .catch(() => ({ onecall: false }));
  }
  return featuresPromise;
}

export async function fetchOneCall(cityId, lang = "pl") {
  const id = Number(cityId);
  if (!(id > 0)) return null;

  const features = await fetchFeatures();
  if (!features.onecall) return null;

  const key = `${id}:${lang}`;
  const cached = oneCallCache.get(key);
  if (cached && Date.now() - cached.at < ONECALL_TTL) return cached.promise;

  const promise = api
    .get(`/weather/onecall/${id}`, { params: { lang }, auth: false })
    .catch((error) => {
      // 404 = funkcja niedostępna (np. brak subskrypcji) – nie pytamy ponownie w tej sesji
      if (error.status === 404) featuresPromise = Promise.resolve({ onecall: false });
      return null;
    });

  oneCallCache.set(key, { at: Date.now(), promise });
  return promise;
}
