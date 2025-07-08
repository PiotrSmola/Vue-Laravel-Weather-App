<script setup>
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useWeatherStore } from "@/stores/weather";
import WeatherView from "@/components/WeatherView.vue";
import CitySearch from "@/components/CitySearch.vue";
import UserFavorites from "@/components/UserFavorites.vue";

const authStore = useAuthStore();
const weatherStore = useWeatherStore();
const isLoading = ref(true);
const error = ref(null);
const selectedCityData = ref(null);

onMounted(async () => {
  try {
    isLoading.value = true;
    
    await authStore.initialize();
    
    if (weatherStore.cities.length === 0) {
      await weatherStore.getFeaturedCities();
    }
    
    if (authStore.isAuthenticated) {
      await weatherStore.getUserFavorites();
    }
    
    if (weatherStore.cities.length > 0) {
      //try-catch aby błąd pobierania pogody nie zatrzymał całego procesu
      try {
        await weatherStore.getWeatherForCity(weatherStore.cities[0].openweather_id);
      } catch (err) {
        console.error("Nie udało się pobrać pogody dla domyślnego miasta:", err);
      }
    }
    
    error.value = null;
    isLoading.value = false;
  } catch (err) {
    console.error("Błąd podczas ładowania danych pogodowych:", err);
    error.value = "Nie udało się załadować danych pogodowych";
    isLoading.value = false;
  }
});

const handleCitySelected = async (cityData) => {
  try {
    isLoading.value = true;
    selectedCityData.value = cityData;
    
    if (cityData.name && cityData.country) {
      await weatherStore.getWeatherForCityByName(cityData.name, cityData.country);
    } else if (cityData.id) {
      await weatherStore.getWeatherForCity(cityData.id);
    }
    
    isLoading.value = false;
  } catch (err) {
    console.error("Błąd podczas pobierania pogody dla wybranego miasta:", err);
    error.value = "Nie udało się pobrać pogody dla wybranego miasta";
    isLoading.value = false;
  }
};

const handleFavoriteCitySelected = async (city) => {
  try {
    isLoading.value = true;
    selectedCityData.value = city.openweather_id;
    
    await weatherStore.getWeatherForCity(city.openweather_id);
    
    isLoading.value = false;
  } catch (err) {
    console.error("Błąd podczas pobierania pogody dla ulubionego miasta:", err);
    error.value = "Nie udało się pobrać pogody dla wybranego miasta";
    isLoading.value = false;
  }
};

const retryLoading = async () => {
  try {
    isLoading.value = true;
    error.value = null;
    
    await weatherStore.getFeaturedCities();
    
    if (weatherStore.cities.length > 0) {
      await weatherStore.getWeatherForCity(weatherStore.cities[0].openweather_id);
    }
    
    isLoading.value = false;
  } catch (err) {
    console.error("Błąd podczas ponownego ładowania danych:", err);
    error.value = "Nie udało się ponownie załadować danych";
    isLoading.value = false;
  }
};
</script>

<template>
  <main>
    <div class="welcome-section">
      <h2 class="welcome-text">Pogoda w Polsce i na świecie</h2>
      <p class="welcome-subtext">
        Aplikacja do śledzenia warunków pogodowych w różnych miastach, w tym 10 największych miast w Polsce
      </p>
    </div>

    <CitySearch @citySelected="handleCitySelected" />
    
    <UserFavorites @selectCity="handleFavoriteCitySelected" />

    <div v-if="isLoading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Ładowanie danych pogodowych...</p>
    </div>
    
    <div v-else-if="error" class="error-container">
      <p>{{ error }}</p>
      <button @click="retryLoading" class="retry-button">Spróbuj ponownie</button>
    </div>
    
    <div v-else-if="weatherStore.currentWeather" class="weather-container">
      <WeatherView :selectedCityId="selectedCityData" />
    </div>
    
    <div v-else class="empty-container">
      <p>Brak danych pogodowych</p>
      <button @click="retryLoading" class="retry-button">Załaduj dane</button>
    </div>
  </main>
</template>

<style scoped>
.welcome-section {
  max-width: 800px;
  margin: 2rem auto;
  padding: 2rem;
  background-color: #f0f9ff;
  border-radius: 8px;
  text-align: center;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.welcome-text {
  font-size: 2rem;
  color: #2563eb;
  margin-bottom: 0.5rem;
}

.welcome-subtext {
  font-size: 1.2rem;
  color: #4b5563;
  margin-bottom: 1.5rem;
}

.auth-buttons {
  display: flex;
  justify-content: center;
  gap: 1rem;
  margin-top: 1rem;
}

.auth-btn {
  padding: 0.75rem 1.5rem;
  border-radius: 0.25rem;
  font-weight: 500;
  text-decoration: none;
  transition: background-color 0.2s;
}

.login-btn {
  background-color: #2563eb;
  color: white;
}

.login-btn:hover {
  background-color: #1d4ed8;
}

.register-btn {
  background-color: #10b981;
  color: white;
}

.register-btn:hover {
  background-color: #059669;
}

.user-info {
  margin-top: 1rem;
  padding: 0.75rem;
  background-color: #e0f2fe;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.user-info p {
  margin-bottom: 0.75rem;
  color: #1e40af;
}

.logout-btn {
  padding: 0.5rem 1rem;
  background-color: #ef4444;
  color: white;
  border: none;
  border-radius: 0.25rem;
  cursor: pointer;
  font-weight: 500;
  transition: background-color 0.2s;
}

.logout-btn:hover {
  background-color: #dc2626;
}

.weather-container {
  max-width: 800px;
  margin: 2rem auto;
}

.loading-container, .error-container, .empty-container {
  max-width: 800px;
  margin: 2rem auto;
  padding: 2rem;
  background-color: #f3f4f6;
  border-radius: 8px;
  text-align: center;
}

.loading-spinner {
  display: inline-block;
  width: 40px;
  height: 40px;
  border: 4px solid rgba(0, 0, 0, 0.1);
  border-radius: 50%;
  border-top-color: #3b82f6;
  animation: spin 1s ease-in-out infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.error-container {
  border-left: 4px solid #ef4444;
}

.retry-button {
  padding: 0.5rem 1rem;
  background-color: #3b82f6;
  color: white;
  border: none;
  border-radius: 0.25rem;
  cursor: pointer;
  margin-top: 1rem;
  font-weight: 500;
}

.retry-button:hover {
  background-color: #2563eb;
}
</style>