<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useWeatherStore } from "@/stores/weather";
import WeatherDetails from '@/components/WeatherDetails.vue';

const route = useRoute();
const router = useRouter();
const weatherStore = useWeatherStore();
const isLoading = ref(true);
const error = ref(null);
const cityName = ref('');

onMounted(async () => {
  try {
    isLoading.value = true;
    error.value = null;
    
    const cityIdOrName = route.params.id;
    
    if (!cityIdOrName) {
      error.value = "Brakujący identyfikator miasta";
      isLoading.value = false;
      return;
    }
    
    if (typeof cityIdOrName === 'string' && cityIdOrName.includes(',')) {
      const [cityName, countryCode] = cityIdOrName.split(',');
      await weatherStore.getWeatherForCityByName(cityName, countryCode);
    } else if (!isNaN(cityIdOrName)) {
      await weatherStore.getWeatherForCity(Number(cityIdOrName));
    } else {
      await weatherStore.getWeatherForCityByName(cityIdOrName);
    }
    
    if (weatherStore.currentWeather) {
      cityName.value = weatherStore.currentWeather.name;
    }
    
    isLoading.value = false;
  } catch (err) {
    console.error("Błąd podczas pobierania danych pogodowych:", err);
    error.value = `Nie udało się pobrać danych pogodowych dla ${cityName.value || 'wybranego miasta'}`;
    isLoading.value = false;
  }
});

const handleBack = () => {
  router.push('/');
};
</script>

<template>
  <div>
    <div v-if="isLoading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Ładowanie danych pogodowych...</p>
    </div>
    
    <div v-else-if="error" class="error-container">
      <p>{{ error }}</p>
      <button @click="handleBack" class="back-button">
        Wróć do strony głównej
      </button>
    </div>
    
    <div v-else-if="weatherStore.currentWeather && weatherStore.forecast" class="weather-details-container">
      <WeatherDetails 
        :cityId="route.params.id" 
        :currentWeather="weatherStore.currentWeather"
        :forecast="weatherStore.forecast"
      />
    </div>
    
    <div v-else class="empty-container">
      <p>Brak danych pogodowych</p>
      <button @click="handleBack" class="back-button">
        Wróć do strony głównej
      </button>
    </div>
  </div>
</template>

<style scoped>
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

.back-button {
  padding: 0.5rem 1rem;
  background-color: #3b82f6;
  color: white;
  border: none;
  border-radius: 0.25rem;
  cursor: pointer;
  margin-top: 1rem;
  font-weight: 500;
}

.back-button:hover {
  background-color: #2563eb;
}

.weather-details-container {
  width: 100%;
}
</style>