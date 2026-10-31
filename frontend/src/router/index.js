import { createRouter, createWebHistory } from "vue-router";

import HomeView from "../views/HomeView.vue";
import RegisterView from "../views/Auth/RegisterView.vue";
import LoginView from "../views/Auth/LoginView.vue";
import WeatherDetailsView from "../views/WeatherDetailsView.vue";
import { useAuthStore } from "@/stores/auth";

// Trasy podstawowe. Nowe funkcje dodają własne pliki w ./modules/*.js
// (każdy: `export default [ { path, name, component, meta } ]`).
// meta.requiresAuth – wymaga zalogowania (przekierowanie do logowania z ?redirect=)
// meta.guest        – tylko dla niezalogowanych (zalogowany trafia na stronę główną)
const coreRoutes = [
  {
    path: "/",
    name: "home",
    component: HomeView,
  },
  {
    path: "/register",
    name: "register",
    component: RegisterView,
    meta: { guest: true },
  },
  {
    path: "/login",
    name: "login",
    component: LoginView,
    meta: { guest: true },
  },
  {
    path: "/weather/:id",
    name: "weather-details",
    component: WeatherDetailsView,
  },
];

const moduleFiles = import.meta.glob("./modules/*.js", { eager: true });
const moduleRoutes = Object.keys(moduleFiles)
  .sort()
  .flatMap((file) => moduleFiles[file].default || []);

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [...coreRoutes, ...moduleRoutes],
});

export { safeRedirect } from "@/utils/redirect";

router.beforeEach(async (to) => {
  const authStore = useAuthStore();
  // Użytkownik pobierany jest raz (przy pierwszej nawigacji) i zapamiętywany w store
  await authStore.ensureUser();

  const requiresAuth = to.matched.some((record) => record.meta.requiresAuth);
  const guestOnly = to.matched.some((record) => record.meta.guest);

  if (requiresAuth && !authStore.user) {
    return { name: "login", query: { redirect: to.fullPath } };
  }

  if (authStore.user && guestOnly) {
    return { name: "home" };
  }
});

export default router;
