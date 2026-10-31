import { defineStore } from "pinia";
import { api } from "@/api/client";

// Alerty pogodowe: licznik nieprzeczytanych powiadomień (dzwonek w nagłówku)
// oraz lista wyzwolonych alertów. Reguły obsługuje widok AlertsView.

export const useAlertsStore = defineStore("alertsStore", {
  state: () => ({
    unreadCount: 0,
    events: [],
    eventsMeta: { current_page: 1, last_page: 1, total: 0 },
    eventsLoading: false,
    eventsError: null,
  }),
  actions: {
    async fetchUnreadCount() {
      try {
        const data = await api.get("/alerts/events/unread-count", { handleUnauthorized: false });
        this.unreadCount = Number(data?.count) || 0;
      } catch {
        // brak sieci / wylogowanie – licznik pozostaje bez zmian
      }
      return this.unreadCount;
    },

    // page = 1 zastępuje listę, kolejne strony są doklejane ("Pokaż starsze")
    async fetchEvents(page = 1) {
      this.eventsLoading = true;
      this.eventsError = null;
      try {
        const data = await api.get("/alerts/events", { params: { page } });
        const items = data?.data || [];
        this.events = page === 1 ? items : [...this.events, ...items];
        this.eventsMeta = data?.meta || this.eventsMeta;
        this.unreadCount = Number(data?.meta?.unread_count) || 0;
      } catch (error) {
        this.eventsError = error.message || "Nie udało się pobrać powiadomień";
      } finally {
        this.eventsLoading = false;
      }
    },

    async markRead(event) {
      if (!event || event.read_at) return;
      try {
        const data = await api.post(`/alerts/events/${event.id}/read`);
        event.read_at = data?.data?.read_at || new Date().toISOString();
        this.unreadCount = Number(data?.unread_count) || 0;
      } catch {
        // ignorujemy – spróbujemy przy kolejnym kliknięciu
      }
    },

    async markAllRead() {
      try {
        await api.post("/alerts/events/read-all");
        const now = new Date().toISOString();
        this.events.forEach((e) => {
          if (!e.read_at) e.read_at = now;
        });
        this.unreadCount = 0;
      } catch (error) {
        this.eventsError = error.message || "Nie udało się oznaczyć powiadomień";
      }
    },

    reset() {
      this.unreadCount = 0;
      this.events = [];
      this.eventsMeta = { current_page: 1, last_page: 1, total: 0 };
      this.eventsError = null;
    },
  },
});

export default useAlertsStore;
