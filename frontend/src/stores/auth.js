import { defineStore } from "pinia";
import { api } from "@/api/client";
import { TOKEN_STORAGE_KEY } from "@/config";
import { useSettingsStore } from "./settings";

function readToken() {
  try {
    return localStorage.getItem(TOKEN_STORAGE_KEY) || null;
  } catch {
    return null;
  }
}

// Promise bieżącego pobierania użytkownika (współdzielony, aby /api/user szedł raz)
let userPromise = null;

export const useAuthStore = defineStore("authStore", {
  state: () => {
    return {
      user: null,
      token: readToken(),
      // true, gdy stan użytkownika został już ustalony (pobrany lub brak tokenu)
      userLoaded: false,
      loading: false,
      error: null,
    };
  },
  getters: {
    isAuthenticated: (state) => !!state.token,
  },
  actions: {
    // Pobiera użytkownika tylko raz (cache); kolejne wywołania zwracają zapamiętany stan
    async ensureUser() {
      if (this.userLoaded) return this.user;
      if (!this.token) {
        this.userLoaded = true;
        return null;
      }
      return this.getUser();
    },

    // Zachowane dla kompatybilności
    async initialize() {
      return this.ensureUser();
    },

    setSession(token, user) {
      this.token = token;
      try {
        localStorage.setItem(TOKEN_STORAGE_KEY, token);
      } catch {
        // ignorujemy
      }
      return this.setUser(user);
    },

    setUser(user) {
      this.user = user;
      this.userLoaded = true;
      // Ustawienia konta (z user.settings lub GET /api/user/settings)
      return user ? useSettingsStore().loadForUser(user) : Promise.resolve();
    },

    async login(email, password) {
      this.loading = true;
      this.error = null;

      try {
        const data = await api.post("/login", { email, password }, { auth: false });
        await this.setSession(data.token, data.user);
        return true;
      } catch (error) {
        console.error("Login error:", error);
        this.error = error.message || "Nie udało się zalogować";
        return false;
      } finally {
        this.loading = false;
      }
    },

    async register(name, email, password, password_confirmation) {
      this.loading = true;
      this.error = null;

      try {
        const data = await api.post(
          "/register",
          { name, email, password, password_confirmation },
          { auth: false }
        );
        await this.setSession(data.token, data.user);
        return true;
      } catch (error) {
        console.error("Register error:", error);
        this.error = error.errors
          ? error.messages.join("\n")
          : error.message || "Nie udało się zarejestrować";
        return false;
      } finally {
        this.loading = false;
      }
    },

    // Wylogowanie z bieżącego urządzenia
    async logout() {
      return this._logoutRequest("/logout");
    },

    // Wylogowanie ze wszystkich urządzeń (unieważnia wszystkie tokeny)
    async logoutAll() {
      return this._logoutRequest("/logout-all");
    },

    async _logoutRequest(path) {
      this.loading = true;
      this.error = null;

      try {
        if (this.token) {
          await api.post(path, null, { handleUnauthorized: false });
        }
        return true;
      } catch (error) {
        console.error("Logout error:", error);
        return false;
      } finally {
        this.clearAuth();
        this.loading = false;
      }
    },

    clearAuth() {
      const wasLoggedIn = !!this.user || !!this.token;
      this.user = null;
      this.token = null;
      this.userLoaded = true;
      userPromise = null;
      try {
        localStorage.removeItem(TOKEN_STORAGE_KEY);
      } catch {
        // ignorujemy
      }
      if (wasLoggedIn) useSettingsStore().resetToLocal();
    },

    // Wymusza pobranie danych użytkownika z /api/user
    async getUser() {
      if (!this.token) return null;
      if (userPromise) return userPromise;

      userPromise = (async () => {
        try {
          const data = await api.get("/user", { handleUnauthorized: false });
          await this.setUser(data);
          return data;
        } catch (error) {
          if (error.status === 401) {
            this.clearAuth();
          } else {
            console.error("Get user error:", error);
            // Nie blokujemy nawigacji przy błędzie sieci – spróbujemy ponownie później
          }
          return null;
        } finally {
          userPromise = null;
        }
      })();

      return userPromise;
    },
  },
});
