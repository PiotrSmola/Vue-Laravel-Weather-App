<script setup>
// Pasek informujący o braku połączenia. Dane pogodowe pochodzą wtedy z cache service workera.
import { ref, onMounted, onBeforeUnmount } from "vue";

const offline = ref(typeof navigator !== "undefined" && navigator.onLine === false);

const update = () => {
  offline.value = navigator.onLine === false;
};

onMounted(() => {
  window.addEventListener("online", update);
  window.addEventListener("offline", update);
});

onBeforeUnmount(() => {
  window.removeEventListener("online", update);
  window.removeEventListener("offline", update);
});
</script>

<template>
  <div v-if="offline" class="offline-banner" role="status" aria-live="polite">
    <svg xmlns="http://www.w3.org/2000/svg" class="offline-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18M8.5 16.5a5 5 0 017 0M5 13a10 10 0 015.17-2.83M19 13a9.97 9.97 0 00-2.1-1.6M2 8.82a15 15 0 014.18-2.65M22 8.82A15 15 0 0010.7 5.1M12 20h.01" />
    </svg>
    <span>Jesteś offline — pokazujemy ostatnie zapisane dane</span>
  </div>
</template>

<style scoped>
.offline-banner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background-color: #fef3c7;
  color: #92400e;
  font-size: 0.875rem;
  font-weight: 500;
  text-align: center;
  border-bottom: 1px solid #fde68a;
}

.offline-icon {
  width: 1.1rem;
  height: 1.1rem;
  flex-shrink: 0;
}
</style>
