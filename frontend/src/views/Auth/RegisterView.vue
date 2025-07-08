<script setup>
import { ref, reactive } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useRouter } from 'vue-router';

const authStore = useAuthStore();
const router = useRouter();

const formData = reactive({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
});

const loading = ref(false);
const formError = ref(null);

const register = async () => {
  loading.value = true;
  formError.value = null;
  
  try {
    const success = await authStore.register(
      formData.name, 
      formData.email, 
      formData.password,
      formData.password_confirmation
    );
    
    if (success) {
      router.push('/');
    }
  } catch (error) {
    formError.value = error.message || "Nie udało się zarejestrować";
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <main>
    <div class="register-container">
      <h1 class="register-title">Zarejestruj się</h1>

      <form @submit.prevent="register" class="register-form">
        <div class="form-group">
          <label for="name">Imię</label>
          <input 
            type="text" 
            id="name" 
            v-model="formData.name" 
            placeholder="Twoje imię" 
            required
          />
          <p v-if="authStore.error && authStore.error.includes('name')" class="error-message">
            {{ authStore.error }}
          </p>
        </div>

        <div class="form-group">
          <label for="email">Adres email</label>
          <input 
            type="email" 
            id="email" 
            v-model="formData.email" 
            placeholder="Twój email" 
            required
          />
          <p v-if="authStore.error && authStore.error.includes('email')" class="error-message">
            {{ authStore.error }}
          </p>
        </div>

        <div class="form-group">
          <label for="password">Hasło</label>
          <input 
            type="password" 
            id="password" 
            v-model="formData.password" 
            placeholder="Twoje hasło" 
            required
          />
          <p v-if="authStore.error && authStore.error.includes('hasło')" class="error-message">
            {{ authStore.error }}
          </p>
        </div>

        <div class="form-group">
          <label for="password_confirmation">Potwierdź hasło</label>
          <input 
            type="password" 
            id="password_confirmation" 
            v-model="formData.password_confirmation" 
            placeholder="Powtórz hasło" 
            required
          />
        </div>

        <div v-if="formError" class="form-error">
          {{ formError }}
        </div>

        <button 
          type="submit" 
          class="register-button" 
          :disabled="loading"
        >
          {{ loading ? 'Rejestracja...' : 'Zarejestruj się' }}
        </button>
      </form>

      <div class="login-link">
        Masz już konto? <router-link to="/login">Zaloguj się</router-link>
      </div>
    </div>
  </main>
</template>

<style scoped>
.register-container {
  max-width: 400px;
  margin: 2rem auto;
  padding: 2rem;
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.register-title {
  font-size: 1.75rem;
  color: #2563eb;
  margin-bottom: 1.5rem;
  text-align: center;
}

.register-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

label {
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 0.25rem;
  color: #4b5563;
}

input {
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 1rem;
  transition: border-color 0.2s;
}

input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
}

.error-message {
  color: #ef4444;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.form-error {
  background-color: #fee2e2;
  color: #b91c1c;
  padding: 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}

.register-button {
  padding: 0.75rem 1rem;
  background-color: #3b82f6;
  color: white;
  border: none;
  border-radius: 0.375rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
}

.register-button:hover:not(:disabled) {
  background-color: #2563eb;
}

.register-button:disabled {
  background-color: #93c5fd;
  cursor: not-allowed;
}

.login-link {
  margin-top: 1.5rem;
  text-align: center;
  font-size: 0.875rem;
  color: #6b7280;
}

.login-link a {
  color: #3b82f6;
  font-weight: 500;
  text-decoration: none;
}

.login-link a:hover {
  text-decoration: underline;
}
</style>