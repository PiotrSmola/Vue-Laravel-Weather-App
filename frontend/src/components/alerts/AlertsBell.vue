<script setup>
// Dzwonek w nagłówku z liczbą nieprzeczytanych alertów (tylko dla zalogowanych).
// Licznik odświeża się co 2 minuty, po powrocie do karty i po otrzymaniu powiadomienia push.
import { watch, onMounted, onBeforeUnmount } from "vue";
import { RouterLink } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useAlertsStore } from "@/stores/alerts";

const REFRESH_MS = 2 * 60 * 1000;

const authStore = useAuthStore();
const alertsStore = useAlertsStore();

const refresh = () => {
  if (authStore.user && document.visibilityState !== "hidden") alertsStore.fetchUnreadCount();
};

const onSwMessage = (event) => {
  if (event.data?.type === "alert-push") refresh();
};

// Dzwonek występuje w nagłówku dwukrotnie (desktop i mobile) – odświeżanie uruchamia tylko pierwsza instancja
const primary = (instances += 1) === 1;

if (primary) {
  watch(
    () => authStore.user?.id,
    (id) => {
      if (id) refresh();
      else alertsStore.reset();
    }
  );
}

onMounted(() => {
  if (!primary) return;
  refresh();
  timer = setInterval(refresh, REFRESH_MS);
  document.addEventListener("visibilitychange", refresh);
  navigator.serviceWorker?.addEventListener("message", onSwMessage);
});

onBeforeUnmount(() => {
  instances -= 1;
  if (!primary) return;
  clearInterval(timer);
  document.removeEventListener("visibilitychange", refresh);
  navigator.serviceWorker?.removeEventListener("message", onSwMessage);
});
</script>

<script>
// Wspólne dla wszystkich instancji komponentu
let instances = 0;
let timer = null;
</script>

<template>
  <RouterLink
    v-if="authStore.user"
    :to="{ name: 'alerts', hash: '#powiadomienia' }"
    class="alerts-bell"
    :title="alertsStore.unreadCount ? `Nieprzeczytane alerty: ${alertsStore.unreadCount}` : 'Alerty pogodowe'"
    :aria-label="alertsStore.unreadCount ? `Nieprzeczytane alerty: ${alertsStore.unreadCount}` : 'Alerty pogodowe'"
  >
    <svg xmlns="http://www.w3.org/2000/svg" class="alerts-bell-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.66V5a2 2 0 10-4 0v.34A6 6 0 006 11v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    </svg>
    <span v-if="alertsStore.unreadCount" class="alerts-bell-badge">
      {{ alertsStore.unreadCount > 99 ? "99+" : alertsStore.unreadCount }}
    </span>
  </RouterLink>
</template>

<style scoped>
.alerts-bell {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.4rem;
  color: white;
  opacity: 0.85;
  border-radius: 9999px;
  transition: opacity 0.2s, background-color 0.2s;
}

.alerts-bell:hover {
  opacity: 1;
  background-color: rgba(255, 255, 255, 0.1);
}

.alerts-bell-icon {
  width: 1.5rem;
  height: 1.5rem;
}

.alerts-bell-badge {
  position: absolute;
  top: -0.1rem;
  right: -0.15rem;
  min-width: 1.15rem;
  height: 1.15rem;
  padding: 0 0.3rem;
  border-radius: 9999px;
  background-color: #ef4444;
  color: white;
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1.15rem;
  text-align: center;
  box-shadow: 0 0 0 2px #1d4ed8;
}
</style>
