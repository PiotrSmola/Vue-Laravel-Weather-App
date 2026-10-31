<script setup>
import { nextTick, ref } from "vue";
import { useRouter } from "vue-router";
import { accountApi } from "@/api/account";
import { useAuthStore } from "@/stores/auth";

const authStore = useAuthStore();
const router = useRouter();

const open = ref(false);
const password = ref("");
const deleting = ref(false);
const error = ref(null);
const passwordInput = ref(null);

const openModal = async () => {
  password.value = "";
  error.value = null;
  open.value = true;
  await nextTick();
  passwordInput.value?.focus();
};

const closeModal = () => {
  if (!deleting.value) open.value = false;
};

const confirmDelete = async () => {
  deleting.value = true;
  error.value = null;
  try {
    await accountApi.destroy(password.value);
    open.value = false;
    authStore.clearAuth();
    router.push({ name: "home" });
  } catch (e) {
    error.value = e.fieldError?.("password") || e.message || "Nie udało się usunąć konta.";
  } finally {
    deleting.value = false;
  }
};
</script>

<template>
  <section class="account-section">
    <h2 class="ui-subtitle">Usuń konto</h2>
    <p class="ui-muted delete-text">
      Usunięcie konta jest nieodwracalne. Stracisz ulubione miasta, ustawienia i wszystkie dane powiązane z kontem.
    </p>
    <div class="ui-btn-row">
      <button type="button" class="ui-btn ui-btn--danger" @click="openModal">Usuń konto</button>
    </div>

    <div v-if="open" class="ui-modal-overlay" @click.self="closeModal">
      <div class="ui-modal" role="dialog" aria-modal="true" aria-labelledby="delete-account-title">
        <h3 id="delete-account-title" class="ui-modal-title">Usunąć konto?</h3>
        <p class="ui-modal-text">Tej operacji nie można cofnąć. Wpisz hasło, aby potwierdzić.</p>

        <form @submit.prevent="confirmDelete" class="ui-form">
          <div class="ui-form-group">
            <label for="delete-password" class="ui-label">Hasło</label>
            <input
              id="delete-password"
              ref="passwordInput"
              v-model="password"
              type="password"
              class="ui-input"
              autocomplete="current-password"
              required
            />
            <p v-if="error" class="ui-error-message">{{ error }}</p>
          </div>

          <div class="ui-btn-row">
            <button type="button" class="ui-btn ui-btn--secondary" :disabled="deleting" @click="closeModal">
              Anuluj
            </button>
            <button type="submit" class="ui-btn ui-btn--danger" :disabled="deleting || !password">
              {{ deleting ? "Usuwanie..." : "Usuń konto" }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>
</template>

<style scoped>
.delete-text {
  margin-bottom: 1rem;
}
</style>
