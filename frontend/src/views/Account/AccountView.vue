<script setup>
import { onMounted, ref } from "vue";
import { accountApi } from "@/api/account";
import ProfileSection from "@/components/account/ProfileSection.vue";
import PasswordSection from "@/components/account/PasswordSection.vue";
import SessionsSection from "@/components/account/SessionsSection.vue";
import ExportSection from "@/components/account/ExportSection.vue";
import DeleteAccountSection from "@/components/account/DeleteAccountSection.vue";

const account = ref(null);
const loading = ref(true);
const error = ref(null);
const sessions = ref(null);

const load = async () => {
  loading.value = true;
  error.value = null;
  try {
    account.value = await accountApi.get();
  } catch (e) {
    error.value = e.message || "Nie udało się wczytać danych konta.";
  } finally {
    loading.value = false;
  }
};

onMounted(load);
</script>

<template>
  <main>
    <div v-if="loading" class="ui-state">
      <div class="ui-spinner"></div>
      <p class="ui-muted">Wczytywanie konta...</p>
    </div>

    <div v-else-if="error" class="ui-state ui-state--error">
      <p>{{ error }}</p>
      <button type="button" class="ui-btn ui-btn--sm account-retry" @click="load">Spróbuj ponownie</button>
    </div>

    <div v-else class="ui-card ui-card--medium account-card">
      <h1 class="ui-title">Moje konto</h1>

      <ProfileSection :account="account" @updated="account = $event" />
      <PasswordSection @changed="sessions?.reload()" />
      <SessionsSection ref="sessions" />
      <ExportSection />
      <DeleteAccountSection />
    </div>
  </main>
</template>

<style scoped>
.account-card {
  padding-bottom: 1rem;
}

.account-card :deep(.account-section) {
  padding: 1.5rem 0;
  border-top: 1px solid #e5e7eb;
}

.account-card :deep(.account-section:first-of-type) {
  padding-top: 0;
  border-top: none;
}

.account-retry {
  margin-top: 1rem;
}

@media (max-width: 640px) {
  .account-card {
    margin: 1rem;
    padding: 1.25rem;
  }
}
</style>
