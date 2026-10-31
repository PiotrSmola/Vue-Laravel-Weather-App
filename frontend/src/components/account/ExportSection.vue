<script setup>
import { ref } from "vue";
import { accountApi } from "@/api/account";

const loading = ref(false);
const error = ref(null);

const download = async () => {
  loading.value = true;
  error.value = null;
  try {
    const data = await accountApi.export();
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `konto-${data?.profile?.id ?? "dane"}-${new Date().toISOString().slice(0, 10)}.json`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (e) {
    error.value = e.message || "Nie udało się pobrać danych.";
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <section class="account-section">
    <h2 class="ui-subtitle">Eksport danych</h2>
    <p class="ui-muted export-text">
      Pobierz plik JSON z danymi konta: profilem, ustawieniami, ulubionymi miastami i listą sesji.
    </p>
    <div v-if="error" class="ui-form-error export-error">{{ error }}</div>
    <div class="ui-btn-row">
      <button type="button" class="ui-btn ui-btn--secondary" :disabled="loading" @click="download">
        {{ loading ? "Przygotowywanie..." : "Pobierz dane (JSON)" }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.export-text,
.export-error {
  margin-bottom: 1rem;
}
</style>
