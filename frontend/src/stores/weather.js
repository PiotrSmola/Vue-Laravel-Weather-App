import { defineStore } from "pinia";
import { useAuthStore } from "./auth";

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
  actions: {
    async getFeaturedCities() {
      this.loading = true;
      this.error = null;

      try {
        const response = await fetch("/api/cities");

        if (!response.ok) {
          console.error(
            "Błąd pobierania miast:",
            response.status,
            response.statusText
          );
          throw new Error("Nie udało się pobrać listy miast");
        }

        const cities = await response.json();

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

        this.cities = [...filteredCities, ...missingDefaultCities];
      } catch (error) {
        console.error("Błąd:", error);
        this.error = "Nie udało się pobrać listy miast";

        this.cities = [...this.defaultCities];
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

        const [weatherResponse, forecastResponse] = await Promise.all([
          fetch(`/api/weather/${cityId}`),
          fetch(`/api/weather/forecast/${cityId}`),
        ]);

        // Sprawdzanie odpowiedzi API
        if (!weatherResponse.ok) {
          console.error(
            `Nie udało się pobrać aktualnej pogody. Status: ${weatherResponse.status}`
          );
          const errorText = await weatherResponse.text();
          console.error(`Treść błędu: ${errorText}`);
          throw new Error("Nie udało się pobrać aktualnej pogody");
        }

        if (!forecastResponse.ok) {
          console.error(
            `Nie udało się pobrać prognozy. Status: ${forecastResponse.status}`
          );
          const errorText = await forecastResponse.text();
          console.error(`Treść błędu: ${errorText}`);
          throw new Error("Nie udało się pobrać prognozy pogody");
        }

        const [weatherData, forecastData] = await Promise.all([
          weatherResponse.json(),
          forecastResponse.json(),
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

        console.log(`Pobieranie pogody dla miasta: ${query}`);

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
      if (!localStorage.getItem("token")) {
        this.userFavorites = [];
        return [];
      }

      try {
        const response = await fetch("/api/cities/user", {
          headers: {
            Authorization: `Bearer ${localStorage.getItem("token")}`,
          },
        });

        if (!response.ok) {
          // Jeśli token wygasł, usuń go
          if (response.status === 401) {
            const authStore = useAuthStore();
            authStore.clearAuth();
          }
          throw new Error("Nie udało się pobrać ulubionych miast");
        }

        const favorites = await response.json();
        this.userFavorites = favorites;
        return favorites;
      } catch (error) {
        console.error("Błąd:", error);
        this.error = "Nie udało się pobrać ulubionych miast";
        this.userFavorites = [];
        return [];
      }
    },

    async addCity(cityId) {
      if (!localStorage.getItem("token")) {
        throw new Error("Wymagane zalogowanie");
      }

      try {
        const response = await fetch("/api/cities", {
          method: "POST",
          headers: {
            Authorization: `Bearer ${localStorage.getItem("token")}`,
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ city_id: cityId }),
        });

        if (!response.ok) {
          const errorData = await response.json();
          throw new Error(errorData.error || "Nie udało się dodać miasta");
        }

        await this.getUserFavorites();
        await this.getFeaturedCities();

        return true;
      } catch (error) {
        console.error("Błąd:", error);
        this.error = error.message;
        return false;
      }
    },

    async removeCity(cityId) {
      if (!localStorage.getItem("token")) {
        throw new Error("Wymagane zalogowanie");
      }

      try {
        const response = await fetch(`/api/cities/${cityId}`, {
          method: "DELETE",
          headers: {
            Authorization: `Bearer ${localStorage.getItem("token")}`,
          },
        });

        if (!response.ok) {
          const errorData = await response.json();
          throw new Error(errorData.error || "Nie udało się usunąć miasta");
        }

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
        this.error = error.message;
        return false;
      }
    },

    async searchCities(query) {
      if (!query || query.length < 3) {
        return [];
      }

      try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 2000); // 2 sekundowy timeout

        const response = await fetch(
          `/api/cities/search?q=${encodeURIComponent(query)}`,
          {
            signal: controller.signal,
          }
        );

        clearTimeout(timeoutId);

        if (!response.ok) {
          if (query.toLowerCase().includes("warszawa")) {
            return [this.defaultCities[0]];
          }
          if (query.toLowerCase().includes("kraków")) {
            return [this.defaultCities[1]];
          }

          return this.defaultCities.filter((city) =>
            city.name.toLowerCase().includes(query.toLowerCase())
          );
        }

        const results = await response.json();

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

        return this.defaultCities.filter((city) =>
          city.name.toLowerCase().includes(query.toLowerCase())
        );
      }
    },

    async getHistoricalData(cityId) {
      if (!localStorage.getItem("token")) {
        throw new Error("Wymagane zalogowanie");
      }

      this.loading = true;
      this.error = null;

      try {
        const response = await fetch(`/api/cities/${cityId}/historical`, {
          headers: {
            Authorization: `Bearer ${localStorage.getItem("token")}`,
          },
        });

        if (!response.ok) {
          if (response.status === 401) {
            const authStore = useAuthStore();
            authStore.clearAuth();
            throw new Error("Sesja wygasła, zaloguj się ponownie");
          }

          const errorData = await response.json();
          throw new Error(
            errorData.error || "Nie udało się pobrać danych historycznych"
          );
        }

        const result = await response.json();
        this.historicalData = result;
        return result;
      } catch (error) {
        console.error("Błąd:", error);
        this.error = error.message;
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
