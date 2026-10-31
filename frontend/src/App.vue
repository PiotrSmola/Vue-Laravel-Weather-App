<script setup>
import { RouterLink, RouterView, useRoute, useRouter } from "vue-router";
import { useAuthStore } from "./stores/auth";
import { ref, computed } from "vue";
import { visibleNavigation } from "./navigation";
import EmailVerificationBanner from "./components/EmailVerificationBanner.vue";
import OfflineBanner from "./components/OfflineBanner.vue";
import AlertsBell from "./components/alerts/AlertsBell.vue";

const authStore = useAuthStore();
const mobileMenuOpen = ref(false);

// Pozycje menu z rejestru src/navigation/*.js
const navItems = computed(() => visibleNavigation(!!authStore.user));
const desktopNavItems = computed(() => navItems.value.filter((item) => item.desktop !== false));

const route = useRoute();
const router = useRouter();

// Po wylogowaniu opuszczamy strony wymagające logowania
const handleLogout = async () => {
  await authStore.logout();
  if (route.matched.some((record) => record.meta.requiresAuth)) {
    router.push({ name: "home" });
  }
};

const toggleMobileMenu = () => {
  mobileMenuOpen.value = !mobileMenuOpen.value;
};

const closeMobileMenu = () => {
  mobileMenuOpen.value = false;
};
</script>

<template>
  <div class="app-container">
    <header class="app-header">
      <nav class="main-nav">
        <div class="nav-container">
          <div class="logo-container">
            <RouterLink :to="{ name: 'home' }" class="logo">
              <span class="logo-icon">☁️</span>
              <span class="logo-text">Poland&World Weather</span>
            </RouterLink>

            <AlertsBell mobile />
            
            <button 
              class="mobile-menu-btn" 
              @click="toggleMobileMenu"
              aria-label="Toggle menu"
            >
              <svg 
                v-if="!mobileMenuOpen" 
                xmlns="http://www.w3.org/2000/svg" 
                class="icon" 
                fill="none" 
                viewBox="0 0 24 24" 
                stroke="currentColor"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
              
              <svg 
                v-else 
                xmlns="http://www.w3.org/2000/svg" 
                class="icon" 
                fill="none" 
                viewBox="0 0 24 24" 
                stroke="currentColor"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          
          <div class="nav-links">
            <RouterLink
              v-for="item in desktopNavItems"
              :key="item.key"
              :to="item.to"
              class="nav-link"
            >
              {{ item.label }}
            </RouterLink>
            
            <div v-if="authStore.user" class="user-nav">
              <AlertsBell />
              <RouterLink :to="{ name: 'account' }" class="user-greeting">
                Witaj, {{ authStore.user.name }}
              </RouterLink>
              
              <form @submit.prevent="handleLogout">
                <button class="logout-btn">Wyloguj</button>
              </form>
            </div>
            
            <div v-else class="auth-nav">
              <RouterLink :to="{ name: 'register' }" class="auth-link">
                Zarejestruj
              </RouterLink>
              
              <RouterLink :to="{ name: 'login' }" class="auth-link login">
                Zaloguj
              </RouterLink>
            </div>
          </div>
        </div>
        
        <div 
          v-if="mobileMenuOpen" 
          class="mobile-nav"
          @click.self="closeMobileMenu"
        >
          <div class="mobile-nav-links">
            <RouterLink
              v-for="item in navItems"
              :key="item.key"
              :to="item.to"
              class="mobile-nav-link"
              @click="closeMobileMenu"
            >
              {{ item.label }}
            </RouterLink>
            
            <div v-if="authStore.user" class="mobile-user-nav">
              <RouterLink :to="{ name: 'account' }" class="mobile-user-greeting" @click="closeMobileMenu">
                Witaj, {{ authStore.user.name }}
              </RouterLink>
              
              <form @submit.prevent="handleLogout">
                <button class="mobile-logout-btn" @click="closeMobileMenu">
                  Logout
                </button>
              </form>
            </div>
            
            <div v-else class="mobile-auth-nav">
              <RouterLink 
                :to="{ name: 'register' }" 
                class="mobile-nav-link"
                @click="closeMobileMenu"
              >
                Register
              </RouterLink>
              
              <RouterLink 
                :to="{ name: 'login' }" 
                class="mobile-nav-link"
                @click="closeMobileMenu"
              >
                Login
              </RouterLink>
            </div>
          </div>
        </div>
      </nav>
    </header>
    <OfflineBanner />

    <EmailVerificationBanner />

    <RouterView />
    
    <footer class="app-footer">
      <div class="footer-content">
        <p class="copyright">
          &copy; {{ new Date().getFullYear() }} Poland&World Weather. All rights reserved
        </p>
        <p class="attribution">
          Dane pogodowe dostarczone przez 
          <a href="https://openweathermap.org" target="_blank" rel="noopener noreferrer">
            OpenWeatherMap
          </a>
        </p>
      </div>
    </footer>
  </div>
</template>

<style>
.app-container {
  @apply min-h-screen flex flex-col bg-gray-100;
}

.app-header {
  @apply bg-blue-700 text-white shadow-md;
}

.main-nav {
  @apply relative 2xl:max-w-none;
}

.nav-container {
  @apply container mx-auto px-2 py-2 flex items-center justify-between gap-8;
}

.logo-container {
  @apply flex items-center justify-between w-full 2xl:w-auto;
}

.logo {
  @apply flex items-center text-white no-underline;
}

.logo-icon {
  @apply text-2xl mr-2;
}

.logo-text {
  @apply text-2xl font-bold whitespace-nowrap;
}

.mobile-menu-btn {
  @apply 2xl:hidden text-white p-2;
}

.icon {
  @apply h-6 w-6;
}

.nav-links {
  @apply hidden 2xl:flex items-center space-x-2;
}

.nav-link {
  @apply text-white opacity-80 hover:opacity-100 text-lg py-2 whitespace-nowrap;
}

.user-nav {
  @apply flex items-center space-x-4;
}

.user-greeting {
  @apply text-white opacity-80 hover:opacity-100 whitespace-nowrap truncate max-w-[10rem];
}

.logout-btn {
  @apply bg-red-500 text-white py-2 px-5 rounded-md hover:bg-red-600 transition-colors;
}

.auth-nav {
  @apply flex items-center space-x-4;
}

.auth-link {
  @apply text-white opacity-80 hover:opacity-100 text-lg;
}

.auth-link.login {
  @apply bg-white text-blue-700 py-2 px-5 rounded-md hover:bg-gray-100 transition-colors;
}

.mobile-nav {
  @apply fixed inset-0 bg-blue-900 bg-opacity-95 z-50 flex items-center justify-center 2xl:hidden;
}

.mobile-nav-links {
  @apply flex flex-col items-center space-y-6 w-full;
}

.mobile-nav-link {
  @apply text-white text-xl font-medium py-2;
}

.mobile-user-nav {
  @apply flex flex-col items-center space-y-4 pt-6;
}

.mobile-user-greeting {
  @apply text-white opacity-80;
}

.mobile-logout-btn {
  @apply bg-red-500 text-white py-2 px-6 rounded-md hover:bg-red-600 transition-colors;
}

.mobile-auth-nav {
  @apply flex flex-col items-center space-y-4;
}

.app-footer {
  @apply bg-blue-800 text-white py-6 mt-auto;
}

.footer-content {
  @apply container mx-auto px-4 text-center;
}

.copyright {
  @apply opacity-80 mb-2;
}

.attribution {
  @apply text-sm opacity-60;
}

.attribution a {
  @apply underline hover:text-blue-200 transition-colors;
}
</style>
