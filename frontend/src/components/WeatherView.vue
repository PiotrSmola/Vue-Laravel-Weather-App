<script setup>
import { ref, computed, watch } from 'vue';
import { useAuthStore } from "@/stores/auth";
import { useWeatherStore } from "@/stores/weather";
import { useRouter } from 'vue-router';

const props = defineProps({
  selectedCityId: {
    type: [Number, String, Object],
    default: null
  }
});

const router = useRouter();
const authStore = useAuthStore();
const weatherStore = useWeatherStore();
const selectedCityIndex = ref(0);
const showConfirmModal = ref(false);
const showRemoveModal = ref(false);
const maxFavoritesReached = ref(false);
const isLoading = ref(false);
const isRemoving = ref(false);

const isCityInFavorites = computed(() => {
  if (!weatherStore.currentWeather || !authStore.user) return false;
  
  const cityId = weatherStore.currentWeather.id;
  return weatherStore.userFavorites.some(city => city.openweather_id === cityId);
});

const isDefaultCity = computed(() => {
  if (!weatherStore.currentWeather) return true;
  
  const cityId = weatherStore.currentWeather.id;
  return weatherStore.defaultCities.some(city => city.openweather_id === cityId);
});

// Sprawdzanie limitu ulubionych miast
const hasReachedFavoritesLimit = computed(() => {
  return weatherStore.userFavorites.length >= 10;
});

const selectCity = async (index) => {
  try {
    isLoading.value = true;
    selectedCityIndex.value = index;
    
    if (weatherStore.cities.length > 0) {
      await weatherStore.getWeatherForCity(weatherStore.cities[index].openweather_id);
    }
    
    isLoading.value = false;
  } catch (err) {
    console.error("Błąd podczas pobierania pogody:", err);
    isLoading.value = false;
  }
};

const getWeatherForSelectedCity = async (cityIdOrData) => {
  try {
    isLoading.value = true;
    
    if (typeof cityIdOrData === 'object' && cityIdOrData.name) {
      await weatherStore.getWeatherForCityByName(cityIdOrData.name, cityIdOrData.country);
    } else if (typeof cityIdOrData === 'string' && cityIdOrData.includes(',')) {
      const [cityName, countryCode] = cityIdOrData.split(',');
      await weatherStore.getWeatherForCityByName(cityName, countryCode);
    } else {
      await weatherStore.getWeatherForCity(cityIdOrData);
    }
    
    isLoading.value = false;
  } catch (err) {
    console.error("Błąd podczas pobierania pogody dla wybranego miasta:", err);
    isLoading.value = false;
  }
};

// Obserwowanie zmiany selectedCityId
watch(() => props.selectedCityId, (newCityId) => {
  if (newCityId) {
    console.log('Nowe miasto wybrane:', newCityId);
    getWeatherForSelectedCity(newCityId);
  } else if (weatherStore.cities.length > 0) {
    selectCity(selectedCityIndex.value);
  }
}, { immediate: true });

const temperature = computed(() => {
  if (!weatherStore.currentWeather) return null;
  return Math.round(weatherStore.currentWeather.main.temp);
});

const cityName = computed(() => {
  if (weatherStore.currentWeather) {
    return weatherStore.currentWeather.name;
  }
  if (weatherStore.cities.length > 0 && selectedCityIndex.value < weatherStore.cities.length) {
    return weatherStore.cities[selectedCityIndex.value].name;
  }
  return "";
});

const getWeatherIconUrl = (iconCode) => {
  return `https://openweathermap.org/img/wn/${iconCode}@2x.png`;
};

const formatDate = (timestamp) => {
  return new Date(timestamp * 1000).toLocaleTimeString('pl-PL', {
    hour: '2-digit',
    minute: '2-digit'
  });
};

const formatDay = (timestamp) => {
  return new Date(timestamp * 1000).toLocaleDateString('pl-PL', {
    weekday: 'long',
    day: 'numeric',
    month: 'long'
  });
};

const viewDetails = () => {
  if (weatherStore.currentWeather) {
    if (props.selectedCityId && typeof props.selectedCityId === 'object' && props.selectedCityId.name) {
      router.push({ 
        name: 'weather-details', 
        params: { 
          id: `${props.selectedCityId.name},${props.selectedCityId.country}`
        } 
      });
    } else {
      let cityId;
      if (weatherStore.currentWeather && weatherStore.currentWeather.id) {
        cityId = weatherStore.currentWeather.id;
      } else if (props.selectedCityId && (typeof props.selectedCityId === 'number' || !isNaN(props.selectedCityId))) {
        cityId = props.selectedCityId;
      } else if (weatherStore.cities.length > 0 && selectedCityIndex.value < weatherStore.cities.length) {
        cityId = weatherStore.cities[selectedCityIndex.value].openweather_id;
      }
      
      if (cityId) {
        router.push({ name: 'weather-details', params: { id: cityId } });
      } else if (weatherStore.currentWeather && weatherStore.currentWeather.name) {
        router.push({ 
          name: 'weather-details', 
          params: { 
            id: weatherStore.currentWeather.name
          } 
        });
      }
    }
  }
};

const toggleFavorite = async () => {
  if (!authStore.user) {
    router.push('/login');
    return;
  }

  if (!weatherStore.currentWeather) return;

  if (isCityInFavorites.value) {
    showRemoveModal.value = true;
  } else {
    if (hasReachedFavoritesLimit.value) {
      maxFavoritesReached.value = true;
      return;
    }
    showConfirmModal.value = true;
  }
};

const confirmAddToFavorites = async () => {
  try {
    await weatherStore.addCity(weatherStore.currentWeather.id);
    await weatherStore.getUserFavorites();
    showConfirmModal.value = false;
  } catch (err) {
    console.error("Błąd podczas dodawania miasta do ulubionych:", err);
  }
};

const cancelAddToFavorites = () => {
  showConfirmModal.value = false;
};

const confirmRemoveFromFavorites = async () => {
  try {
    isRemoving.value = true;
    await weatherStore.removeCity(weatherStore.currentWeather.id);
    await weatherStore.getUserFavorites();
    showRemoveModal.value = false;
  } catch (err) {
    console.error("Błąd podczas usuwania miasta z ulubionych:", err);
  } finally {
    isRemoving.value = false;
  }
};

const cancelRemoveFromFavorites = () => {
  showRemoveModal.value = false;
};

const closeMaxFavoritesModal = () => {
  maxFavoritesReached.value = false;
};
</script>

<template>
  <div class="weather-view">
    <div v-if="isLoading" class="loading-overlay">
      <div class="loading-spinner"></div>
      <p>Ładowanie...</p>
    </div>
    
    <div v-if="!props.selectedCityId && weatherStore.cities.length > 0" class="city-selector">
      <button 
        v-for="(city, index) in weatherStore.cities" 
        :key="city.id" 
        @click="selectCity(index)"
        :class="['city-button', { 'active': index === selectedCityIndex }]"
      >
        {{ city.name }}
      </button>
    </div>
    
    <div v-if="weatherStore.currentWeather" class="current-weather">
      <div class="weather-card">
        <div class="weather-header">
          <h2 class="city-name">{{ cityName }}</h2>
          <p class="date">{{ formatDay(weatherStore.currentWeather.dt) }}</p>
        </div>
        
        <div v-if="authStore.user" class="favorite-action">
          <button 
            @click="toggleFavorite"
            :class="['favorite-button', { 'is-favorite': isCityInFavorites }]"
          >
            <span v-if="isCityInFavorites">❤️ Usuń z ulubionych</span>
            <span v-else>🤍 Dodaj do ulubionych</span>
          </button>
        </div>
        
        <div class="weather-main">
          <div class="weather-icon">
            <img 
              :src="getWeatherIconUrl(weatherStore.currentWeather.weather[0].icon)" 
              :alt="weatherStore.currentWeather.weather[0].description"
            />
          </div>
          
          <div class="weather-info">
            <div class="temperature">{{ temperature }}°C</div>
            <div class="description">{{ weatherStore.currentWeather.weather[0].description }}</div>
          </div>
          
          <div class="weather-details">
            <div class="detail">
              <span class="detail-label">Wilgotność:</span>
              <span class="detail-value">{{ weatherStore.currentWeather.main.humidity }}%</span>
            </div>
            <div class="detail">
              <span class="detail-label">Wiatr:</span>
              <span class="detail-value">{{ Math.round(weatherStore.currentWeather.wind.speed * 3.6) }} km/h</span>
            </div>
            <div class="detail">
              <span class="detail-label">Ciśnienie:</span>
              <span class="detail-value">{{ weatherStore.currentWeather.main.pressure }} hPa</span>
            </div>
          </div>
        </div>
        
        <div class="weather-additional">
          <div class="detail">
            <span class="detail-label">Wschód słońca:</span>
            <span class="detail-value">{{ formatDate(weatherStore.currentWeather.sys.sunrise) }}</span>
          </div>
          <div class="detail">
            <span class="detail-label">Zachód słońca:</span>
            <span class="detail-value">{{ formatDate(weatherStore.currentWeather.sys.sunset) }}</span>
          </div>
          <div class="detail">
            <span class="detail-label">Widoczność:</span>
            <span class="detail-value">{{ Math.round(weatherStore.currentWeather.visibility / 1000) }} km</span>
          </div>
          <div class="detail">
            <span class="detail-label">Chmury:</span>
            <span class="detail-value">{{ weatherStore.currentWeather.clouds.all }}%</span>
          </div>
        </div>
        
        <div class="view-details-container">
          <button @click="viewDetails" class="view-details-button">
            Zobacz szczegółową prognozę
          </button>
        </div>
      </div>
    </div>
    
    <div v-if="weatherStore.forecast" class="forecast">
      <h3 class="forecast-title">Prognoza 5-dniowa</h3>
      <div class="forecast-container">
        <div 
          v-for="(day, index) in weatherStore.forecast.list.filter((_, i) => i % 8 === 0).slice(0, 5)" 
          :key="index" 
          class="forecast-day"
        >
          <div class="forecast-date">{{ formatDay(day.dt).split(',')[0] }}</div>
          <img 
            :src="getWeatherIconUrl(day.weather[0].icon)" 
            :alt="day.weather[0].description" 
            class="forecast-icon"
          />
          <div class="forecast-temp">{{ Math.round(day.main.temp) }}°C</div>
          <div class="forecast-desc">{{ day.weather[0].description }}</div>
        </div>
      </div>
    </div>
    
    <!-- Modal dodawania do ulubionych -->
    <div v-if="showConfirmModal" class="modal-overlay">
      <div class="confirm-modal">
        <h3 class="modal-title">Dodaj do ulubionych</h3>
        <p class="modal-text">Czy chcesz dodać {{ cityName }} do ulubionych miast?</p>
        <p class="modal-info">Dane pogodowe dla ulubionych miast będą zbierane co 30 minut.</p>
        <div class="modal-buttons">
          <button @click="cancelAddToFavorites" class="modal-button cancel-button">Anuluj</button>
          <button @click="confirmAddToFavorites" class="modal-button confirm-button">Dodaj</button>
        </div>
      </div>
    </div>

    <!-- Modal usuwania z ulubionych -->
    <div v-if="showRemoveModal" class="modal-overlay">
      <div class="confirm-modal">
        <h3 class="modal-title">Usuń z ulubionych</h3>
        <p class="modal-text">Czy na pewno chcesz usunąć <strong>{{ cityName }}</strong> z ulubionych miast?</p>
        <p class="modal-info">Po usunięciu miasta przestaną być zbierane dla niego dane archiwalne.</p>
        <div class="modal-buttons">
          <button 
            @click="cancelRemoveFromFavorites" 
            class="modal-button cancel-button"
            :disabled="isRemoving"
          >
            Anuluj
          </button>
          <button 
            @click="confirmRemoveFromFavorites" 
            class="modal-button remove-button"
            :disabled="isRemoving"
          >
            <span v-if="isRemoving" class="button-spinner"></span>
            {{ isRemoving ? 'Usuwanie...' : 'Usuń' }}
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal limitu ulubionych miast -->
    <div v-if="maxFavoritesReached" class="modal-overlay">
      <div class="confirm-modal">
        <h3 class="modal-title">Limit ulubionych miast</h3>
        <p class="modal-text">Osiągnięto limit 10 ulubionych miast.</p>
        <p class="modal-info">Aby dodać nowe miasto, musisz najpierw usunąć jedno z istniejących ulubionych miast.</p>
        <div class="modal-buttons">
          <button @click="closeMaxFavoritesModal" class="modal-button confirm-button">OK</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.weather-view {
  background-color: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
  position: relative;
}

.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(255, 255, 255, 0.8);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  z-index: 10;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid rgba(59, 130, 246, 0.1);
  border-radius: 50%;
  border-left-color: #3b82f6;
  animation: spin 1s linear infinite;
  margin-bottom: 0.5rem;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.city-selector {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  padding: 1rem;
  background-color: #f3f4f6;
  gap: 0.5rem;
}

.city-button {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  background-color: #e5e7eb;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s ease;
}

.city-button:hover {
  background-color: #d1d5db;
}

.city-button.active {
  background-color: #3b82f6;
  color: white;
}

.favorite-action {
  display: flex;
  justify-content: center;
  margin: 0.5rem 0 1.5rem;
}

.favorite-button {
  padding: 0.5rem 1.5rem;
  border: none;
  border-radius: 20px;
  background-color: #f3f4f6;
  color: #4b5563;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.favorite-button:hover {
  background-color: #e5e7eb;
}

.favorite-button.is-favorite {
  background-color: #fee2e2;
  color: #b91c1c;
}

.favorite-button.is-favorite:hover {
  background-color: #fecaca;
}

.current-weather {
  padding: 1.5rem;
}

.weather-card {
  border-radius: 8px;
  overflow: hidden;
  background-color: #f0f9ff;
}

.weather-header {
  background-color: #3b82f6;
  color: white;
  padding: 1rem;
  text-align: center;
}

.city-name {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
}

.date {
  font-size: 0.875rem;
  opacity: 0.9;
  margin: 0.25rem 0 0;
}

.weather-main {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 1.5rem;
}

@media (min-width: 768px) {
  .weather-main {
    flex-direction: row;
    justify-content: space-between;
  }
}

.weather-icon img {
  width: 100px;
  height: 100px;
}

.weather-info {
  text-align: center;
  margin: 1rem 0;
}

@media (min-width: 768px) {
  .weather-info {
    margin: 0;
  }
}

.temperature {
  font-size: 3rem;
  font-weight: 700;
  color: #1f2937;
}

.description {
  font-size: 1.125rem;
  color: #4b5563;
  text-transform: capitalize;
}

.weather-details {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (min-width: 768px) {
  .weather-details {
    grid-template-columns: repeat(3, 1fr);
    margin-left: auto;
  }
}

.weather-additional {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  padding: 0 1.5rem 1.5rem;
  background-color: #f0f9ff;
}

.detail {
  display: flex;
  flex-direction: column;
}

.detail-label {
  font-size: 0.75rem;
  color: #6b7280;
}

.detail-value {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1f2937;
}

.view-details-container {
  padding: 1rem 1.5rem;
  text-align: center;
  background-color: #f0f9ff;
}

.view-details-button {
  padding: 0.75rem 1.5rem;
  background-color: #3b82f6;
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
}

.view-details-button:hover {
  background-color: #2563eb;
}

.forecast {
  padding: 1.5rem;
  background-color: #f3f4f6;
}

.forecast-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 1rem;
  text-align: center;
}

.forecast-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 1rem;
}

.forecast-day {
  background-color: white;
  border-radius: 8px;
  padding: 1rem;
  text-align: center;
}

.forecast-date {
  font-weight: 600;
  color: #3b82f6;
}

.forecast-icon {
  width: 64px;
  height: 64px;
  margin: 0 auto;
}

.forecast-temp {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
}

.forecast-desc {
  font-size: 0.875rem;
  color: #6b7280;
  text-transform: capitalize;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 50;
  animation: fadeIn 0.2s ease-out;
}

.confirm-modal {
  background-color: white;
  border-radius: 8px;
  padding: 1.5rem;
  width: 90%;
  max-width: 400px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
  animation: slideIn 0.2s ease-out;
}

.modal-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 1rem;
  text-align: center;
}

.modal-text {
  margin-bottom: 0.5rem;
  color: #4b5563;
  text-align: center;
}

.modal-info {
  margin-bottom: 1.5rem;
  color: #6b7280;
  font-size: 0.9rem;
  text-align: center;
}

.modal-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.modal-button {
  padding: 0.5rem 1.5rem;
  border: none;
  border-radius: 0.375rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.modal-button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.cancel-button {
  background-color: #f3f4f6;
  color: #4b5563;
}

.cancel-button:hover:not(:disabled) {
  background-color: #e5e7eb;
}

.confirm-button {
  background-color: #3b82f6;
  color: white;
}

.confirm-button:hover {
  background-color: #2563eb;
}

.remove-button {
  background-color: #ef4444;
  color: white;
}

.remove-button:hover:not(:disabled) {
  background-color: #dc2626;
}

.button-spinner {
  display: inline-block;
  width: 1rem;
  height: 1rem;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: white;
  animation: spin 1s linear infinite;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
</style>