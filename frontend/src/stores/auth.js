import { defineStore } from "pinia";

export const useAuthStore = defineStore("authStore", {
  state: () => {
    return {
      user: null,
      token: localStorage.getItem("token") || null,
      loading: false,
      error: null,
    };
  },
  getters: {
    isAuthenticated: (state) => !!state.token,
  },
  actions: {
    async initialize() {
      if (this.token) {
        await this.getUser();
      }
    },
    
    async login(email, password) {
      this.loading = true;
      this.error = null;

      try {
        const response = await fetch("/api/login", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ email, password }),
        });

        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.message || "Nie udało się zalogować");
        }

        this.token = data.token;
        localStorage.setItem("token", data.token);
        this.user = data.user;

        return true;
      } catch (error) {
        console.error("Login error:", error);
        this.error = error.message;
        return false;
      } finally {
        this.loading = false;
      }
    },

    async register(name, email, password, password_confirmation) {
      this.loading = true;
      this.error = null;

      try {
        const response = await fetch("/api/register", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ name, email, password, password_confirmation }),
        });

        const data = await response.json();

        if (!response.ok) {
          if (data.errors) {
            const errors = Object.values(data.errors).flat();
            throw new Error(errors.join("\n"));
          }
          throw new Error(data.message || "Nie udało się zarejestrować");
        }

        this.token = data.token;
        localStorage.setItem("token", data.token);
        this.user = data.user;

        return true;
      } catch (error) {
        console.error("Register error:", error);
        this.error = error.message;
        return false;
      } finally {
        this.loading = false;
      }
    },

    async logout() {
      this.loading = true;
      this.error = null;

      try {
        if (this.token) {
          const response = await fetch("/api/logout", {
            method: "POST",
            headers: {
              Authorization: `Bearer ${this.token}`,
              "Content-Type": "application/json",
            },
          });

          if (!response.ok) {
            const data = await response.json();
            console.error("Logout error:", data);
          }
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
      this.user = null;
      this.token = null;
      localStorage.removeItem("token");
    },

    async getUser() {
      if (!this.token) return null;

      try {
        const response = await fetch("/api/user", {
          headers: {
            Authorization: `Bearer ${this.token}`,
          },
        });

        if (!response.ok) {
          if (response.status === 401) {
            this.clearAuth();
            return null;
          }
          throw new Error("Nie udało się pobrać danych użytkownika");
        }

        const data = await response.json();
        this.user = data;
        return data;
      } catch (error) {
        console.error("Get user error:", error);
        return null;
      }
    },
  },
});