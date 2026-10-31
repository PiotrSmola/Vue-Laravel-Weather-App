<script setup>
import { ref } from "vue";
import { accountApi } from "@/api/account";

const email = ref("");
const loading = ref(false);
const sentMessage = ref(null);
const fieldError = ref(null);
const formError = ref(null);

const submit = async () => {
  loading.value = true;
  sentMessage.value = null;
  fieldError.value = null;
  formError.value = null;

  try {
    const data = await accountApi.forgotPassword(email.value);
    sentMessage.value = data?.message || "Jeśli konto istnieje, wysłaliśmy link do resetowania hasła.";
  } catch (error) {
    fieldError.value = error.fieldError?.("email") || null;
    if (!fieldError.value) formError.value = error.message || "Nie udało się wysłać linku.";
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <main>
    <div class="ui-card ui-card--narrow">
      <h1 class="ui-title">Nie pamiętasz hasła?</h1>

      <p class="ui-muted forgot-intro">
        Podaj adres e-mail powiązany z kontem. Wyślemy na niego link do ustawienia nowego hasła.
      </p>

      <div v-if="sentMessage" class="ui-form-success">
        {{ sentMessage }}
      </div>

      <form v-else @submit.prevent="submit" class="ui-form">
        <div class="ui-form-group">
          <label for="email" class="ui-label">Adres e-mail</label>
          <input
            id="email"
            v-model="email"
            type="email"
            class="ui-input"
            placeholder="Twój e-mail"
            autocomplete="email"
            required
          />
          <p v-if="fieldError" class="ui-error-message">{{ fieldError }}</p>
        </div>

        <div v-if="formError" class="ui-form-error">{{ formError }}</div>

        <button type="submit" class="ui-btn" :disabled="loading">
          {{ loading ? "Wysyłanie..." : "Wyślij link" }}
        </button>
      </form>

      <p class="ui-muted forgot-footer">
        <RouterLink :to="{ name: 'login' }" class="ui-link">Wróć do logowania</RouterLink>
      </p>
    </div>
  </main>
</template>

<style scoped>
.forgot-intro {
  margin-bottom: 1.25rem;
  text-align: center;
}

.forgot-footer {
  margin-top: 1.5rem;
  text-align: center;
}
</style>
