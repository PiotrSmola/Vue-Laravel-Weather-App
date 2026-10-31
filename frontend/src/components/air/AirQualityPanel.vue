<script setup>
// Sekcja jakości powietrza na stronie szczegółów pogody: aktualne dane, prognoza, historia.
import AirQualityCard from "./AirQualityCard.vue";
import AirQualityForecast from "./AirQualityForecast.vue";
import AirQualityHistory from "./AirQualityHistory.vue";

defineProps({
  // ID miasta w OpenWeather
  cityId: { type: [Number, String], required: true },
});
</script>

<template>
  <div class="air-quality-panel">
    <AirQualityCard :cityId="cityId" />
    <AirQualityForecast :cityId="cityId" />
    <AirQualityHistory :cityId="cityId" />
  </div>
</template>

<style scoped>
/* Szerokość i odstępy jak w WeatherDetails (.weather-details-page) */
.air-quality-panel {
  max-width: 1000px;
  margin: 0 auto;
  padding: 0 1rem 1rem;
}
</style>
