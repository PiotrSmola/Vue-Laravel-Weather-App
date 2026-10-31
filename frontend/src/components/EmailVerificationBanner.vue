<script setup>
// Pasek przypominający o potwierdzeniu adresu e-mail. Nie blokuje aplikacji;
// można go ukryć do końca sesji przeglądarki (sessionStorage).
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import { accountApi } from "@/api/account";
import { useAuthStore } from "@/stores/auth";

const DISMISS_KEY = "email-verification-banner-dismissed";

const authStore = useAuthStore();
const route = useRoute();

function readDismissed() {
  try {
    return sessionStorage.getItem(DISMISS_KEY) === "1";
  } catch {
    return false;
  }
}

const dismissed = ref(readDismissed());
const sending = ref(false);
const message = ref(null);

const visible = computed(
  () =>
    !!authStore.user &&
    !authStore.user.email_verified_at &&
    !dismissed.value &&
    route.name !== "verify-email"
);

const dismiss = () => {
  dismissed.value = true;
  try {
    sessionStorage.setItem(DISMISS_KEY, "1");
  } catch {
    // ignorujemy
  }
};

const resend = async () => {
  sending.value = true;
  message.value = null;
  try {
    const data = await accountApi.resendVerification();
    message.value = data?.message || "Wysłaliśmy nowy link weryfikacyjny.";
  } catch (error) {
    message.value = error.message || "Nie udało się wysłać linku.";
  } finally {
    sending.value = false;
  }
};
</script>

<template>
  <div v-if="visible" class="verify-banner" role="status">
    <div class="verify-banner__content">
      <p class="verify-banner__text">
        <template v-if="message">{{ message }}</template>
        <template v-else>
          Potwierdź adres e-mail <strong>{{ authStore.user.email }}</strong> – kliknij link, który
          wysłaliśmy w wiadomości.
        </template>
      </p>
      <div class="verify-banner__actions">
        <button type="button" class="verify-banner__btn" :disabled="sending" @click="resend">
          {{ sending ? "Wysyłanie..." : "Wyślij ponownie" }}
        </button>
        <button type="button" class="verify-banner__close" aria-label="Ukryj" @click="dismiss">×</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.verify-banner {
  background-color: #fef3c7;
  color: #92400e;
  border-bottom: 1px solid #fde68a;
}

.verify-banner__content {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0.5rem 1rem;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
}

.verify-banner__text {
  font-size: 0.875rem;
  overflow-wrap: anywhere;
}

.verify-banner__actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.verify-banner__btn {
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  background-color: #f59e0b;
  color: white;
  font-size: 0.875rem;
  font-weight: 500;
  transition: background-color 0.2s;
}

.verify-banner__btn:hover:not(:disabled) {
  background-color: #d97706;
}

.verify-banner__btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.verify-banner__close {
  padding: 0 0.5rem;
  font-size: 1.25rem;
  line-height: 1;
  color: #92400e;
}
</style>
