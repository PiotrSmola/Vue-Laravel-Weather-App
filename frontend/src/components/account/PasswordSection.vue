<script setup>
import { reactive, ref } from "vue";
import { accountApi } from "@/api/account";

const emit = defineEmits(["changed"]);

const empty = () => ({ current_password: "", password: "", password_confirmation: "" });
const form = reactive(empty());

const saving = ref(false);
const errors = ref(null);
const formError = ref(null);
const success = ref(null);

const fieldError = (field) => errors.value?.fieldError(field) || null;

const save = async () => {
  saving.value = true;
  errors.value = null;
  formError.value = null;
  success.value = null;

  try {
    const data = await accountApi.updatePassword({ ...form });
    Object.assign(form, empty());
    success.value = data?.message || "Hasło zostało zmienione.";
    emit("changed");
  } catch (error) {
    if (error.errors) errors.value = error;
    else formError.value = error.message || "Nie udało się zmienić hasła.";
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <section class="account-section">
    <h2 class="ui-subtitle">Zmiana hasła</h2>

    <form @submit.prevent="save" class="ui-form">
      <div class="ui-form-group">
        <label for="current_password" class="ui-label">Obecne hasło</label>
        <input
          id="current_password"
          v-model="form.current_password"
          type="password"
          class="ui-input"
          autocomplete="current-password"
          required
        />
        <p v-if="fieldError('current_password')" class="ui-error-message">{{ fieldError("current_password") }}</p>
      </div>

      <div class="ui-form-group">
        <label for="new_password" class="ui-label">Nowe hasło</label>
        <input
          id="new_password"
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
        <label for="new_password_confirmation" class="ui-label">Powtórz nowe hasło</label>
        <input
          id="new_password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          class="ui-input"
          autocomplete="new-password"
          required
        />
        <p class="ui-hint">Po zmianie hasła pozostałe urządzenia zostaną wylogowane.</p>
      </div>

      <div v-if="formError" class="ui-form-error">{{ formError }}</div>
      <div v-else-if="success" class="ui-form-success">{{ success }}</div>

      <div class="ui-btn-row">
        <button type="submit" class="ui-btn" :disabled="saving">
          {{ saving ? "Zapisywanie..." : "Zmień hasło" }}
        </button>
      </div>
    </form>
  </section>
</template>
