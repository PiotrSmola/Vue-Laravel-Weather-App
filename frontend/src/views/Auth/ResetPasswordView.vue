<script setup>
import { computed, reactive, ref } from "vue";
import { useRoute } from "vue-router";
import { accountApi } from "@/api/account";

const route = useRoute();

const token = computed(() => String(route.query.token || ""));
const form = reactive({
  email: String(route.query.email || ""),
  password: "",
  password_confirmation: "",
});

const loading = ref(false);
const done = ref(null);
const errors = ref(null);
const formError = ref(null);

const fieldError = (field) => errors.value?.fieldError(field) || null;

const submit = async () => {
  loading.value = true;
  errors.value = null;
  formError.value = null;

  try {
    const data = await accountApi.resetPassword({ token: token.value, ...form });
    done.value = data?.message || "Hasło zostało zmienione.";
  } catch (error) {
    if (error.errors) {
      errors.value = error;
      // Błąd tokenu zwracany jest przy polu e-mail – pokazujemy go też ogólnie
      if (!fieldError("password") && !fieldError("password_confirmation")) {
        formError.value = fieldError("email") || error.message;
      }
    } else {
      formError.value = error.message || "Nie udało się zmienić hasła.";
    }
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <main>
    <div class="ui-card ui-card--narrow">
      <h1 class="ui-title">Ustaw nowe hasło</h1>

      <div v-if="!token" class="ui-form-error">
        Link jest niekompletny. Poproś o nowy link do resetowania hasła.
      </div>

      <div v-else-if="done" class="reset-done">
        <div class="ui-form-success">{{ done }}</div>
        <RouterLink :to="{ name: 'login' }" class="ui-btn ui-btn--block">Zaloguj się</RouterLink>
      </div>

      <form v-else @submit.prevent="submit" class="ui-form">
        <div class="ui-form-group">
          <label for="email" class="ui-label">Adres e-mail</label>
          <input id="email" v-model="form.email" type="email" class="ui-input" autocomplete="email" required />
        </div>

        <div class="ui-form-group">
          <label for="password" class="ui-label">Nowe hasło</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            class="ui-input"
            placeholder="Co najmniej 8 znaków"
            autocomplete="new-password"
            required
          />
          <p v-if="fieldError('password')" class="ui-error-message">{{ fieldError("password") }}</p>
        </div>

        <div class="ui-form-group">
          <label for="password_confirmation" class="ui-label">Powtórz nowe hasło</label>
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            class="ui-input"
            autocomplete="new-password"
            required
          />
          <p v-if="fieldError('password_confirmation')" class="ui-error-message">
            {{ fieldError("password_confirmation") }}
          </p>
        </div>

        <div v-if="formError" class="ui-form-error">{{ formError }}</div>

        <button type="submit" class="ui-btn" :disabled="loading">
          {{ loading ? "Zapisywanie..." : "Zmień hasło" }}
        </button>
      </form>

      <p class="ui-muted reset-footer">
        <RouterLink :to="{ name: 'forgot-password' }" class="ui-link">Wyślij nowy link</RouterLink>
        ·
        <RouterLink :to="{ name: 'login' }" class="ui-link">Logowanie</RouterLink>
      </p>
    </div>
  </main>
</template>

<style scoped>
.reset-done {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.reset-footer {
  margin-top: 1.5rem;
  text-align: center;
}
</style>
