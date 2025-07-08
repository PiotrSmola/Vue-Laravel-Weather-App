<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  weather: {
    type: Object,
    required: true
  },
  city: {
    type: Object,
    required: true
  }
});

const getWeatherIconUrl = (iconCode) => {
  return `https://openweathermap.org/img/wn/${iconCode}@2x.png`;
};

const formattedDate = computed(() => {
  return new Date(props.weather.dt * 1000).toLocaleDateString('pl-PL', {
    weekday: 'long',
    year: 'numeric', 
    month: 'long', 
    day: 'numeric'
  });
});

const formattedTime = computed(() => {
  return new Date(props.weather.dt * 1000).toLocaleTimeString('pl-PL', {
    hour: '2-digit',
    minute: '2-digit'
  });
});

const sunrise = computed(() => {
  return new Date(props.weather.sys.sunrise * 1000).toLocaleTimeString('pl-PL', {
    hour: '2-digit',
    minute: '2-digit'
  });
});

const sunset = computed(() => {
  return new Date(props.weather.sys.sunset * 1000).toLocaleTimeString('pl-PL', {
    hour: '2-digit',
    minute: '2-digit'
  });
});

const windDirection = computed(() => {
  const deg = props.weather.wind.deg;
  const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
  const index = Math.round(deg / 45) % 8;
  return directions[index];
});

const feelsLike = computed(() => {
  return Math.round(props.weather.main.feels_like);
});

const temperature = computed(() => {
  return Math.round(props.weather.main.temp);
});

const windSpeed = computed(() => {
  return Math.round(props.weather.wind.speed * 3.6);
});

const backgroundClass = computed(() => {
  const weatherId = props.weather.weather[0].id;
  const hour = new Date(props.weather.dt * 1000).getHours();
  const isDay = hour >= 6 && hour < 20;
  
  if (weatherId === 800) {
    return isDay ? 'bg-clear-day' : 'bg-clear-night';
  }
  
  if (weatherId >= 801 && weatherId <= 804) {
    return isDay ? 'bg-cloudy-day' : 'bg-cloudy-night';
  }
  
  if ((weatherId >= 300 && weatherId <= 321) || 
      (weatherId >= 500 && weatherId <= 531)) {
    return 'bg-rainy';
  }
  
  if (weatherId >= 600 && weatherId <= 622) {
    return 'bg-snow';
  }
  
  if (weatherId >= 200 && weatherId <= 232) {
    return 'bg-storm';
  }
  
  if (weatherId >= 701 && weatherId <= 781) {
    return 'bg-mist';
  }
  
  return 'bg-default';
});
</script>

<template>
  <div class="detailed-weather" :class="backgroundClass">
    <div class="weather-content">
      <div class="weather-header">
        <h2 class="city-name">{{ city.name }}</h2>
        <div class="date-time">
          <div class="date">{{ formattedDate }}</div>
          <div class="time">{{ formattedTime }}</div>
        </div>
      </div>
      
      <div class="weather-main">
        <div class="temp-container">
          <div class="temp">{{ temperature }}°C</div>
          <div class="feels-like">Odczuwalna: {{ feelsLike }}°C</div>
        </div>
        
        <div class="weather-icon-desc">
          <img 
            :src="getWeatherIconUrl(weather.weather[0].icon)" 
            :alt="weather.weather[0].description"
            class="weather-icon"
          />
          <div class="weather-desc">{{ weather.weather[0].description }}</div>
        </div>
      </div>
      
      <div class="weather-details">
        <div class="detail-item">
          <div class="detail-label">Wilgotność</div>
          <div class="detail-value">{{ weather.main.humidity }}%</div>
        </div>
        
        <div class="detail-item">
          <div class="detail-label">Ciśnienie</div>
          <div class="detail-value">{{ weather.main.pressure }} hPa</div>
        </div>
        
        <div class="detail-item">
          <div class="detail-label">Wiatr</div>
          <div class="detail-value">{{ windSpeed }} km/h {{ windDirection }}</div>
        </div>
        
        <div class="detail-item">
          <div class="detail-label">Widoczność</div>
          <div class="detail-value">{{ Math.round(weather.visibility / 1000) }} km</div>
        </div>
        
        <div class="detail-item">
          <div class="detail-label">Wschód słońca</div>
          <div class="detail-value">{{ sunrise }}</div>
        </div>
        
        <div class="detail-item">
          <div class="detail-label">Zachód słońca</div>
          <div class="detail-value">{{ sunset }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.detailed-weather {
  @apply rounded-lg overflow-hidden shadow-lg text-white max-w-4xl mx-auto my-8;
  min-height: 400px;
}

.bg-clear-day {
  @apply bg-gradient-to-r from-blue-400 to-blue-600;
}

.bg-clear-night {
  @apply bg-gradient-to-r from-blue-900 to-indigo-900;
}

.bg-cloudy-day {
  @apply bg-gradient-to-r from-gray-400 to-blue-400;
}

.bg-cloudy-night {
  @apply bg-gradient-to-r from-gray-700 to-blue-800;
}

.bg-rainy {
  @apply bg-gradient-to-r from-blue-700 to-gray-700;
}

.bg-snow {
  @apply bg-gradient-to-r from-blue-100 to-gray-300 text-gray-800;
}

.bg-storm {
  @apply bg-gradient-to-r from-gray-800 to-blue-900;
}

.bg-mist {
  @apply bg-gradient-to-r from-gray-400 to-gray-600;
}

.bg-default {
  @apply bg-gradient-to-r from-blue-500 to-indigo-600;
}

.weather-content {
  @apply p-8;
}

.weather-header {
  @apply mb-8 flex justify-between flex-col md:flex-row;
}

.city-name {
  @apply text-3xl font-bold;
}

.date-time {
  @apply text-right opacity-90;
}

.weather-main {
  @apply flex justify-between items-center mb-8 flex-col md:flex-row gap-6;
}

.temp-container {
  @apply text-center md:text-left;
}

.temp {
  @apply text-6xl font-bold;
}

.feels-like {
  @apply opacity-80;
}

.weather-icon-desc {
  @apply flex flex-col items-center;
}

.weather-icon {
  @apply w-24 h-24;
}

.weather-desc {
  @apply capitalize text-xl;
}

.weather-details {
  @apply grid grid-cols-2 md:grid-cols-3 gap-6 bg-black bg-opacity-20 p-6 rounded-lg;
}

.detail-item {
  @apply flex flex-col;
}

.detail-label {
  @apply text-xs opacity-70;
}

.detail-value {
  @apply text-lg font-medium;
}

@media (max-width: 640px) {
  .weather-content {
    @apply p-4;
  }
  
  .temp {
    @apply text-5xl;
  }
  
  .weather-icon {
    @apply w-20 h-20;
  }
  
  .weather-details {
    @apply grid-cols-1 md:grid-cols-2 gap-4 p-4;
  }
}
</style>