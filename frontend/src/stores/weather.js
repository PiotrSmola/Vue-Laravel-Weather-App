import { defineStore } from "pinia";
import { api } from "@/api/client";
import { useAuthStore } from "./auth";
import { useSettingsStore } from "./settings";

// Parametry wspólne dla wszystkich zapytań /api/weather/... (język opisów pogody)
export function weatherParams(extra = {}) {
  return { lang: useSettingsStore().lang, ...extra };
}

export const useWeatherStore = defineStore("weatherStore", {
  state: () => {
    return {
      cities: [],
      defaultCities: [
        { id: 1, name: "Warszawa", openweather_id: 756135, country: "PL" },
        { id: 2, name: "Kraków", openweather_id: 3094802, country: "PL" },
        { id: 3, name: "Wrocław", openweather_id: 3081368, country: "PL" },
        { id: 4, name: "Łódź", openweather_id: 3093133, country: "PL" },
        { id: 5, name: "Poznań", openweather_id: 3088171, country: "PL" },
        { id: 6, name: "Gdańsk", openweather_id: 3099434, country: "PL" },
        { id: 7, name: "Rzeszów", openweather_id: 759734, country: "PL" },
        { id: 8, name: "Sosnowiec", openweather_id: 3085128, country: "PL" },
        { id: 9, name: "Lublin", openweather_id: 765876, country: "PL" },
        { id: 10, name: "Katowice", openweather_id: 3096472, country: "PL" },
      ],
      userFavorites: [],
      currentWeather: null,
      forecast: null,
      historicalData: null,
      loading: false,
      error: null,
    };
  },
  getters: {
    // Miasto domowe z ustawień – home_city_id to ID miasta w OpenWeather (openweather_id)
    homeCity(state) {
      const homeId = useSettingsStore().home_city_id;
      if (!homeId || !useAuthStore().user) return null;
      return (
        state.userFavorites.find((c) => c.openweather_id === homeId) || null
      );
    },
  },
  actions: {
    // Ustawia miasto domowe na początku listy miast (przyciski wyboru miasta)
    withHomeCityFirst(list) {
      const home = this.homeCity;
      if (!home) return list;
      const rest = list.filter((c) => c.openweather_id !== home.openweather_id);
      return [home, ...rest];
    },

    // Przelicza kolejność listy miast po zmianie miasta domowego (lub wylogowaniu)
    applyHomeCity() {
      const order = this.defaultCities.map((c) => c.openweather_id);
      const defaults = this.cities
        .filter((c) => order.includes(c.openweather_id))
        .sort(
          (a, b) =>
            order.indexOf(a.openweather_id) - order.indexOf(b.openweather_id)
        );
      this.cities = this.withHomeCityFirst(defaults);
    },

    async getFeaturedCities() {
      this.loading = true;
      this.error = null;

      try {
        const cities = await api.get("/cities");

        const defaultCityIds = this.defaultCities.map(
          (city) => city.openweather_id
        );

        // Filtrowanie miast zwróconych z API - zostawienie tylko domyślnych miast
        const filteredCities = cities.filter((city) => {
          const isDefault = defaultCityIds.includes(city.openweather_id);
          return isDefault;
        });

        const missingDefaultCities = this.defaultCities.filter(
          (defaultCity) =>
            !filteredCities.some(
              (city) => city.openweather_id === defaultCity.openweather_id
            )
        );

        this.cities = this.withHomeCityFirst([
          ...filteredCities,
          ...missingDefaultCities,
        ]);
      } catch (error) {
        console.error("Błąd:", error);
        this.error = "Nie udało się pobrać listy miast";

        this.cities = this.withHomeCityFirst([...this.defaultCities]);
      } finally {
        this.loading = false;
      }
    },

    async getWeatherForCity(cityId) {
      this.loading = true;
      this.error = null;

      try {
        if (this.cities.length === 0) {
          this.cities = [...this.defaultCities];
        }

        let foundCity = this.cities.find(
          (city) => city.openweather_id === cityId
        );
        if (!foundCity && this.userFavorites.length > 0) {
          foundCity = this.userFavorites.find(
            (city) => city.openweather_id === cityId
          );
        }

        if (!foundCity) {
          foundCity = this.defaultCities.find(
            (city) => city.openweather_id === cityId
          );
        }

        if (!foundCity) {
          console.warn(
            `Nie znaleziono miasta o ID ${cityId}, używam domyślnego`
          );
          foundCity = this.defaultCities[0];
          cityId = foundCity.openweather_id;
        }

        const params = weatherParams();
        const [weatherData, forecastData] = await Promise.all([
          api.get(`/weather/${cityId}`, { params }).catch((error) => {
            console.error(
              `Nie udało się pobrać aktualnej pogody. Status: ${error.status}`,
              error.data
            );
            throw new Error("Nie udało się pobrać aktualnej pogody");
          }),
          api.get(`/weather/forecast/${cityId}`, { params }).catch((error) => {
            console.error(
              `Nie udało się pobrać prognozy. Status: ${error.status}`,
              error.data
            );
            throw new Error("Nie udało się pobrać prognozy pogody");
          }),
        ]);

        this.currentWeather = weatherData;
        this.forecast = forecastData;
      } catch (error) {
        console.error("Błąd:", error);
        this.error = "Nie udało się pobrać danych pogodowych";

        throw error;
      } finally {
        this.loading = false;
      }
    },

    async getWeatherForCityByName(cityName, countryCode = "") {
      this.loading = true;
      this.error = null;

      try {
        let query = cityName;
        if (countryCode) {
          query += `,${countryCode}`;
        }

        const searchResults = await this.searchCities(query);

        if (!searchResults || searchResults.length === 0) {
          throw new Error(`Nie znaleziono miasta: ${query}`);
        }

        const exactMatch = searchResults.find(
          (city) =>
            city.name.toLowerCase() === cityName.toLowerCase() &&
            (!countryCode || city.country === countryCode)
        );

        const bestMatch = exactMatch || searchResults[0];

        if (bestMatch.id) {
          await this.getWeatherForCity(bestMatch.id);
          return;
        }

        throw new Error(`Brak ID dla miasta: ${query}`);
      } catch (error) {
        console.error("Błąd:", error);
        this.error = "Nie udało się pobrać danych pogodowych";
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async getUserFavorites() {
      if (!useAuthStore().token) {
        this.userFavorites = [];
        return [];
      }

      try {
        // Wygasły token (401) obsługuje globalny handler klienta API
        const favorites = await api.get("/cities/user");
        this.userFavorites = Array.isArray(favorites) ? favorites : [];
        return this.userFavorites;
      } catch (error) {
        console.error("Błąd:", error);
        this.error = "Nie udało się pobrać ulubionych miast";
        this.userFavorites = [];
        return [];
      }
    },

    async addCity(cityId) {
      if (!useAuthStore().token) {
        throw new Error("Wymagane zalogowanie");
      }

      try {
        await api.post("/cities", { city_id: cityId });

        await this.getUserFavorites();
        await this.getFeaturedCities();

        return true;
      } catch (error) {
        console.error("Błąd:", error);
        this.error = error.message || "Nie udało się dodać miasta";
        return false;
      }
    },

    async removeCity(cityId) {
      if (!useAuthStore().token) {
        throw new Error("Wymagane zalogowanie");
      }

      try {
        await api.delete(`/cities/${cityId}`);

        // Po usunięciu miasta z ulubionych aktualizowanie list
        await this.getUserFavorites();

        const isDefaultCity = this.defaultCities.some(
          (city) => city.openweather_id === cityId
        );

        if (!isDefaultCity) {
          this.cities = this.cities.filter(
            (city) => city.openweather_id !== cityId
          );
        } else {
          await this.getFeaturedCities();
        }

        if (
          this.currentWeather &&
          this.currentWeather.id === cityId &&
          !isDefaultCity
        ) {
          if (this.cities.length > 0) {
            await this.getWeatherForCity(this.cities[0].openweather_id);
          }
        }

        return true;
      } catch (error) {
        console.error("Błąd:", error);
        this.error = error.message || "Nie udało się usunąć miasta";
        return false;
      }
    },

    async searchCities(query) {
      if (!query || query.length < 3) {
        return [];
      }

      const localFallback = () =>
        this.defaultCities.filter((city) =>
          city.name.toLowerCase().includes(query.toLowerCase())
        );

      try {
        let results;
        try {
          results = await api.get("/cities/search", {
            params: { q: query },
            timeout: 2000, // 2 sekundowy timeout
          });
        } catch (error) {
          if (error.name !== "ApiError") throw error;

          // Błąd odpowiedzi serwera – podpowiedzi z listy domyślnych miast
          if (query.toLowerCase().includes("warszawa")) {
            return [this.defaultCities[0]];
          }
          if (query.toLowerCase().includes("kraków")) {
            return [this.defaultCities[1]];
          }
          return localFallback();
        }

        if (!Array.isArray(results)) {
          console.warn("Niepoprawny format danych z wyszukiwania:", results);
          return [];
        }

        return results;
      } catch (error) {
        if (error.name === "AbortError") {
          console.error("Timeout podczas wyszukiwania miast");
          this.error = "Przekroczono czas oczekiwania na odpowiedź";
        } else {
          console.error("Błąd wyszukiwania:", error);
          this.error = "Nie udało się wyszukać miast";
        }

        return localFallback();
      }
    },

    async getHistoricalData(cityId) {
      if (!useAuthStore().token) {
        throw new Error("Wymagane zalogowanie");
      }

      this.loading = true;
      this.error = null;

      try {
        const result = await api.get(`/cities/${cityId}/historical`);
        this.historicalData = result;
        return result;
      } catch (error) {
        console.error("Błąd:", error);
        this.error =
          error.status === 401
            ? "Sesja wygasła, zaloguj się ponownie"
            : error.message || "Nie udało się pobrać danych historycznych";
        this.historicalData = null;
        return null;
      } finally {
        this.loading = false;
      }
    },

    generateCityId(lat, lon) {
      const latStr = lat.toFixed(2);
      const lonStr = lon.toFixed(2);
      return parseInt(latStr.replace(".", "") + lonStr.replace(".", ""), 10);
    },
  },
});
