<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import { useAuthStore } from "@/stores/auth";
import { useWeatherStore } from "@/stores/weather";
import { useRouter } from 'vue-router';
import { FAVORITES_LIMIT } from '@/config';
import { useUnits } from '@/composables/useUnits';

const { formatTemp } = useUnits();

const props = defineProps({
  onCitySelect: {
    type: Function,
    default: null
  }
});

const emit = defineEmits(['selectCity']);

const weatherStore = useWeatherStore();
const authStore = useAuthStore();
const router = useRouter();
const loading = ref(false);
const error = ref(null);

const showRemoveModal = ref(false);
const cityToRemove = ref(null);
const isRemoving = ref(false);

const isAuthenticated = computed(() => {
  return !!authStore.user;
});

const hasFavorites = computed(() => {
  return weatherStore.userFavorites && weatherStore.userFavorites.length > 0;
});

const hasReachedFavoritesLimit = computed(() => {
  return weatherStore.userFavorites.length >= FAVORITES_LIMIT;
});

const showLimitWarning = ref(false);

const loadFavorites = async () => {
  if (!isAuthenticated.value) return;
  
  loading.value = true;
  error.value = null;
  
  try {
    await weatherStore.getUserFavorites();
    
    await weatherStore.getFeaturedCities();
  } catch (err) {
    console.error('Błąd podczas ładowania ulubionych miast:', err);
    error.value = 'Nie udało się załadować ulubionych miast';
  } finally {
    loading.value = false;
  }
};

const selectCity = (city) => {
  emit('selectCity', city);
};

const showRemoveConfirmation = (event, city) => {
  event.stopPropagation();
  cityToRemove.value = city;
  showRemoveModal.value = true;
};

const confirmRemoveCity = async () => {
  if (!cityToRemove.value) return;
  
  isRemoving.value = true;
  
  try {
    await weatherStore.removeCity(cityToRemove.value.openweather_id);
    // Po usunięciu miasta z ulubionych aktualizowanie obu list
    await loadFavorites();
    await weatherStore.getFeaturedCities();
    
    showRemoveModal.value = false;
    cityToRemove.value = null;
  } catch (err) {
    console.error('Błąd podczas usuwania miasta z ulubionych:', err);
    error.value = 'Nie udało się usunąć miasta z ulubionych';
  } finally {
    isRemoving.value = false;
  }
};

const cancelRemoveCity = () => {
  showRemoveModal.value = false;
  cityToRemove.value = null;
};

const closeLimitWarning = () => {
  showLimitWarning.value = false;
};

// --- Kolejność: przeciąganie (mysz) oraz przyciski w górę / w dół (dotyk, klawiatura) ---
const dragIndex = ref(null);
const overIndex = ref(null);
const reordering = ref(false);
const actionError = ref(null);

const moveFavorite = async (from, to) => {
  const list = weatherStore.userFavorites;
  if (from === to || from < 0 || to < 0 || from >= list.length || to >= list.length) return;

  const ids = list.map((c) => c.openweather_id);
  const [moved] = ids.splice(from, 1);
  ids.splice(to, 0, moved);

  reordering.value = true;
  actionError.value = null;
  const ok = await weatherStore.reorderFavorites(ids);
  reordering.value = false;
  if (!ok) actionError.value = 'Nie udało się zapisać kolejności ulubionych miast';
};

const onDragStart = (event, index) => {
  dragIndex.value = index;
  event.dataTransfer.effectAllowed = 'move';
  // Firefox wymaga ustawienia danych, żeby przeciąganie się rozpoczęło
  event.dataTransfer.setData('text/plain', String(index));
};

const onDragOver = (index) => {
  if (dragIndex.value !== null) overIndex.value = index;
};

const onDrop = (index) => {
  const from = dragIndex.value;
  onDragEnd();
  if (from !== null) moveFavorite(from, index);
};

const onDragEnd = () => {
  dragIndex.value = null;
  overIndex.value = null;
};

// --- Własna nazwa (alias), np. "Dom", "Działka" ---
const ALIAS_MAX = 50;
const editingId = ref(null);
const aliasDraft = ref('');
const savingAlias = ref(false);
const aliasInput = ref(null);

const startAliasEdit = async (city) => {
  editingId.value = city.openweather_id;
  aliasDraft.value = city.alias || '';
  actionError.value = null;
  await nextTick();
  const input = Array.isArray(aliasInput.value) ? aliasInput.value[0] : aliasInput.value;
  input?.focus();
};

const cancelAliasEdit = () => {
  editingId.value = null;
  aliasDraft.value = '';
};

const saveAlias = async (city) => {
  if (savingAlias.value) return;
  savingAlias.value = true;
  actionError.value = null;
  try {
    await weatherStore.setFavoriteAlias(city.openweather_id, aliasDraft.value.slice(0, ALIAS_MAX));
    cancelAliasEdit();
  } catch (err) {
    console.error('Błąd podczas zapisywania nazwy:', err);
    actionError.value = err.message || 'Nie udało się zapisać nazwy';
  } finally {
    savingAlias.value = false;
  }
};

watch(() => authStore.user, (newUser) => {
  if (newUser) {
    loadFavorites();
  } else {
    weatherStore.userFavorites = [];
  }
});

onMounted(() => {
  if (isAuthenticated.value) {
    loadFavorites();
  }
});
</script>

<template>
  <div class="user-favorites-component">
    <div v-if="isAuthenticated">
      <div v-if="loading" class="favorites-loading">
        <div class="loading-spinner"></div>
        <p>Ładowanie ulubionych miast...</p>
      </div>
      
      <div v-else-if="error" class="favorites-error">
        <p>{{ error }}</p>
        <button @click="loadFavorites" class="retry-button">Spróbuj ponownie</button>
      </div>
      
      <div v-else-if="hasFavorites" class="favorites-section">
        <div class="favorites-header">
          <h3 class="favorites-title">Twoje ulubione miasta ({{ weatherStore.userFavorites.length }}/{{ FAVORITES_LIMIT }})</h3>
          <div v-if="hasReachedFavoritesLimit" class="limit-badge">Limit osiągnięty</div>
        </div>
        
        <div class="favorites-list">
          <div
            v-for="(city, index) in weatherStore.userFavorites"
            :key="city.id"
            :class="['favorite-city-item', {
              'is-dragging': dragIndex === index,
              'is-drag-over': overIndex === index && dragIndex !== index
            }]"
            :draggable="editingId !== city.openweather_id && !reordering"
            @dragstart="onDragStart($event, index)"
            @dragover.prevent="onDragOver(index)"
            @drop.prevent="onDrop(index)"
            @dragend="onDragEnd"
            @click="editingId !== city.openweather_id && selectCity(city)"
          >
            <span class="drag-handle" title="Przeciągnij, aby zmienić kolejność" aria-hidden="true">⋮⋮</span>
            <div class="favorite-city-info">
              <form
                v-if="editingId === city.openweather_id"
                class="alias-form"
                @submit.prevent="saveAlias(city)"
                @click.stop
              >
                <input
                  ref="aliasInput"
                  v-model="aliasDraft"
                  type="text"
                  class="alias-input"
                  :maxlength="ALIAS_MAX"
                  :placeholder="`Własna nazwa, np. Dom (${city.name})`"
                  aria-label="Własna nazwa miasta"
                  @keydown.esc.prevent="cancelAliasEdit"
                />
                <button type="submit" class="alias-button alias-save" :disabled="savingAlias">
                  {{ savingAlias ? 'Zapisywanie...' : 'Zapisz' }}
                </button>
                <button type="button" class="alias-button" :disabled="savingAlias" @click="cancelAliasEdit">Anuluj</button>
              </form>
              <template v-else>
                <span class="favorite-city-name">{{ city.alias || city.name }}</span>
                <span class="favorite-city-country">{{ city.alias ? `${city.name}, ${city.country}` : city.country }}</span>
              </template>
              <div class="favorite-city-weather" v-if="city.current_temp">
                <span class="temp">{{ formatTemp(city.current_temp) }}</span>
                <span class="condition" v-if="city.current_condition">{{ city.current_condition }}</span>
                <img 
                  v-if="city.current_icon" 
                  :src="`https://openweathermap.org/img/wn/${city.current_icon}.png`" 
                  :alt="city.current_condition" 
                  class="weather-icon"
                />
              </div>
            </div>
            <div class="favorite-actions" @click.stop>
              <button
                type="button"
                class="order-button"
                title="Przenieś wyżej"
                aria-label="Przenieś wyżej"
                :disabled="index === 0 || reordering"
                @click="moveFavorite(index, index - 1)"
              >▲</button>
              <button
                type="button"
                class="order-button"
                title="Przenieś niżej"
                aria-label="Przenieś niżej"
                :disabled="index === weatherStore.userFavorites.length - 1 || reordering"
                @click="moveFavorite(index, index + 1)"
              >▼</button>
              <button
                type="button"
                class="order-button"
                title="Zmień nazwę"
                aria-label="Zmień nazwę"
                :disabled="editingId === city.openweather_id"
                @click="startAliasEdit(city)"
              >✎</button>
              <button
                @click="(event) => showRemoveConfirmation(event, city)"
                class="remove-favorite-button"
                title="Usuń z ulubionych"
                :disabled="isRemoving"
              >
                ✕
              </button>
            </div>
          </div>
        </div>

        <p v-if="actionError" class="favorites-action-error" role="alert">{{ actionError }}</p>

        <div class="favorites-info">
          <p class="archive-info">Dane archiwalne są zbierane co 30 minut dla ulubionych miast.</p>
          <p class="archive-info">Przeciągnij miasto lub użyj strzałek, aby zmienić kolejność. ✎ – własna nazwa (np. „Dom”).</p>
        </div>
      </div>
      
      <div v-else class="no-favorites">
        <p>Nie masz jeszcze ulubionych miast</p>
        <p class="hint">Wyszukaj miasto, aby dodać je do ulubionych i zbierać dane archiwalne</p>
      </div>
      
      <div v-if="showLimitWarning" class="limit-warning">
        <p>Osiągnięto limit {{ FAVORITES_LIMIT }} ulubionych miast. Usuń jedno z istniejących miast, aby dodać nowe.</p>
        <button @click="closeLimitWarning" class="close-button">×</button>
      </div>
    </div>
    
    <div v-else class="login-prompt">
      <p>Zaloguj się, aby zarządzać ulubionymi miastami i mieć dostęp do danych archiwalnych</p>
      <div class="login-buttons">
        <router-link to="/login" class="login-button">Zaloguj się</router-link>
        <router-link to="/register" class="register-button">Zarejestruj się</router-link>
      </div>
    </div>

    <div v-if="showRemoveModal" class="modal-overlay">
      <div class="modal-content">
        <h3 class="modal-title">Usuń z ulubionych</h3>
        <p class="modal-message">
          Czy na pewno chcesz usunąć <strong>{{ cityToRemove?.name }}</strong> z ulubionych miast?
        </p>
        <p class="modal-info">
          Po usunięciu miasta przestaną być zbierane dla niego dane archiwalne.
        </p>
        <div class="modal-buttons">
          <button 
            @click="cancelRemoveCity" 
            class="modal-button cancel-button"
            :disabled="isRemoving"
          >
            Anuluj
          </button>
          <button 
            @click="confirmRemoveCity" 
            class="modal-button remove-button"
            :disabled="isRemoving"
          >
            <span v-if="isRemoving" class="button-spinner"></span>
            {{ isRemoving ? 'Usuwanie...' : 'Usuń' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.user-favorites-component {
  margin-bottom: 1.5rem;
}

.favorites-section {
  padding: 1rem;
  background-color: #effbff;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  margin-bottom: 1.5rem;
}

.favorites-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.favorites-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e40af;
  padding-left: 0.5rem;
  margin: 0;
}

.limit-badge {
  background-color: #fee2e2;
  color: #b91c1c;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.favorites-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.favorite-city-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background-color: #dbeafe;
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  width: 100%;
  transition: background-color 0.2s;
}

.favorite-city-item:hover {
  background-color: #bfdbfe;
}

.favorite-city-info {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.favorite-city-item.is-dragging {
  opacity: 0.5;
}

.favorite-city-item.is-drag-over {
  box-shadow: inset 0 0 0 2px #3b82f6;
}

.drag-handle {
  color: #93c5fd;
  cursor: grab;
  font-size: 1rem;
  letter-spacing: -0.15em;
  margin-right: 0.75rem;
  user-select: none;
}

.favorite-actions {
  display: flex;
  align-items: center;
  gap: 0.125rem;
  margin-left: 0.5rem;
  flex-shrink: 0;
}

.order-button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 4px;
  background-color: transparent;
  color: #6b7280;
  cursor: pointer;
  font-size: 0.8rem;
  padding: 0;
  transition: color 0.2s, background-color 0.2s;
}

.order-button:hover:not(:disabled) {
  color: #1e40af;
  background-color: #eff6ff;
}

.order-button:disabled {
  opacity: 0.35;
  cursor: default;
}

.alias-form {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.alias-form .alias-input {
  flex: 1 1 160px;
  min-width: 0;
  padding: 0.375rem 0.5rem;
}

.alias-button {
  padding: 0.375rem 0.75rem;
  border: none;
  border-radius: 0.375rem;
  background-color: #f3f4f6;
  color: #4b5563;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
}

.alias-button:hover:not(:disabled) {
  background-color: #e5e7eb;
}

.alias-save {
  background-color: #3b82f6;
  color: white;
}

.alias-save:hover:not(:disabled) {
  background-color: #2563eb;
}

.alias-button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.favorites-action-error {
  margin: 0.75rem 0 0;
  padding: 0.5rem 0.75rem;
  background-color: #fee2e2;
  color: #b91c1c;
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.favorite-city-name {
  font-weight: 500;
  color: #1e40af;
  font-size: 1.1rem;
}

.favorite-city-country {
  font-size: 0.8rem;
  color: #4b5563;
}

.favorite-city-weather {
  display: flex;
  align-items: center;
  margin-top: 0.25rem;
}

.temp {
  font-weight: 600;
  color: #1f2937;
  margin-right: 0.5rem;
}

.condition {
  font-size: 0.8rem;
  color: #4b5563;
  text-transform: capitalize;
}

.weather-icon {
  width: 30px;
  height: 30px;
}

.remove-favorite-button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  background-color: transparent;
  color: #6b7280;
  cursor: pointer;
  transition: color 0.2s;
  font-size: 1rem;
  padding: 0;
}

.remove-favorite-button:hover:not(:disabled) {
  color: #ef4444;
}

.remove-favorite-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.favorites-info {
  margin-top: 0.75rem;
  text-align: center;
}

.archive-info {
  font-size: 0.8rem;
  color: #6b7280;
  margin: 0;
}

.no-favorites, .login-prompt {
  padding: 1.5rem;
  background-color: #f3f4f6;
  border-radius: 8px;
  text-align: center;
  color: #4b5563;
  margin-bottom: 1.5rem;
}

.hint {
  color: #6b7280;
  font-size: 0.875rem;
  margin-top: 0.5rem;
}

.login-buttons {
  display: flex;
  justify-content: center;
  gap: 1rem;
  margin-top: 1rem;
}

.login-button, .register-button {
  padding: 0.5rem 1.5rem;
  border-radius: 0.375rem;
  font-weight: 500;
  text-decoration: none;
  transition: background-color 0.2s;
}

.login-button {
  background-color: #3b82f6;
  color: white;
}

.login-button:hover {
  background-color: #2563eb;
}

.register-button {
  background-color: #e5e7eb;
  color: #1f2937;
}

.register-button:hover {
  background-color: #d1d5db;
}

.favorites-loading, .favorites-error {
  padding: 1.5rem;
  text-align: center;
  background-color: #f3f4f6;
  border-radius: 8px;
  margin-bottom: 1.5rem;
}

.loading-spinner {
  display: inline-block;
  width: 2rem;
  height: 2rem;
  border: 3px solid rgba(59, 130, 246, 0.2);
  border-radius: 50%;
  border-top-color: #3b82f6;
  animation: spin 1s linear infinite;
  margin-bottom: 0.5rem;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.favorites-error {
  color: #b91c1c;
}

.retry-button {
  padding: 0.5rem 1rem;
  background-color: #3b82f6;
  color: white;
  border: none;
  border-radius: 0.25rem;
  font-weight: 500;
  cursor: pointer;
  margin-top: 0.75rem;
}

.retry-button:hover {
  background-color: #2563eb;
}

.limit-warning {
  position: fixed;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
  background-color: #fee2e2;
  color: #b91c1c;
  padding: 1rem;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  z-index: 50;
  max-width: 90%;
  width: 400px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.close-button {
  background: none;
  border: none;
  font-size: 1.5rem;
  line-height: 1;
  color: #b91c1c;
  cursor: pointer;
  padding: 0.25rem;
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
  color: #b91c1c;
  margin-bottom: 1rem;
  text-align: center;
}

.modal-message {
  color: #374151;
  margin-bottom: 0.5rem;
  text-align: center;
}

.modal-info {
  color: #6b7280;
  font-size: 0.875rem;
  margin-bottom: 1.5rem;
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

@media (min-width: 640px) {
  .favorite-city-item {
    width: calc(50% - 0.375rem);
  }
}

@media (min-width: 1024px) {
  .favorite-city-item {
    width: calc(33.333% - 0.5rem);
  }
}
</style>