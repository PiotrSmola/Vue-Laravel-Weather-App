<script setup>
// Podpowiedzi + (opcjonalnie) dane One Call 3.0 dla wyświetlanego miasta.
// One Call jest pobierane raz na miasto (src/api/onecall.js), tylko gdy backend ma włączoną funkcję.
import { ref, computed, watch } from 'vue';
import SuggestionsPanel from '@/components/SuggestionsPanel.vue';
import OneCallPanel from '@/components/OneCallPanel.vue';
import { fetchOneCall } from '@/api/onecall';
import { useSettingsStore } from '@/stores/settings';

const props = defineProps({
  current: { type: Object, default: null },
  forecast: { type: Object, default: null },
  // ID OpenWeather – domyślnie z aktualnej pogody
  cityId: { type: [Number, String], default: null },
});

const settings = useSettingsStore();
const oneCall = ref(null);

const id = computed(() => Number(props.cityId || props.current?.id) || null);

watch(
  () => [id.value, settings.lang],
  async ([cityId, lang]) => {
    oneCall.value = null;
    if (!cityId) return;
    const data = await fetchOneCall(cityId, lang);
    // Odpowiedź dla miasta, które jest nadal wyświetlane
    if (cityId === id.value) oneCall.value = data;
  },
  { immediate: true }
);

const uvi = computed(() => (typeof oneCall.value?.uvi === 'number' ? oneCall.value.uvi : null));
</script>

<template>
  <div class="weather-insights">
    <SuggestionsPanel :current="current" :forecast="forecast" :uvi="uvi" />
    <OneCallPanel :data="oneCall" />
  </div>
</template>
