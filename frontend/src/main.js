import './assets/main.css'

import { createApp, markRaw } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { setUnauthorizedHandler } from './api/client'
import { useAuthStore } from './stores/auth'

const app = createApp(App)
const pinia = createPinia()

pinia.use(({store}) => {
    store.router = markRaw(router)
})

app.use(pinia)
app.use(router)

// Wygasła sesja (401 dla żądania z tokenem): czyścimy dane logowania i przechodzimy do logowania.
// Rejestrowane tutaj, aby klient API nie importował routera ani store'ów (brak cykli importów).
setUnauthorizedHandler(() => {
    const authStore = useAuthStore(pinia)
    authStore.clearAuth()

    const current = router.currentRoute.value
    if (current.name !== 'login') {
        router.push({ name: 'login', query: { redirect: current.fullPath } })
    }
})

app.mount('#app')
