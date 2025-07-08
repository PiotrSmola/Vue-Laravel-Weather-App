<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useWeatherStore } from "@/stores/weather";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
  cityId: {
    type: [Number, String],
    required: true
  },
  currentWeather: {
    type: Object,
    default: null
  },
  forecast: {
    type: Object,
    default: null
  }
});

const router = useRouter();
const weatherStore = useWeatherStore();
const authStore = useAuthStore();
const activeTab = ref('overview');
const chartType = ref('temperature');
const isLoadingHistorical = ref(false);
const historicalData = ref(null);
const historicalError = ref(null);

const getWeatherIconUrl = (iconCode) => {
  return `https://openweathermap.org/img/wn/${iconCode}@2x.png`;
};

const formattedDate = computed(() => {
  if (!props.currentWeather) return '';
  return new Date(props.currentWeather.dt * 1000).toLocaleDateString('pl-PL', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
});

const formattedTime = computed(() => {
  if (!props.currentWeather) return '';
  return new Date(props.currentWeather.dt * 1000).toLocaleTimeString('pl-PL', {
    hour: '2-digit',
    minute: '2-digit'
  });
});

const sunrise = computed(() => {
  if (!props.currentWeather) return '';
  return new Date(props.currentWeather.sys.sunrise * 1000).toLocaleTimeString('pl-PL', {
    hour: '2-digit',
    minute: '2-digit'
  });
});

const sunset = computed(() => {
  if (!props.currentWeather) return '';
  return new Date(props.currentWeather.sys.sunset * 1000).toLocaleTimeString('pl-PL', {
    hour: '2-digit',
    minute: '2-digit'
  });
});

const windDirection = computed(() => {
  if (!props.currentWeather) return '';
  const deg = props.currentWeather.wind.deg;
  const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
  const index = Math.round(deg / 45) % 8;
  return directions[index];
});

const temperature = computed(() => {
  if (!props.currentWeather) return '';
  return Math.round(props.currentWeather.main.temp);
});

const feelsLike = computed(() => {
  if (!props.currentWeather) return '';
  return Math.round(props.currentWeather.main.feels_like);
});

const windSpeed = computed(() => {
  if (!props.currentWeather) return '';
  return Math.round(props.currentWeather.wind.speed * 3.6);
});

const cityName = computed(() => {
  if (!props.currentWeather) return '';
  return props.currentWeather.name;
});

const backgroundClass = computed(() => {
  if (!props.currentWeather) return 'bg-default';

  const weatherId = props.currentWeather.weather[0].id;
  const hour = new Date(props.currentWeather.dt * 1000).getHours();
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

const hourlyChartData = computed(() => {
  if (!props.forecast) return { labels: [], datasets: [] };

  const next24Hours = props.forecast.list.slice(0, 8);

  const labels = next24Hours.map(item => {
    return new Date(item.dt * 1000).toLocaleTimeString('pl-PL', {
      hour: '2-digit',
      minute: '2-digit'
    });
  });

  const tempData = next24Hours.map(item => Math.round(item.main.temp));
  const humidityData = next24Hours.map(item => item.main.humidity);
  const pressureData = next24Hours.map(item => item.main.pressure);

  let datasets = [];

  if (chartType.value === 'temperature') {
    datasets = [
      {
        label: 'Temperatura (°C)',
        data: tempData,
        borderColor: '#ff9800',
        backgroundColor: 'rgba(255, 152, 0, 0.2)',
        fill: true,
        tension: 0.4
      }
    ];
  } else if (chartType.value === 'humidity') {
    datasets = [
      {
        label: 'Wilgotność (%)',
        data: humidityData,
        borderColor: '#2196f3',
        backgroundColor: 'rgba(33, 150, 243, 0.2)',
        fill: true,
        tension: 0.4
      }
    ];
  } else if (chartType.value === 'pressure') {
    datasets = [
      {
        label: 'Ciśnienie (hPa)',
        data: pressureData,
        borderColor: '#4caf50',
        backgroundColor: 'rgba(76, 175, 80, 0.2)',
        fill: true,
        tension: 0.4
      }
    ];
  } else if (chartType.value === 'combined') {
    datasets = [
      {
        label: 'Temperatura (°C)',
        data: tempData,
        borderColor: '#ff9800',
        backgroundColor: 'rgba(255, 152, 0, 0.1)',
        fill: true,
        tension: 0.4,
        yAxisID: 'y'
      },
      {
        label: 'Wilgotność (%)',
        data: humidityData,
        borderColor: '#2196f3',
        backgroundColor: 'rgba(33, 150, 243, 0.1)',
        fill: true,
        tension: 0.4,
        yAxisID: 'y1'
      }
    ];
  }

  return { labels, datasets };
});

const dailyForecast = computed(() => {
  if (!props.forecast) return [];

  const dailyData = [];
  const dates = new Set();

  for (const item of props.forecast.list) {
    const date = new Date(item.dt * 1000).toISOString().split('T')[0];
    const hour = new Date(item.dt * 1000).getHours();

    if (!dates.has(date) && hour >= 12 && hour <= 14) {
      dates.add(date);
      dailyData.push(item);

      if (dailyData.length >= 5) break;
    }
  }

  return dailyData.map(day => ({
    date: new Date(day.dt * 1000).toLocaleDateString('pl-PL', { weekday: 'long', day: 'numeric', month: 'short' }),
    temp: Math.round(day.main.temp),
    feelsLike: Math.round(day.main.feels_like),
    description: day.weather[0].description,
    icon: day.weather[0].icon,
    humidity: day.main.humidity,
    pressure: day.main.pressure,
    windSpeed: Math.round(day.wind.speed * 3.6),
    weatherId: day.weather[0].id
  }));
});

const generateHistoricalChartData = computed(() => {
  if (!historicalData.value || !historicalData.value.data || historicalData.value.data.length === 0) {
    return { points: [], tempMin: 0, tempMax: 30, humidityMin: 0, humidityMax: 100 };
  }

  const data = historicalData.value.data;
  
  const tempValues = data.map(d => d.temperature);
  const humidityValues = data.map(d => d.humidity);
  
  const tempMin = Math.floor(Math.min(...tempValues));
  const tempMax = Math.ceil(Math.max(...tempValues));
  const humidityMin = Math.floor(Math.min(...humidityValues));
  const humidityMax = Math.ceil(Math.max(...humidityValues));
  
  const points = data.map((item, index) => {
    const x = (index / (data.length - 1)) * 100;
    
    // Normalizacja wartości do wysokości wykresu (200px)
    const tempHeight = 200 - ((item.temperature - tempMin) / (tempMax - tempMin)) * 200;
    const humidityHeight = 200 - ((item.humidity - humidityMin) / (humidityMax - humidityMin)) * 200;
    
    return {
      x,
      tempY: tempHeight,
      humidityY: humidityHeight,
      date: new Date(item.timestamp).toLocaleDateString('pl-PL', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
      }),
      temperature: item.temperature,
      humidity: item.humidity
    };
  });
  
  return {
    points,
    tempMin,
    tempMax,
    humidityMin,
    humidityMax
  };
});

const hasHistoricalData = computed(() => {
  return historicalData.value && 
    historicalData.value.data && 
    historicalData.value.data.length > 0;
});

const fetchHistoricalData = async () => {
  if (!authStore.user) {
    historicalError.value = 'Zaloguj się, aby zobaczyć dane historyczne';
    return;
  }

  const cityId = typeof props.cityId === 'string' && props.cityId.includes(',')
    ? props.currentWeather.id
    : props.cityId;

  if (!cityId) {
    historicalError.value = 'Brak identyfikatora miasta';
    return;
  }

  isLoadingHistorical.value = true;
  historicalError.value = null;

  try {
    await weatherStore.getHistoricalData(cityId);
    historicalData.value = weatherStore.historicalData;
    
    if (!historicalData.value || !historicalData.value.data || historicalData.value.data.length === 0) {
      historicalError.value = 'Brak danych historycznych dla tego miasta';
    }
  } catch (error) {
    console.error('Błąd pobierania danych historycznych:', error);
    historicalError.value = error.message || 'Nie udało się pobrać danych historycznych';
  } finally {
    isLoadingHistorical.value = false;
  }
};

watch(activeTab, (newTab) => {
  if (newTab === 'historical' && !historicalData.value && !isLoadingHistorical.value) {
    fetchHistoricalData();
  }
});

const setChartType = (type) => {
  chartType.value = type;
};

const goBack = () => {
  router.go(-1);
};

const formatDateForDaily = (timestamp) => {
  return new Date(timestamp * 1000).toLocaleDateString('pl-PL', {
    weekday: 'long',
    day: 'numeric',
    month: 'short'
  });
};
</script>

<template>
  <div class="weather-details-page">
    <div class="weather-details-container">
      <div class="navigation">
        <button @click="goBack" class="back-button">
          ← Powrót
        </button>
      </div>

      <div class="main-weather-card" :class="backgroundClass" v-if="currentWeather">
        <div class="weather-header">
          <h1 class="city-name">{{ cityName }}</h1>
          <div class="date-time">
            <div class="date">{{ formattedDate }}</div>
            <div class="time">{{ formattedTime }}</div>
          </div>
        </div>

        <div class="current-conditions">
          <div class="temperature-container">
            <div class="current-temp">{{ temperature }}°C</div>
            <div class="feels-like">Odczuwalna: {{ feelsLike }}°C</div>
          </div>
          <div class="weather-icon-container">
            <img :src="getWeatherIconUrl(currentWeather.weather[0].icon)" :alt="currentWeather.weather[0].description"
              class="weather-icon" />
            <div class="weather-description">{{ currentWeather.weather[0].description }}</div>
          </div>
        </div>

        <div class="weather-metrics">
          <div class="metric">
            <div class="metric-label">Wilgotność</div>
            <div class="metric-value">{{ currentWeather.main.humidity }}%</div>
          </div>
          <div class="metric">
            <div class="metric-label">Ciśnienie</div>
            <div class="metric-value">{{ currentWeather.main.pressure }} hPa</div>
          </div>
          <div class="metric">
            <div class="metric-label">Wiatr</div>
            <div class="metric-value">{{ windSpeed }} km/h {{ windDirection }}</div>
          </div>
          <div class="metric">
            <div class="metric-label">Widoczność</div>
            <div class="metric-value">{{ Math.round(currentWeather.visibility / 1000) }} km</div>
          </div>
          <div class="metric">
            <div class="metric-label">Wschód słońca</div>
            <div class="metric-value">{{ sunrise }}</div>
          </div>
          <div class="metric">
            <div class="metric-label">Zachód słońca</div>
            <div class="metric-value">{{ sunset }}</div>
          </div>
        </div>
      </div>

      <div class="tabs">
        <button @click="activeTab = 'overview'" :class="['tab-button', { active: activeTab === 'overview' }]">
          Przegląd
        </button>
        <button @click="activeTab = 'hourly'" :class="['tab-button', { active: activeTab === 'hourly' }]">
          Prognoza godzinowa
        </button>
        <button @click="activeTab = 'daily'" :class="['tab-button', { active: activeTab === 'daily' }]">
          Prognoza 5-dniowa
        </button>
        <button @click="activeTab = 'charts'" :class="['tab-button', { active: activeTab === 'charts' }]">
          Wykresy
        </button>
        <button v-if="authStore.user" @click="activeTab = 'historical'" 
          :class="['tab-button', { active: activeTab === 'historical' }]">
          Dane historyczne
        </button>
      </div>

      <div class="tab-content">
        <div v-if="activeTab === 'overview'" class="overview-tab">
          <div class="overview-section">
            <h2>Warunki obecne</h2>
            <p class="overview-description" v-if="currentWeather">
              Obecnie w {{ cityName }} jest {{ currentWeather.weather[0].description }} z temperaturą {{
              temperature }}°C.
              Temperatura odczuwalna to {{ feelsLike }}°C, a wilgotność powietrza wynosi {{
              currentWeather.main.humidity }}%.
              Wiatr wieje z prędkością {{ windSpeed }} km/h z kierunku {{ windDirection }}.
            </p>

            <div v-if="currentWeather && currentWeather.rain" class="additional-info">
              <h3>Opady deszczu</h3>
              <p>
                Ostatnia godzina: {{ currentWeather.rain['1h'] || 0 }} mm<br>
                Ostatnie 3 godziny: {{ currentWeather.rain['3h'] || 0 }} mm
              </p>
            </div>

            <div v-if="currentWeather && currentWeather.snow" class="additional-info">
              <h3>Opady śniegu</h3>
              <p>
                Ostatnia godzina: {{ currentWeather.snow['1h'] || 0 }} mm<br>
                Ostatnie 3 godziny: {{ currentWeather.snow['3h'] || 0 }} mm
              </p>
            </div>
          </div>

          <div class="overview-section">
            <h2>Dzisiaj</h2>
            <div class="today-highlights">
              <div class="highlight-card">
                <h3>Indeks UV</h3>
                <div class="highlight-value">{{ currentWeather && currentWeather.uvi ?
                  Math.round(currentWeather.uvi) : 'N/A' }}</div>
              </div>

              <div class="highlight-card">
                <h3>Zachmurzenie</h3>
                <div class="highlight-value">{{ currentWeather ? currentWeather.clouds.all : 0 }}%</div>
              </div>

              <div class="highlight-card">
                <h3>Szansa opadów</h3>
                <div class="highlight-value">
                  {{ forecast && forecast.list[0].pop ? Math.round(forecast.list[0].pop * 100) : 0 }}%
                </div>
              </div>
            </div>
          </div>

          <div class="overview-section" v-if="forecast">
            <h2>Prognoza na dziś</h2>
            <div class="today-forecast">
              <div v-for="(item, index) in forecast.list.slice(0, 4)" :key="index" class="forecast-hour-card">
                <div class="forecast-time">
                  {{ new Date(item.dt * 1000).toLocaleTimeString('pl-PL', {
                    hour: '2-digit', minute: '2-digit'
                  }) }}
                </div>
                <img :src="getWeatherIconUrl(item.weather[0].icon)" :alt="item.weather[0].description"
                  class="forecast-icon" />
                <div class="forecast-temp">{{ Math.round(item.main.temp) }}°C</div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="activeTab === 'hourly'" class="hourly-tab">
          <h2>Prognoza godzinowa</h2>

          <div class="hourly-forecast" v-if="forecast">
            <div v-for="(item, index) in forecast.list.slice(0, 24)" :key="index" class="hourly-forecast-item">
              <div class="hourly-time">
                {{ new Date(item.dt * 1000).toLocaleTimeString('pl-PL', {
                  hour: '2-digit', minute: '2-digit'
                }) }}
              </div>
              <img :src="getWeatherIconUrl(item.weather[0].icon)" :alt="item.weather[0].description"
                class="hourly-icon" />
              <div class="hourly-temp">{{ Math.round(item.main.temp) }}°C</div>
              <div class="hourly-description">{{ item.weather[0].description }}</div>
              <div class="hourly-details">
                <div class="hourly-detail">
                  <span class="detail-label">Wiatr:</span>
                  <span class="detail-value">{{ Math.round(item.wind.speed * 3.6) }} km/h</span>
                </div>
                <div class="hourly-detail">
                  <span class="detail-label">Wilgotność:</span>
                  <span class="detail-value">{{ item.main.humidity }}%</span>
                </div>
                <div class="hourly-detail">
                  <span class="detail-label">Opady:</span>
                  <span class="detail-value">{{ item.pop ? Math.round(item.pop * 100) : 0 }}%</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="activeTab === 'daily'" class="daily-tab">
          <h2>Prognoza 5-dniowa</h2>

          <div class="daily-forecast">
            <div v-for="(day, index) in dailyForecast" :key="index" class="daily-forecast-item">
              <div class="daily-date">{{ day.date }}</div>
              <div class="daily-weather">
                <img :src="getWeatherIconUrl(day.icon)" :alt="day.description" class="daily-icon" />
                <div class="daily-description">{{ day.description }}</div>
              </div>
              <div class="daily-temps">
                <div class="daily-high">{{ day.temp }}°C</div>
                <div class="daily-feels-like">Odczuwalna: {{ day.feelsLike }}°C</div>
              </div>
              <div class="daily-details">
                <div class="daily-detail">
                  <span class="detail-label">Wiatr:</span>
                  <span class="detail-value">{{ day.windSpeed }} km/h</span>
                </div>
                <div class="daily-detail">
                  <span class="detail-label">Wilgotność:</span>
                  <span class="detail-value">{{ day.humidity }}%</span>
                </div>
                <div class="daily-detail">
                  <span class="detail-label">Ciśnienie:</span>
                  <span class="detail-value">{{ day.pressure }} hPa</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="activeTab === 'charts'" class="charts-tab">
          <h2>Wykresy pogodowe</h2>

          <div class="chart-type-selector">
            <button @click="setChartType('temperature')"
              :class="['chart-type-button', { active: chartType === 'temperature' }]">
              Temperatura
            </button>
            <button @click="setChartType('humidity')" :class="['chart-type-button', { active: chartType === 'humidity' }]">
              Wilgotność
            </button>
            <button @click="setChartType('pressure')" :class="['chart-type-button', { active: chartType === 'pressure' }]">
              Ciśnienie
            </button>
            <button @click="setChartType('combined')" :class="['chart-type-button', { active: chartType === 'combined' }]">
              Temp. i wilgotność
            </button>
          </div>

          <div class="chart-container">
            <div v-if="forecast" class="line-chart">
              <div class="chart-placeholder">
                <h3>Wykres {{
                  chartType === 'temperature' ? 'temperatury' :
                    chartType === 'humidity' ? 'wilgotności' :
                      chartType === 'pressure' ? 'ciśnienia' :
                        'temperatury i wilgotności'
                }}</h3>
                <div v-if="chartType === 'combined'" class="combined-chart-data">
                  <div class="chart-legend">
                    <div v-for="(label, index) in hourlyChartData.labels" :key="index" class="legend-item">
                      {{ label }}
                    </div>
                  </div>
                  <div class="combined-chart-values">
                    <div class="chart-values temperature-values">
                      <div v-for="(value, index) in hourlyChartData.datasets[0].data" :key="index"
                        class="chart-bar temperature-bar" :style="{
                          height: `${value * 4}px`,
                          backgroundColor: hourlyChartData.datasets[0].borderColor
                        }">
                        <span class="chart-value">{{ value }}°C</span>
                      </div>
                    </div>

                    <div class="chart-values humidity-values">
                      <div v-for="(value, index) in hourlyChartData.datasets[1].data" :key="index"
                        class="chart-bar humidity-bar" :style="{
                          height: `${value * 1.5}px`,
                          backgroundColor: hourlyChartData.datasets[1].borderColor
                        }">
                        <span class="chart-value">{{ value }}%</span>
                      </div>
                    </div>
                  </div>
                  <div class="chart-legend-labels">
                    <div class="legend-label" style="color: #ff9800;">● Temperatura</div>
                    <div class="legend-label" style="color: #2196f3;">● Wilgotność</div>
                  </div>
                </div>

                <div v-else class="chart-data">
                  <div class="chart-legend">
                    <div v-for="(label, index) in hourlyChartData.labels" :key="index" class="legend-item">
                      {{ label }}
                    </div>
                  </div>
                  <div class="chart-values">
                    <div v-for="(value, index) in hourlyChartData.datasets[0].data" :key="index" class="chart-bar"
                      :style="{
                        height: chartType === 'temperature' ? `${value * 5}px` :
                          chartType === 'humidity' ? `${value * 2}px` : `${(value - 990) * 2}px`,
                        backgroundColor: hourlyChartData.datasets[0].borderColor
                      }">
                      <span class="chart-value">{{ value }}{{
                        chartType === 'temperature' ? '°C' :
                          chartType === 'humidity' ? '%' : ' hPa'
                      }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="wind-chart" v-if="currentWeather">
            <h3>Kierunek i prędkość wiatru</h3>
            <div class="wind-compass">
              <div class="compass-circle">
                <div class="compass-marker n">N</div>
                <div class="compass-marker e">E</div>
                <div class="compass-marker s">S</div>
                <div class="compass-marker w">W</div>
                <div class="wind-arrow" :style="{ transform: `rotate(${currentWeather.wind.deg}deg)` }">
                </div>
              </div>
              <div class="wind-speed">
                {{ windSpeed }} km/h
              </div>
            </div>
          </div>
        </div>

        <div v-if="activeTab === 'historical'" class="historical-tab">
          <h2>Dane historyczne</h2>

          <div v-if="isLoadingHistorical" class="loading-container">
            <div class="loading-spinner"></div>
            <p>Ładowanie danych historycznych...</p>
          </div>

          <div v-else-if="historicalError" class="error-container">
            <p class="error-message">{{ historicalError }}</p>
            <button v-if="authStore.user" @click="fetchHistoricalData" class="retry-button">
              Spróbuj ponownie
            </button>
            <p v-else class="login-info">
              Zaloguj się, aby mieć dostęp do danych historycznych dla miast dodanych do ulubionych.
            </p>
          </div>

          <div v-else-if="hasHistoricalData" class="historical-data-container">
            <div class="historical-info">
              <p>Poniżej przedstawione są dane archiwalne dla miasta {{ cityName }} zbierane co 30 minut.</p>
              <p class="data-info">
                Łączna liczba pomiarów: <strong>{{ historicalData.data.length }}</strong> |
                Od: <strong>{{ new Date(historicalData.data[0].timestamp).toLocaleDateString('pl-PL', {
                  year: 'numeric',
                  month: 'short',
                  day: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit'
                }) }}</strong> |
                Do: <strong>{{ new Date(historicalData.data[historicalData.data.length - 1].timestamp).toLocaleDateString('pl-PL', {
                  year: 'numeric',
                  month: 'short',
                  day: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit'
                }) }}</strong>
              </p>
            </div>

            <div class="historical-chart-container">
              <h3>Wykres temperatury i wilgotności</h3>
              
              <div class="historical-chart">
                <div class="chart-legend-labels">
                  <div class="legend-label" style="color: #ff9800;">● Temperatura (°C)</div>
                  <div class="legend-label" style="color: #2196f3;">● Wilgotność (%)</div>
                </div>
                
                <div class="chart-area">
                  <div class="y-axis temp-axis">
                    <div v-for="i in 5" :key="`temp-${i}`" class="axis-tick">
                      {{ Math.round(generateHistoricalChartData.tempMin + (generateHistoricalChartData.tempMax - generateHistoricalChartData.tempMin) * (5-i) / 4) }}°C
                    </div>
                  </div>
                  
                  <div class="chart-svg-container">
                    <svg width="100%" height="220" viewBox="0 0 100 200" preserveAspectRatio="none">
                      <polyline 
                        :points="generateHistoricalChartData.points.map(p => `${p.x},${p.tempY}`).join(' ')"
                        fill="none" 
                        stroke="#ff9800" 
                        stroke-width="2"
                      />
                      
                      <polyline
                        :points="generateHistoricalChartData.points.map(p => `${p.x},${p.humidityY}`).join(' ')"
                        fill="none" 
                        stroke="#2196f3" 
                        stroke-width="2"
                        stroke-dasharray="3,3"
                      />
                      
                      <g v-for="(point, index) in generateHistoricalChartData.points" :key="`temp-point-${index}`">
                        <circle 
                          :cx="point.x" 
                          :cy="point.tempY" 
                          r="0.8" 
                          fill="#ff9800" 
                        />
                      </g>
                      
                      <g v-for="(point, index) in generateHistoricalChartData.points" :key="`hum-point-${index}`">
                        <circle 
                          :cx="point.x" 
                          :cy="point.humidityY" 
                          r="0.8" 
                          fill="#2196f3" 
                        />
                      </g>
                    </svg>
                  </div>
                  
                  <div class="y-axis humidity-axis">
                    <div v-for="i in 5" :key="`hum-${i}`" class="axis-tick">
                      {{ Math.round(generateHistoricalChartData.humidityMin + (generateHistoricalChartData.humidityMax - generateHistoricalChartData.humidityMin) * (5-i) / 4) }}%
                    </div>
                  </div>
                </div>
                
                <div class="x-axis">
                  <div v-if="historicalData.data.length > 20" class="date-range">
                    <span>{{ new Date(historicalData.data[0].timestamp).toLocaleDateString('pl-PL', {
                      day: 'numeric',
                      month: 'short'
                    }) }}</span>
                    <span>{{ new Date(historicalData.data[Math.floor(historicalData.data.length / 2)].timestamp).toLocaleDateString('pl-PL', {
                      day: 'numeric',
                      month: 'short'
                    }) }}</span>
                    <span>{{ new Date(historicalData.data[historicalData.data.length - 1].timestamp).toLocaleDateString('pl-PL', {
                      day: 'numeric',
                      month: 'short'
                    }) }}</span>
                  </div>
                  <div v-else class="date-ticks">
                    <div v-for="(item, index) in historicalData.data" :key="`date-${index}`" 
                      :style="{ left: `${(index / (historicalData.data.length - 1)) * 100}%` }"
                      class="date-tick">
                      {{ new Date(item.timestamp).toLocaleDateString('pl-PL', {
                        day: 'numeric',
                        month: 'short',
                        hour: '2-digit'
                      }) }}
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="historical-stats">
                <h3>Statystyki</h3>
                <div class="stats-grid">
                  <div class="stat-item">
                    <div class="stat-label">Minimalna temperatura</div>
                    <div class="stat-value temp-value">
                      {{ Math.min(...historicalData.data.map(d => d.temperature)).toFixed(1) }}°C
                    </div>
                  </div>
                  <div class="stat-item">
                    <div class="stat-label">Maksymalna temperatura</div>
                    <div class="stat-value temp-value">
                      {{ Math.max(...historicalData.data.map(d => d.temperature)).toFixed(1) }}°C
                    </div>
                  </div>
                  <div class="stat-item">
                    <div class="stat-label">Średnia temperatura</div>
                    <div class="stat-value temp-value">
                      {{ (historicalData.data.reduce((sum, d) => sum + d.temperature, 0) / historicalData.data.length).toFixed(1) }}°C
                    </div>
                  </div>
                  <div class="stat-item">
                    <div class="stat-label">Minimalna wilgotność</div>
                    <div class="stat-value humidity-value">
                      {{ Math.min(...historicalData.data.map(d => d.humidity)) }}%
                    </div>
                  </div>
                  <div class="stat-item">
                    <div class="stat-label">Maksymalna wilgotność</div>
                    <div class="stat-value humidity-value">
                      {{ Math.max(...historicalData.data.map(d => d.humidity)) }}%
                    </div>
                  </div>
                  <div class="stat-item">
                    <div class="stat-label">Średnia wilgotność</div>
                    <div class="stat-value humidity-value">
                      {{ Math.round(historicalData.data.reduce((sum, d) => sum + d.humidity, 0) / historicalData.data.length) }}%
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div v-else class="no-data-container">
            <p>Brak danych historycznych. Dodaj to miasto do ulubionych, aby zbierać dane archiwalne.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.weather-details-page {
  max-width: 1000px;
  margin: 0 auto;
  padding: 1rem;
}

.weather-details-container {
  background-color: #f5f7fa;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.navigation {
  padding: 1rem;
  display: flex;
  justify-content: flex-start;
}

.back-button {
  background: none;
  border: none;
  color: #3b82f6;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  transition: background-color 0.2s;
}

.back-button:hover {
  background-color: rgba(59, 130, 246, 0.1);
}

.main-weather-card {
  padding: 2rem;
  color: white;
  border-radius: 8px;
  margin: 0 1rem 1rem;
}

.bg-clear-day {
  background: linear-gradient(135deg, #4da0ff, #1e56b1);
}

.bg-clear-night {
  background: linear-gradient(135deg, #0a367a, #041b4d);
}

.bg-cloudy-day {
  background: linear-gradient(135deg, #8e9eac, #5a6978);
}

.bg-cloudy-night {
  background: linear-gradient(135deg, #464d57, #2c3238);
}

.bg-rainy {
  background: linear-gradient(135deg, #345d9d, #263649);
}

.bg-snow {
  background: linear-gradient(135deg, #c8d7e5, #94a4b8);
  color: #2c3e50;
}

.bg-storm {
  background: linear-gradient(135deg, #2c3e50, #1a202c);
}

.bg-mist {
  background: linear-gradient(135deg, #7a8b99, #4a5763);
}

.bg-default {
  background: linear-gradient(135deg, #3498db, #2c3e50);
}

.weather-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 2rem;
}

@media (min-width: 640px) {
  .weather-header {
    flex-direction: row;
    justify-content: space-between;
    align-items: flex-start;
  }
}

.city-name {
  font-size: 2.5rem;
  font-weight: 700;
  margin: 0;
  text-align: center;
}

@media (min-width: 640px) {
  .city-name {
    text-align: left;
  }
}

.date-time {
  text-align: center;
  opacity: 0.9;
  margin-top: 0.5rem;
}

@media (min-width: 640px) {
  .date-time {
    text-align: right;
    margin-top: 0;
  }
}

.current-conditions {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 2rem;
}

@media (min-width: 640px) {
  .current-conditions {
    flex-direction: row;
    justify-content: space-between;
  }
}

.temperature-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 1rem;
}

@media (min-width: 640px) {
  .temperature-container {
    align-items: flex-start;
    margin-bottom: 0;
  }
}

.current-temp {
  font-size: 4rem;
  font-weight: 700;
  line-height: 1;
}

.feels-like {
  font-size: 1.25rem;
  opacity: 0.9;
  margin-top: 0.5rem;
}

.weather-icon-container {
  text-align: center;
}

.weather-icon {
  width: 100px;
  height: 100px;
}

.weather-description {
  font-size: 1.5rem;
  text-transform: capitalize;
}

.weather-metrics {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
  background-color: rgba(0, 0, 0, 0.2);
  padding: 1.5rem;
  border-radius: 8px;
}

@media (min-width: 768px) {
  .weather-metrics {
    grid-template-columns: repeat(3, 1fr);
  }
}

.metric {
  display: flex;
  flex-direction: column;
}

.metric-label {
  font-size: 0.875rem;
  opacity: 0.8;
}

.metric-value {
  font-size: 1.25rem;
  font-weight: 600;
  margin-top: 0.25rem;
}

.tabs {
  display: flex;
  flex-wrap: wrap;
  border-bottom: 1px solid #e2e8f0;
  padding: 0 1rem;
}

.tab-button {
  padding: 1rem 0.75rem;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s;
  text-align: center;
  flex: 1;
  white-space: nowrap;
  font-size: 0.9rem;
}

@media (min-width: 640px) {
  .tab-button {
    padding: 1rem 1.5rem;
    flex: none;
    font-size: 1rem;
  }
}

.tab-button:hover {
  color: #3b82f6;
}

.tab-button.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
}

.tab-content {
  padding: 1.5rem;
}

@media (min-width: 640px) {
  .tab-content {
    padding: 2rem;
  }
}

.historical-tab h2 {
  font-size: 1.5rem;
  color: #1e293b;
  margin-bottom: 1.5rem;
  text-align: center;
}

.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  background-color: #f8fafc;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid rgba(59, 130, 246, 0.1);
  border-radius: 50%;
  border-left-color: #3b82f6;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

.error-container {
  padding: 2rem;
  background-color: #fee2e2;
  border-radius: 8px;
  text-align: center;
  color: #b91c1c;
  margin-bottom: 1rem;
}

.error-message {
  font-weight: 500;
  margin-bottom: 1rem;
}

.retry-button {
  padding: 0.5rem 1rem;
  background-color: #3b82f6;
  color: white;
  border: none;
  border-radius: 0.25rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
}

.retry-button:hover {
  background-color: #2563eb;
}

.login-info {
  color: #64748b;
  margin-top: 0.5rem;
  font-size: 0.9rem;
}

.no-data-container {
  padding: 2rem;
  background-color: #f1f5f9;
  border-radius: 8px;
  text-align: center;
  color: #64748b;
}

.historical-data-container {
  background-color: #f8fafc;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.historical-info {
  margin-bottom: 1.5rem;
  text-align: center;
  color: #1e293b;
}

.data-info {
  font-size: 0.9rem;
  color: #64748b;
  margin-top: 0.5rem;
}

.historical-chart-container {
  margin-bottom: 2rem;
}

.historical-chart-container h3 {
  font-size: 1.25rem;
  color: #1e293b;
  margin-bottom: 1rem;
  text-align: center;
}

.historical-chart {
  background-color: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.chart-legend-labels {
  display: flex;
  justify-content: center;
  gap: 1.5rem;
  margin-bottom: 1rem;
}

.legend-label {
  display: flex;
  align-items: center;
  font-weight: 500;
  font-size: 0.9rem;
}

.chart-area {
  display: flex;
  margin-bottom: 1rem;
  height: 220px;
}

.y-axis {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 10px 0;
  font-size: 0.75rem;
  color: #64748b;
  width: 50px;
}

.temp-axis {
  color: #ff9800;
}

.humidity-axis {
  color: #2196f3;
  text-align: right;
}

.axis-tick {
  height: 1px;
}

.chart-svg-container {
  flex: 1;
  border-left: 1px solid #e2e8f0;
  border-right: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
  position: relative;
  background-color: #f8fafc;
}

.x-axis {
  padding-top: 0.5rem;
  font-size: 0.75rem;
  color: #64748b;
}

.date-range {
  display: flex;
  justify-content: space-between;
  padding: 0 50px;
}

.date-ticks {
  position: relative;
  height: 30px;
}

.date-tick {
  position: absolute;
  transform: translateX(-50%) rotate(-45deg);
  font-size: 0.7rem;
  white-space: nowrap;
  bottom: 0;
}

.historical-stats {
  margin-top: 2rem;
  background-color: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 1rem;
}

@media (min-width: 640px) {
  .stats-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.stat-item {
  background-color: #f8fafc;
  padding: 1rem;
  border-radius: 6px;
  text-align: center;
}

.stat-label {
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 0.5rem;
}

.stat-value {
  font-size: 1.25rem;
  font-weight: 600;
}

.temp-value {
  color: #ff9800;
}

.humidity-value {
  color: #2196f3;
}

.temp-value {
  color: #ff9800;
}

.humidity-value {
  color: #2196f3;
}

.overview-tab h2 {
  font-size: 1.5rem;
  color: #1e293b;
  margin-bottom: 1rem;
}

.overview-section {
  margin-bottom: 2rem;
}

.overview-description {
  line-height: 1.6;
  color: #4b5563;
}

.additional-info {
  background-color: #f8fafc;
  border-radius: 8px;
  padding: 1rem;
  margin-top: 1rem;
}

.additional-info h3 {
  font-size: 1.1rem;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.today-highlights {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 1rem;
}

@media (min-width: 640px) {
  .today-highlights {
    grid-template-columns: repeat(3, 1fr);
  }
}

.highlight-card {
  background-color: #f8fafc;
  padding: 1.5rem;
  border-radius: 8px;
  text-align: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.highlight-card h3 {
  font-size: 1rem;
  color: #64748b;
  margin-bottom: 0.75rem;
}

.highlight-value {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
}

.today-forecast {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

@media (min-width: 640px) {
  .today-forecast {
    grid-template-columns: repeat(4, 1fr);
  }
}

.forecast-hour-card {
  background-color: #f8fafc;
  padding: 1rem;
  border-radius: 8px;
  text-align: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.forecast-time {
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 0.5rem;
}

.forecast-icon {
  width: 50px;
  height: 50px;
  margin: 0 auto;
}

.forecast-temp {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
}

.hourly-tab h2 {
  font-size: 1.5rem;
  color: #1e293b;
  margin-bottom: 1.5rem;
  text-align: center;
}

.hourly-forecast {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 1rem;
}

@media (min-width: 480px) {
  .hourly-forecast {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 768px) {
  .hourly-forecast {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (min-width: 1024px) {
  .hourly-forecast {
    grid-template-columns: repeat(4, 1fr);
  }
}

.hourly-forecast-item {
  background-color: white;
  padding: 1.5rem;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  transition: transform 0.2s;
}

.hourly-forecast-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.hourly-time {
  font-size: 1.125rem;
  font-weight: 600;
  color: #3b82f6;
  margin-bottom: 0.5rem;
}

.hourly-icon {
  width: 50px;
  height: 50px;
  margin: 0.5rem 0;
}

.hourly-temp {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.hourly-description {
  font-size: 1rem;
  color: #64748b;
  text-transform: capitalize;
  margin-bottom: 1rem;
  text-align: center;
}

.hourly-details {
  width: 100%;
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.hourly-detail {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.875rem;
  color: #475569;
}

.daily-tab h2 {
  font-size: 1.5rem;
  color: #1e293b;
  margin-bottom: 1.5rem;
  text-align: center;
}

.daily-forecast {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 1rem;
}

@media (min-width: 640px) {
  .daily-forecast {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .daily-forecast {
    grid-template-columns: repeat(3, 1fr);
  }
}

.daily-forecast-item {
  background-color: white;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  transition: transform 0.2s;
}

.daily-forecast-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.daily-date {
  font-size: 1.125rem;
  font-weight: 700;
  color: #3b82f6;
  margin-bottom: 1rem;
  text-align: center;
}

.daily-weather {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 1rem;
}

.daily-icon {
  width: 64px;
  height: 64px;
  margin-bottom: 0.5rem;
}

.daily-description {
  font-size: 1rem;
  color: #64748b;
  text-transform: capitalize;
  text-align: center;
}

.daily-temps {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 1rem;
}

.daily-high {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
}

.daily-feels-like {
  font-size: 0.875rem;
  color: #64748b;
  margin-top: 0.25rem;
}

.daily-details {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 0.75rem;
}

@media (min-width: 480px) {
  .daily-details {
    grid-template-columns: repeat(3, 1fr);
  }
}

.daily-detail {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.charts-tab h2 {
  font-size: 1.5rem;
  color: #1e293b;
  margin-bottom: 1.5rem;
  text-align: center;
}

.chart-type-selector {
  display: flex;
  justify-content: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.chart-type-button {
  padding: 0.75rem 1.25rem;
  background-color: #e2e8f0;
  border: none;
  border-radius: 0.25rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
}

.chart-type-button:hover {
  background-color: #cbd5e1;
}

.chart-type-button.active {
  background-color: #3b82f6;
  color: white;
}

.chart-container {
  margin-bottom: 2rem;
}

.line-chart {
  background-color: #f8fafc;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.chart-placeholder {
  text-align: center;
}

.chart-placeholder h3 {
  font-size: 1.25rem;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.chart-placeholder p {
  color: #64748b;
  margin-bottom: 1.5rem;
}

.chart-data {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.chart-legend {
  display: flex;
  justify-content: space-between;
  width: 100%;
  margin-bottom: 0.5rem;
  overflow-x: auto;
  padding-bottom: 0.5rem;
}

.legend-item {
  font-size: 0.75rem;
  color: #64748b;
  text-align: center;
  flex: 1;
}

.chart-values {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  width: 100%;
  height: 200px;
}

.chart-bar {
  flex: 1;
  margin: 0 2px;
  position: relative;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  border-radius: 4px 4px 0 0;
  transition: height 0.3s;
}

.chart-value {
  position: absolute;
  top: -20px;
  font-size: 0.75rem;
  font-weight: 500;
  color: #1e293b;
}

.combined-chart-data {
  display: flex;
  flex-direction: column;
  margin-top: 1rem;
}

.combined-chart-values {
  display: flex;
  position: relative;
  min-height: 200px;
}

.temperature-values,
.humidity-values {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  width: 100%;
  position: absolute;
  bottom: 0;
}

.humidity-values {
  opacity: 0.7;
}

.temperature-bar,
.humidity-bar {
  flex: 1;
  margin: 0 2px;
  position: relative;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  border-radius: 4px 4px 0 0;
  transition: height 0.3s;
}

.temperature-bar {
  z-index: 2;
}

.humidity-bar {
  z-index: 1;
}

.wind-chart {
  background-color: #f8fafc;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  text-align: center;
  margin-top: 2rem;
}

.wind-chart h3 {
  font-size: 1.25rem;
  color: #1e293b;
  margin-bottom: 1.5rem;
}

.wind-compass {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.compass-circle {
  position: relative;
  width: 150px;
  height: 150px;
  border-radius: 50%;
  border: 2px solid #cbd5e1;
  margin-bottom: 1rem;
}

.compass-marker {
  position: absolute;
  font-weight: 600;
  color: #475569;
}

.compass-marker.n {
  top: 5px;
  left: 50%;
  transform: translateX(-50%);
}

.compass-marker.e {
  right: 5px;
  top: 50%;
  transform: translateY(-50%);
}

.compass-marker.s {
  bottom: 5px;
  left: 50%;
  transform: translateX(-50%);
}

.compass-marker.w {
  left: 5px;
  top: 50%;
  transform: translateY(-50%);
}

.wind-arrow {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  border-left: 8px solid transparent;
  border-right: 8px solid transparent;
  border-bottom: 60px solid #3b82f6;
  transform-origin: 50% 0;
  transform: translate(-50%, -50%);
  transition: transform 0.3s;
}

.wind-speed {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1e293b;
}
</style>