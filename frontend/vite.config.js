import { fileURLToPath, URL } from "node:url";

import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { VitePWA } from "vite-plugin-pwa";

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    // PWA: manifest, service worker (src/sw.js) z cache API i obsługą Web Push.
    // W trybie dev SW też działa (potrzebny do powiadomień push); VITE_PWA_DEV=false go wyłącza.
    VitePWA({
      strategies: "injectManifest",
      srcDir: "src",
      filename: "sw.js",
      registerType: "autoUpdate",
      injectRegister: "auto",
      includeAssets: ["pwa-icon.svg", "apple-touch-icon-180x180.png"],
      manifest: {
        name: "Poland&World Weather",
        short_name: "P&W Weather",
        description: "Aktualna pogoda, prognozy i alerty pogodowe dla miast w Polsce i na świecie.",
        lang: "pl",
        start_url: "/",
        scope: "/",
        display: "standalone",
        theme_color: "#1d4ed8",
        background_color: "#f3f4f6",
        icons: [
          { src: "pwa-192x192.png", sizes: "192x192", type: "image/png" },
          { src: "pwa-512x512.png", sizes: "512x512", type: "image/png" },
          { src: "pwa-maskable-192x192.png", sizes: "192x192", type: "image/png", purpose: "maskable" },
          { src: "pwa-maskable-512x512.png", sizes: "512x512", type: "image/png", purpose: "maskable" },
        ],
      },
      injectManifest: {
        globPatterns: ["**/*.{js,css,html,svg,png,ico,woff2}"],
      },
      devOptions: {
        enabled: process.env.VITE_PWA_DEV !== "false",
        type: "module",
        navigateFallback: "index.html",
      },
    }),
  ],
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
  },
  server: {
    watch: {
      // W Dockerze na Windowsie/macOS zdarzenia plików z bind mounta nie docierają
      usePolling: process.env.VITE_USE_POLLING === "true",
    },
    proxy: {
      "/api": {
        target: process.env.VITE_API_PROXY_TARGET || "http://127.0.0.1:8000",
        changeOrigin: true,
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
      },
    },
  },
});
