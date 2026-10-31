<script setup>
import { onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { accountApi } from "@/api/account";
import { useAuthStore } from "@/stores/auth";

const route = useRoute();
const authStore = useAuthStore();

// "loading" | "success" | "error"
const state = ref("loading");
const message = ref("");
const resending = ref(false);
const resendMessage = ref(null);

const verify = async () => {
  const { id, hash, expires, signature } = route.query;
  if (!id || !hash || !expires || !signature) {
    state.value = "error";
    message.value = "Link weryfikacyjny jest niekompletny.";
    return;
  }

  state.value = "loading";
  try {
    const data = await accountApi.verifyEmail({ id, hash, expires, signature });
    authStore.patchUser({ email_verified_at: data?.email_verified_at || new Date().toISOString() });
    state.value = "success";
    message.value = data?.message || "Adres e-mail został potwierdzony.";
  } catch (error) {
    state.value = "error";
    message.value = error.message || "Nie udało się potwierdzić adresu e-mail.";
  }
};

const resend = async () => {
  resending.value = true;
  resendMessage.value = null;
  try {
    const data = await accountApi.resendVerification();
    resendMessage.value = data?.message || "Wysłaliśmy nowy link.";
  } catch (error) {
    resendMessage.value = error.message || "Nie udało się wysłać linku.";
  } finally {
    resending.value = false;
  }
};

onMounted(verify);
</script>

<template>
  <main>
    <div class="ui-card ui-card--narrow verify-card">
      <h1 class="ui-title">Weryfikacja adresu e-mail</h1>

      <div v-if="state === 'loading'" class="verify-loading">
        <div class="ui-spinner"></div>
        <p class="ui-muted">Sprawdzanie linku...</p>
      </div>

      <template v-else-if="state === 'success'">
        <div class="ui-form-success">{{ message }}</div>
        <div class="verify-actions">
          <RouterLink :to="{ name: 'home' }" class="ui-btn">Przejdź do strony głównej</RouterLink>
          <RouterLink :to="{ name: 'account' }" class="ui-btn ui-btn--secondary">Moje konto</RouterLink>
        </div>
      </template>

      <template v-else>
        <div class="ui-form-error">{{ message }}</div>
        <p v-if="authStore.user && !authStore.user.email_verified_at" class="ui-muted verify-hint">
          Możesz wysłać nowy link na adres {{ authStore.user.email }}.
        </p>
        <div class="verify-actions">
          <button
            v-if="authStore.user && !authStore.user.email_verified_at"
            type="button"
            class="ui-btn"
            :disabled="resending"
            @click="resend"
          >
            {{ resending ? "Wysyłanie..." : "Wyślij nowy link" }}
          </button>
          <RouterLink :to="{ name: 'account' }" class="ui-btn ui-btn--secondary">Moje konto</RouterLink>
        </div>
        <p v-if="resendMessage" class="ui-muted verify-hint">{{ resendMessage }}</p>
      </template>
    </div>
  </main>
</template>

<style scoped>
.verify-card {
  text-align: center;
}

.verify-loading {
  padding: 1rem 0;
}

.verify-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.75rem;
  margin-top: 1.25rem;
}

.verify-hint {
  margin-top: 1rem;
}
</style>
