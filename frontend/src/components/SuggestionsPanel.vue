<script setup>
// Podpowiedzi pogodowe (reguły z src/utils/suggestions.js) dla wyświetlanego miasta.
import { computed } from 'vue';
import { buildSuggestions } from '@/utils/suggestions';
import { useUnits } from '@/composables/useUnits';

const props = defineProps({
  current: { type: Object, default: null },
  forecast: { type: Object, default: null },
  // Indeks UV (One Call) – bez danych podpowiedź o UV jest pomijana
  uvi: { type: Number, default: null },
});

const { formatTemp, formatWind } = useUnits();

const suggestions = computed(() =>
  buildSuggestions(props.current, props.forecast, {
    formatTemp: (c) => formatTemp(c),
    formatWind: (ms) => formatWind(ms),
    uvi: props.uvi,
  })
);
</script>

<template>
  <section v-if="suggestions.length" class="suggestions" aria-label="Podpowiedzi">
    <h3 class="suggestions-title">Podpowiedzi</h3>
    <ul class="suggestions-list">
      <li
        v-for="item in suggestions"
        :key="item.id"
        :class="['suggestion', `suggestion--${item.severity}`]"
      >
        <span class="suggestion-icon" aria-hidden="true">{{ item.icon }}</span>
        <span class="suggestion-text">{{ item.text }}</span>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.suggestions {
  padding: 1.5rem;
  background-color: #f3f4f6;
}

.suggestions-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 1rem;
  text-align: center;
}

.suggestions-list {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

@media (min-width: 768px) {
  .suggestions-list {
    grid-template-columns: 1fr 1fr;
  }
}

.suggestion {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background-color: white;
  border-radius: 8px;
  padding: 0.75rem 1rem;
  border-left: 4px solid #3b82f6;
  color: #1f2937;
  font-size: 0.95rem;
}

.suggestion--good { border-left-color: #10b981; }
.suggestion--info { border-left-color: #3b82f6; }
.suggestion--warning { border-left-color: #f59e0b; background-color: #fffbeb; }
.suggestion--danger { border-left-color: #ef4444; background-color: #fef2f2; }

.suggestion-icon {
  font-size: 1.5rem;
  line-height: 1;
  flex-shrink: 0;
}
</style>
