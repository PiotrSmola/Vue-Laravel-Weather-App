<script setup>
// Lista wyzwolonych alertów (powiadomienia w aplikacji) z oznaczaniem jako przeczytane.
import { onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAlertsStore } from "@/stores/alerts";

const alertsStore = useAlertsStore();
const router = useRouter();

const formatDate = (iso) => {
  if (!iso) return "";
  const date = new Date(iso);
  const today = new Date();
  const sameDay = date.toDateString() === today.toDateString();
  return sameDay
    ? `dziś, ${date.toLocaleTimeString("pl-PL", { hour: "2-digit", minute: "2-digit" })}`
    : date.toLocaleString("pl-PL", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" });
};

const open = async (event) => {
  await alertsStore.markRead(event);
  if (event.city?.id) router.push({ name: "weather-details", params: { id: event.city.id } });
};

const loadMore = () => alertsStore.fetchEvents(alertsStore.eventsMeta.current_page + 1);

onMounted(() => alertsStore.fetchEvents(1));
</script>

<template>
  <section id="powiadomienia" class="events-section">
    <div class="events-header">
      <h2 class="events-title">
        Powiadomienia
        <span v-if="alertsStore.unreadCount" class="unread-badge">{{ alertsStore.unreadCount }} nowe</span>
      </h2>
      <button
        v-if="alertsStore.unreadCount"
        type="button"
        class="ui-btn ui-btn--sm ui-btn--secondary"
        @click="alertsStore.markAllRead()"
      >
        Oznacz wszystkie jako przeczytane
      </button>
    </div>

    <div v-if="alertsStore.eventsError" class="ui-form-error">{{ alertsStore.eventsError }}</div>

    <p v-else-if="!alertsStore.eventsLoading && alertsStore.events.length === 0" class="ui-muted empty">
      Nie ma jeszcze żadnych powiadomień. Gdy któryś z Twoich alertów się uruchomi, zobaczysz go tutaj.
    </p>

    <ul v-if="alertsStore.events.length" class="events-list">
      <li
        v-for="event in alertsStore.events"
        :key="event.id"
        class="event-item"
        :class="{ 'is-unread': !event.read_at }"
      >
        <button type="button" class="event-main" @click="open(event)">
          <span class="event-dot" aria-hidden="true"></span>
          <span class="event-body">
            <span class="event-title">{{ event.title }}</span>
            <span class="event-message">{{ event.message }}</span>
            <span class="event-meta">{{ formatDate(event.triggered_at) }}</span>
          </span>
        </button>
        <button
          v-if="!event.read_at"
          type="button"
          class="event-read"
          title="Oznacz jako przeczytane"
          aria-label="Oznacz jako przeczytane"
          @click="alertsStore.markRead(event)"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </button>
      </li>
    </ul>

    <div v-if="alertsStore.eventsLoading" class="events-loading">
      <div class="ui-spinner"></div>
    </div>

    <div
      v-else-if="alertsStore.eventsMeta.current_page < alertsStore.eventsMeta.last_page"
      class="events-more"
    >
      <button type="button" class="ui-btn ui-btn--sm ui-btn--secondary" @click="loadMore">Pokaż starsze</button>
    </div>
  </section>
</template>

<style scoped>
.events-section {
  scroll-margin-top: 1rem;
}

.events-header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.events-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.unread-badge {
  background-color: #fee2e2;
  color: #b91c1c;
  padding: 0.15rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.empty {
  padding: 1rem 0;
}

.events-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.event-item {
  display: flex;
  align-items: stretch;
  background-color: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  transition: background-color 0.2s;
}

.event-item.is-unread {
  background-color: #eff6ff;
  border-color: #bfdbfe;
}

.event-main {
  flex: 1;
  display: flex;
  gap: 0.75rem;
  padding: 0.75rem;
  text-align: left;
  min-width: 0;
}

.event-main:hover .event-title {
  text-decoration: underline;
}

.event-dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  margin-top: 0.45rem;
  border-radius: 9999px;
  background-color: transparent;
}

.is-unread .event-dot {
  background-color: #3b82f6;
}

.event-body {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.event-title {
  font-weight: 600;
  color: #1e40af;
}

.event-message {
  color: #374151;
  font-size: 0.9rem;
}

.event-meta {
  color: #6b7280;
  font-size: 0.8rem;
}

.event-read {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  padding: 0 0.75rem;
  color: #3b82f6;
}

.event-read:hover {
  color: #1d4ed8;
}

.event-read svg {
  width: 1.25rem;
  height: 1.25rem;
}

.events-loading,
.events-more {
  display: flex;
  justify-content: center;
  margin-top: 1rem;
}
</style>
