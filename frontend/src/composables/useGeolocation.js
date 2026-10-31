// Lokalizacja użytkownika (navigator.geolocation) z polskimi komunikatami błędów.
//
//   const { locate, locating, geoError } = useGeolocation();
//   const coords = await locate(); // { lat, lon, accuracy } lub null (błąd w geoError)

import { ref } from "vue";

export const GEO_MESSAGES = {
  unsupported: "Twoja przeglądarka nie obsługuje lokalizacji.",
  insecure: "Lokalizacja wymaga bezpiecznego połączenia (HTTPS).",
  denied: "Brak zgody na dostęp do lokalizacji. Zmień uprawnienia w ustawieniach przeglądarki.",
  unavailable: "Nie udało się ustalić Twojej lokalizacji. Spróbuj ponownie za chwilę.",
  timeout: "Przekroczono czas oczekiwania na lokalizację. Spróbuj ponownie.",
};

export function geolocationErrorMessage(error) {
  if (!error) return GEO_MESSAGES.unavailable;
  if (error.code === 1) return GEO_MESSAGES.denied;
  if (error.code === 3) return GEO_MESSAGES.timeout;
  return GEO_MESSAGES.unavailable;
}

export function useGeolocation(options = {}) {
  const locating = ref(false);
  const geoError = ref(null);

  const locate = () =>
    new Promise((resolve) => {
      geoError.value = null;

      if (typeof navigator === "undefined" || !("geolocation" in navigator)) {
        geoError.value = GEO_MESSAGES.unsupported;
        return resolve(null);
      }
      if (typeof window !== "undefined" && window.isSecureContext === false) {
        geoError.value = GEO_MESSAGES.insecure;
        return resolve(null);
      }

      locating.value = true;
      navigator.geolocation.getCurrentPosition(
        (position) => {
          locating.value = false;
          resolve({
            lat: Math.round(position.coords.latitude * 10000) / 10000,
            lon: Math.round(position.coords.longitude * 10000) / 10000,
            accuracy: position.coords.accuracy,
          });
        },
        (error) => {
          locating.value = false;
          geoError.value = geolocationErrorMessage(error);
          resolve(null);
        },
        { enableHighAccuracy: false, timeout: 10000, maximumAge: 5 * 60 * 1000, ...options }
      );
    });

  return { locate, locating, geoError };
}

export default useGeolocation;
