<script setup>
import { RouterLink, RouterView } from "vue-router";
import { useAuthStore } from "./stores/auth";
import { ref } from "vue";

const authStore = useAuthStore();
const mobileMenuOpen = ref(false);

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
            <RouterLink :to="{ name: 'home' }" class="nav-link">
              Strona główna
            </RouterLink>
            
            <div v-if="authStore.user" class="user-nav">
              <span class="user-greeting">
                Witaj, {{ authStore.user.name }}
              </span>
              
              <form @submit.prevent="authStore.logout">
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
              :to="{ name: 'home' }" 
              class="mobile-nav-link"
              @click="closeMobileMenu"
            >
              Home
            </RouterLink>
            
            <div v-if="authStore.user" class="mobile-user-nav">
              <span class="mobile-user-greeting">
                Hello, {{ authStore.user.name }}
              </span>
              
              <form @submit.prevent="authStore.logout">
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
  @apply relative;
}

.nav-container {
  @apply container mx-auto px-2 py-2 flex items-center justify-between;
}

.logo-container {
  @apply flex items-center justify-between w-full md:w-auto;
}

.logo {
  @apply flex items-center text-white no-underline;
}

.logo-icon {
  @apply text-2xl mr-2;
}

.logo-text {
  @apply text-2xl font-bold ;
}

.mobile-menu-btn {
  @apply md:hidden text-white p-2;
}

.icon {
  @apply h-6 w-6;
}

.nav-links {
  @apply hidden md:flex items-center space-x-8;
}

.nav-link {
  @apply text-white opacity-80 hover:opacity-100 text-lg py-2;
}

.user-nav {
  @apply flex items-center space-x-4;
}

.user-greeting {
  @apply text-white opacity-80;
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
  @apply fixed inset-0 bg-blue-900 bg-opacity-95 z-50 flex items-center justify-center md:hidden;
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