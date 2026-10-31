<script setup>
import { reactive, ref, watch } from "vue";
import { accountApi } from "@/api/account";
import { useAuthStore } from "@/stores/auth";
import { formatDate } from "@/utils/date";

const props = defineProps({
  account: { type: Object, required: true },
});
const emit = defineEmits(["updated"]);

const authStore = useAuthStore();

const form = reactive({ name: props.account.name, email: props.account.email });
watch(
  () => props.account,
  (account) => Object.assign(form, { name: account.name, email: account.email })
);

const saving = ref(false);
const errors = ref(null);
const formError = ref(null);
const success = ref(null);

const resending = ref(false);
const resendMessage = ref(null);
const resendError = ref(false);

const fieldError = (field) => errors.value?.fieldError(field) || null;

const save = async () => {
  saving.value = true;
  errors.value = null;
  formError.value = null;
  success.value = null;

  try {
    const data = await accountApi.updateProfile({ ...form });
    authStore.patchUser({ name: data.name, email: data.email, email_verified_at: data.email_verified_at });
    success.value = data.message || "Profil został zapisany.";
    emit("updated", data);
  } catch (error) {
    if (error.errors) errors.value = error;
    else formError.value = error.message || "Nie udało się zapisać profilu.";
  } finally {
    saving.value = false;
  }
};

const resend = async () => {
  resending.value = true;
  resendMessage.value = null;
  resendError.value = false;
  try {
    const data = await accountApi.resendVerification();
    resendMessage.value = data?.message || "Wysłaliśmy nowy link weryfikacyjny.";
  } catch (error) {
    resendError.value = true;
    resendMessage.value = error.message || "Nie udało się wysłać linku.";
  } finally {
    resending.value = false;
  }
};
</script>

<template>
  <section class="account-section">
    <h2 class="ui-subtitle">Profil</h2>

    <div class="profile-status">
      <span v-if="account.email_verified_at" class="badge badge--ok">Adres e-mail potwierdzony</span>
      <span v-else class="badge badge--warn">Adres e-mail niepotwierdzony</span>
      <span class="ui-muted">
        Konto od {{ formatDate(account.created_at) }} · ulubione miasta: {{ account.stats?.favorites_count ?? 0 }}
      </span>
    </div>

    <div v-if="!account.email_verified_at" class="verify-box">
      <p class="ui-muted">
        Kliknij link wysłany na adres <strong>{{ account.email }}</strong>, aby potwierdzić konto.
      </p>
      <button type="button" class="ui-btn ui-btn--sm ui-btn--secondary" :disabled="resending" @click="resend">
        {{ resending ? "Wysyłanie..." : "Wyślij link ponownie" }}
      </button>
      <p v-if="resendMessage" :class="resendError ? 'ui-error-message' : 'ui-hint'">{{ resendMessage }}</p>
    </div>

    <form @submit.prevent="save" class="ui-form">
      <div class="ui-form-group">
        <label for="profile-name" class="ui-label">Nazwa</label>
        <input id="profile-name" v-model="form.name" type="text" class="ui-input" autocomplete="name" required />
        <p v-if="fieldError('name')" class="ui-error-message">{{ fieldError("name") }}</p>
      </div>

      <div class="ui-form-group">
        <label for="profile-email" class="ui-label">Adres e-mail</label>
        <input id="profile-email" v-model="form.email" type="email" class="ui-input" autocomplete="email" required />
        <p v-if="fieldError('email')" class="ui-error-message">{{ fieldError("email") }}</p>
        <p v-else class="ui-hint">Po zmianie adresu wyślemy na niego nowy link weryfikacyjny.</p>
      </div>

      <div v-if="formError" class="ui-form-error">{{ formError }}</div>
      <div v-else-if="success" class="ui-form-success">{{ success }}</div>

      <div class="ui-btn-row">
        <button type="submit" class="ui-btn" :disabled="saving">
          {{ saving ? "Zapisywanie..." : "Zapisz profil" }}
        </button>
      </div>
    </form>
  </section>
</template>

<style scoped>
.profile-status {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 1rem;
  margin-bottom: 1rem;
}

.badge {
  display: inline-block;
  padding: 0.25rem 0.625rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.badge--ok {
  background-color: #dcfce7;
  color: #15803d;
}

.badge--warn {
  background-color: #fef3c7;
  color: #b45309;
}

.verify-box {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.75rem;
  margin-bottom: 1rem;
  background-color: #fffbeb;
  border-radius: 0.375rem;
}
</style>
