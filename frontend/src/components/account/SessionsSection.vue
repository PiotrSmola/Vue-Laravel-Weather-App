<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { accountApi } from "@/api/account";
import { useAuthStore } from "@/stores/auth";
import { formatDateTime } from "@/utils/date";

const authStore = useAuthStore();
const router = useRouter();

const tokens = ref([]);
const loading = ref(true);
const error = ref(null);
const revoking = ref(null);
const loggingOutAll = ref(false);

const load = async () => {
  loading.value = true;
  error.value = null;
  try {
    tokens.value = await accountApi.tokens();
  } catch (e) {
    error.value = e.message || "Nie udało się wczytać listy sesji.";
  } finally {
    loading.value = false;
  }
};

const revoke = async (token) => {
  revoking.value = token.id;
  error.value = null;
  try {
    await accountApi.revokeToken(token.id);
    tokens.value = tokens.value.filter((t) => t.id !== token.id);
  } catch (e) {
    error.value = e.message || "Nie udało się zakończyć sesji.";
  } finally {
    revoking.value = null;
  }
};

const logoutAll = async () => {
  loggingOutAll.value = true;
  await authStore.logoutAll();
  loggingOutAll.value = false;
  router.push({ name: "login" });
};

defineExpose({ reload: load });

onMounted(load);
</script>

<template>
  <section class="account-section">
    <h2 class="ui-subtitle">Aktywne sesje</h2>
    <p class="ui-muted sessions-intro">Urządzenia, na których jesteś zalogowany.</p>

    <div v-if="loading" class="sessions-loading">
      <div class="ui-spinner"></div>
    </div>

    <div v-else-if="error" class="ui-form-error">{{ error }}</div>

    <ul v-else class="sessions-list">
      <li v-for="token in tokens" :key="token.id" class="session-item">
        <div class="session-info">
          <span class="session-name">
            {{ token.name }}
            <span v-if="token.current" class="session-current">to urządzenie</span>
          </span>
          <span class="ui-hint">
            Ostatnia aktywność: {{ formatDateTime(token.last_used_at || token.created_at) }}
            · zalogowano {{ formatDateTime(token.created_at) }}
          </span>
        </div>
        <button
          v-if="!token.current"
          type="button"
          class="ui-btn ui-btn--sm ui-btn--secondary"
          :disabled="revoking === token.id"
          @click="revoke(token)"
        >
          {{ revoking === token.id ? "Kończenie..." : "Wyloguj" }}
        </button>
      </li>
      <li v-if="tokens.length === 0" class="ui-muted">Brak aktywnych sesji.</li>
    </ul>

    <div class="ui-btn-row sessions-actions">
      <button type="button" class="ui-btn ui-btn--danger" :disabled="loggingOutAll" @click="logoutAll">
        {{ loggingOutAll ? "Wylogowywanie..." : "Wyloguj ze wszystkich urządzeń" }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.sessions-intro {
  margin-bottom: 1rem;
}

.sessions-loading {
  text-align: center;
}

.sessions-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.session-item {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
  padding: 0.75rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem;
}

.session-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.session-name {
  font-weight: 500;
  color: #1f2937;
  overflow-wrap: anywhere;
}

.session-current {
  margin-left: 0.5rem;
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
  background-color: #dbeafe;
  color: #1d4ed8;
  font-size: 0.75rem;
  font-weight: 600;
}

.sessions-actions {
  margin-top: 1rem;
}
</style>
