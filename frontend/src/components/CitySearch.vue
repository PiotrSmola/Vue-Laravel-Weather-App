<script setup>
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useWeatherStore } from "@/stores/weather";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
  onCitySelect: {
    type: Function,
    default: null
  }
});

const emit = defineEmits(['citySelected']);

const weatherStore = useWeatherStore();
const authStore = useAuthStore();
const router = useRouter();

const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const searchError = ref(null);
const showResults = ref(false);
const selectedCity = ref(null);
const showConfirmModal = ref(false);
const isAddingToFavorites = ref(false);

let searchTimeout = null;
const debounceSearch = (query) => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  
  if (query.length < 3) {
    searchResults.value = [];
    showResults.value = false;
    return;
  }
  
  searchTimeout = setTimeout(async () => {
    isSearching.value = true;
    searchError.value = null;
    
    try {
      const results = await weatherStore.searchCities(query);
      searchResults.value = Array.isArray(results) ? results : [];
      showResults.value = true;
    } catch (error) {
      console.error('Błąd wyszukiwania:', error);
      searchError.value = 'Nie udało się wyszukać miast';
    } finally {
      isSearching.value = false;
    }
  }, 250);
};

watch(searchQuery, (newValue) => {
  debounceSearch(newValue);
});

const selectCity = async (city) => {
  try {
    selectedCity.value = city;
    
    emit('citySelected', city);
    
    searchQuery.value = '';
    searchResults.value = [];
    showResults.value = false;

    if (authStore.user && 
        !weatherStore.cities.some(c => c.openweather_id === city.id) && 
        !weatherStore.userFavorites.some(c => c.openweather_id === city.id)) {
      showConfirmModal.value = true;
    }
  } catch (error) {
    console.error('Błąd podczas wybierania miasta:', error);
  }
};

const addToFavorites = async () => {
  if (!authStore.user) {
    router.push('/login');
    return;
  }
  
  try {
    isAddingToFavorites.value = true;
    if (selectedCity.value && selectedCity.value.id) {
      await weatherStore.addCity(selectedCity.value.id);
      await weatherStore.getUserFavorites();
      await weatherStore.getFeaturedCities();
    }
  } catch (error) {
    console.error('Błąd podczas dodawania miasta do ulubionych:', error);
  } finally {
    isAddingToFavorites.value = false;
    showConfirmModal.value = false;
  }
};

const closeModal = () => {
  showConfirmModal.value = false;
};

const closeResults = () => {
  setTimeout(() => {
    showResults.value = false;
  }, 200);
};

const viewDetails = () => {
  if (selectedCity.value) {
    const params = {
      name: 'weather-details',
      params: { 
        id: selectedCity.value.name
      }
    };
    
    if (selectedCity.value.country) {
      params.params.id = `${selectedCity.value.name},${selectedCity.value.country}`;
    }
    
    router.push(params);
  }
};
</script>

<template>
  <div class="city-search">
    <div class="search-container">
      <input
        type="text"
        v-model="searchQuery"
        placeholder="Wyszukaj miasto..."
        class="search-input"
        @blur="closeResults"
      />
      
      <div v-if="isSearching" class="search-spinner"></div>
      
      <div v-if="showResults && searchResults.length > 0" class="search-results">
        <div 
          v-for="city in searchResults" 
          :key="city.id"
          class="search-result-item"
          @click="selectCity(city)"
        >
          <span class="city-name">{{ city.name }}</span>
          <span class="country-code">{{ city.country }}</span>
        </div>
      </div>
      
      <div v-else-if="showResults && searchQuery.length >= 3 && !isSearching" class="no-results">
        Nie znaleziono miast pasujących do "{{ searchQuery }}".
      </div>
    </div>
    
    <div v-if="searchError" class="search-error">
      {{ searchError }}
    </div>
    
    <div class="search-info">
      <p>Wpisz nazwę miasta, aby sprawdzić aktualną pogodę w dowolnym miejscu na świecie.</p>
    </div>
    
    <div v-if="showConfirmModal" class="modal-overlay">
      <div class="modal-content">
        <h3 class="modal-title">Dodaj do ulubionych</h3>
        <p class="modal-message">
          Czy chcesz dodać <strong>{{ selectedCity ? selectedCity.name : "" }}</strong> do swoich ulubionych miast?
        </p>
        <p class="modal-info">
          Dane pogodowe dla ulubionych miast będą zbierane co 30 minut, co pozwoli na dostęp do danych archiwalnych.
        </p>
        <div class="modal-buttons">
          <button 
            @click="closeModal" 
            class="modal-button cancel-button"
            :disabled="isAddingToFavorites"
          >
            Anuluj
          </button>
          <button 
            @click="addToFavorites" 
            class="modal-button confirm-button"
            :disabled="isAddingToFavorites"
          >
            <span v-if="isAddingToFavorites" class="button-spinner"></span>
            {{ isAddingToFavorites ? 'Dodawanie...' : 'Dodaj' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.city-search {
  max-width: 500px;
  margin: 2rem auto;
  position: relative;
}

.search-container {
  position: relative;
}

.search-input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 2px solid #e5e7eb;
  border-radius: 0.5rem;
  font-size: 1rem;
  transition: border-color 0.2s;
}

.search-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
}

.search-spinner {
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  width: 1.25rem;
  height: 1.25rem;
  border: 2px solid rgba(59, 130, 246, 0.3);
  border-radius: 50%;
  border-top-color: #3b82f6;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: translateY(-50%) rotate(360deg);
  }
}

.search-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  margin-top: 0.5rem;
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  z-index: 10;
  max-height: 300px;
  overflow-y: auto;
}

.search-result-item {
  padding: 0.75rem 1rem;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: background-color 0.2s;
}

.search-result-item:hover {
  background-color: #f3f4f6;
}

.city-name {
  font-weight: 500;
}

.country-code {
  background-color: #e5e7eb;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  color: #4b5563;
}

.no-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  margin-top: 0.5rem;
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  padding: 0.75rem 1rem;
  color: #6b7280;
  text-align: center;
}

.search-error {
  margin-top: 0.5rem;
  color: #ef4444;
  font-size: 0.875rem;
}

.search-info {
  margin-top: 1rem;
  padding: 0.75rem 1rem;
  background-color: #f3f4f6;
  border-radius: 0.5rem;
  color: #6b7280;
  font-size: 0.875rem;
  text-align: center;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.2s ease-out;
}

.modal-content {
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
  margin-bottom: 1rem;
  color: #1f2937;
  text-align: center;
}

.modal-message {
  margin-bottom: 0.5rem;
  color: #4b5563;
  text-align: center;
}

.modal-info {
  margin-bottom: 1.5rem;
  color: #6b7280;
  font-size: 0.875rem;
  text-align: center;
}

.modal-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.modal-button {
  padding: 0.5rem 1.25rem;
  border-radius: 0.375rem;
  font-weight: 500;
  cursor: pointer;
  border: none;
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
  background-color: #e5e7eb;
  color: #374151;
}

.cancel-button:hover:not(:disabled) {
  background-color: #d1d5db;
}

.confirm-button {
  background-color: #3b82f6;
  color: white;
}

.confirm-button:hover:not(:disabled) {
  background-color: #2563eb;
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