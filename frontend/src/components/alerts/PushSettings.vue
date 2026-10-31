<script setup>
// Włączanie / wyłączanie powiadomień push na tym urządzeniu i powiadomienie testowe.
import { onMounted, computed } from "vue";
import { usePush } from "@/composables/usePush";

const push = usePush();

const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent || "");
const standalone =
  window.matchMedia?.("(display-mode: standalone)").matches || window.navigator.standalone === true;

const status = computed(() => {
  if (!push.supported) return { text: "Nieobsługiwane", cls: "is-off" };
  if (push.denied.value) return { text: "Zablokowane", cls: "is-blocked" };
  if (push.subscribed.value) return { text: "Włączone", cls: "is-on" };
  return { text: "Wyłączone", cls: "is-off" };
});

onMounted(() => push.refresh());
</script>

<template>
  <section class="push-settings">
    <div class="push-header">
      <h2 class="push-title">Powiadomienia push na tym urządzeniu</h2>
      <span class="push-status" :class="status.cls">{{ status.text }}</span>
    </div>

    <p v-if="!push.supported" class="ui-hint">
      Ta przeglądarka nie obsługuje powiadomień push.
      <template v-if="isIos && !standalone">
        Na iPhonie i iPadzie dodaj aplikację do ekranu początkowego (Udostępnij → „Do ekranu
        początkowego”) i otwórz ją stamtąd.
      </template>
      Alerty nadal możesz otrzymywać e-mailem.
    </p>

    <template v-else>
      <p v-if="push.denied.value" class="ui-hint">
        Powiadomienia dla tej strony są zablokowane w ustawieniach przeglądarki. Zmień uprawnienie
        (ikona kłódki obok adresu strony), a następnie odśwież stronę.
      </p>
      <p v-else class="ui-hint">
        Alerty z kanałem „Push” będą wyświetlane jako powiadomienia systemowe – także gdy strona
        jest zamknięta.
      </p>

      <div class="push-actions">
        <button
          v-if="!push.subscribed.value"
          type="button"
          class="ui-btn ui-btn--sm"
          :disabled="push.busy.value || push.denied.value"
          @click="push.enable()"
        >
          {{ push.busy.value ? "Włączanie..." : "Włącz powiadomienia" }}
        </button>
        <template v-else>
          <button type="button" class="ui-btn ui-btn--sm" :disabled="push.busy.value" @click="push.sendTest()">
            Wyślij powiadomienie testowe
          </button>
          <button
            type="button"
            class="ui-btn ui-btn--sm ui-btn--secondary"
            :disabled="push.busy.value"
            @click="push.disable()"
          >
            Wyłącz
          </button>
        </template>
      </div>
    </template>

    <div v-if="push.error.value" class="ui-form-error push-feedback">{{ push.error.value }}</div>
    <div v-else-if="push.message.value" class="ui-form-success push-feedback">{{ push.message.value }}</div>
  </section>
</template>

<style scoped>
.push-settings {
  padding: 1rem;
  background-color: #effbff;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.push-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.25rem;
}

.push-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e40af;
  margin: 0;
}

.push-status {
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  white-space: nowrap;
}

.push-status.is-on { background-color: #dcfce7; color: #15803d; }
.push-status.is-off { background-color: #f3f4f6; color: #4b5563; }
.push-status.is-blocked { background-color: #fee2e2; color: #b91c1c; }

.push-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 0.75rem;
}

.push-feedback {
  margin-top: 0.75rem;
}
</style>
