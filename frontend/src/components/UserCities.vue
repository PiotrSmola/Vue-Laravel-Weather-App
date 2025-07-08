<script setup>
import { ref, onMounted, computed } from 'vue';
import { useAuthStore } from "@/stores/auth";
import { useWeatherStore } from "@/stores/weather";

const authStore = useAuthStore();
const weatherStore = useWeatherStore();
const userCities = ref([]);
const loading = ref(false);
const error = ref(null);

const isAuthenticated = computed(() => {
  return !!authStore.user;
});

onMounted(async () => {
  if (isAuthenticated.value) {
    await loadUserCities();
  }
});

const loadUserCities = async () => {
  loading.value = true;
  error.value = null;
  
  try {
    const response = await fetch('/api/cities/user', {
      headers: {
        Authorization: `Bearer ${localStorage.getItem("token")}`,
      },
    });
    
    if (!response.ok) {
      throw new Error('Failed to load user cities');
    }
    
    const data = await response.json();
    userCities.value = data;
  } catch (err) {
    console.error('Load user cities error:', err);
    error.value = 'Failed to load your cities';
  } finally {
    loading.value = false;
  }
};

const removeCity = async (cityId) => {
  try {
    await weatherStore.removeCity(cityId);
    await loadUserCities();
  } catch (err) {
    console.error('Remove city error:', err);
    error.value = 'Failed to remove city';
  }
};
</script>

<template>
  <div v-if="isAuthenticated" class="user-cities">
    <h2 class="cities-title">Twoje miasta</h2>
    
    <div v-if="loading" class="cities-loading">
      <div class="w-8 h-8 border-4 border-blue-500 rounded-full border-t-transparent animate-spin"></div>
    </div>
    
    <div v-else-if="error" class="cities-error">
      {{ error }}
    </div>
    
    <div v-else-if="userCities.length === 0" class="no-cities">
      <p>Nie masz jeszcze dodanych miast</p>
      <p class="text-gray-500 text-sm">Użyj wyszukiwarki powyżej, aby dodać miasto</p>
    </div>
    
    <div v-else class="cities-list">
      <div 
        v-for="city in userCities" 
        :key="city.id"
        class="city-item"
      >
        <div class="city-info">
          <div class="city-name">{{ city.name }}</div>
          <div class="city-temp" v-if="city.current_temp">{{ Math.round(city.current_temp) }}°C</div>
        </div>
        
        <div class="city-actions">
          <button 
            @click="$emit('select-city', city)"
            class="view-city-btn"
          >
            Zobacz
          </button>
          <button 
            @click="removeCity(city.id)"
            class="remove-city-btn"
          >
            Usuń
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.user-cities {
  @apply max-w-lg mx-auto my-8 bg-white rounded-lg shadow-md p-4;
}

.cities-title {
  @apply text-xl font-bold text-gray-800 mb-4;
}

.cities-loading {
  @apply flex justify-center py-8;
}

.cities-error {
  @apply text-red-500 text-center py-4;
}

.no-cities {
  @apply text-center py-6;
}

.cities-list {
  @apply divide-y divide-gray-200;
}

.city-item {
  @apply flex items-center justify-between py-3;
}

.city-info {
  @apply flex items-center space-x-4;
}

.city-name {
  @apply font-medium;
}

.city-temp {
  @apply text-blue-600 font-bold;
}

.city-actions {
  @apply flex space-x-2;
}

.view-city-btn {
  @apply px-3 py-1 bg-blue-500 text-white text-sm rounded-md hover:bg-blue-600 transition-colors;
}

.remove-city-btn {
  @apply px-3 py-1 bg-red-500 text-white text-sm rounded-md hover:bg-red-600 transition-colors;
}
</style>