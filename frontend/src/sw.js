// Service worker aplikacji (vite-plugin-pwa, strategia injectManifest):
//  - precache powłoki aplikacji (tylko build produkcyjny),
//  - cache odpowiedzi API pogody i miast (NetworkFirst – offline pokazujemy ostatnie dane),
//  - powiadomienia Web Push z alertów pogodowych i otwieranie strony miasta po kliknięciu.

import { cleanupOutdatedCaches, createHandlerBoundToURL, precacheAndRoute } from "workbox-precaching";
import { NavigationRoute, registerRoute } from "workbox-routing";
import { NetworkFirst } from "workbox-strategies";
import { ExpirationPlugin } from "workbox-expiration";
import { CacheableResponsePlugin } from "workbox-cacheable-response";
import { clientsClaim } from "workbox-core";

self.skipWaiting();
clientsClaim();

// W trybie dev Vite serwuje moduły na żywo – precache tylko w buildzie
if (import.meta.env.PROD) {
  cleanupOutdatedCaches();
  precacheAndRoute(self.__WB_MANIFEST);
  registerRoute(
    new NavigationRoute(createHandlerBoundToURL("index.html"), { denylist: [/^\/api\//] })
  );
}

// Klucz cache zależny od tokenu – dane jednego konta nie trafią do innego na tym samym urządzeniu
function hash(text) {
  let h = 0x811c9dc5;
  for (let i = 0; i < text.length; i++) {
    h ^= text.charCodeAt(i);
    h = Math.imul(h, 0x01000193);
  }
  return (h >>> 0).toString(36);
}

const perUserCacheKey = {
  cacheKeyWillBeUsed: async ({ request }) => {
    const auth = request.headers.get("Authorization");
    if (!auth) return request.url;
    const url = new URL(request.url);
    url.searchParams.set("__u", hash(auth));
    return url.href;
  },
};

const apiCache = (cacheName, maxEntries) =>
  new NetworkFirst({
    cacheName,
    networkTimeoutSeconds: 4,
    plugins: [
      perUserCacheKey,
      new CacheableResponsePlugin({ statuses: [200] }),
      new ExpirationPlugin({ maxEntries, maxAgeSeconds: 7 * 24 * 60 * 60 }),
    ],
  });

registerRoute(
  ({ url, request }) =>
    request.method === "GET" && url.origin === self.location.origin && url.pathname.startsWith("/api/weather/"),
  apiCache("api-weather", 120)
);

registerRoute(
  ({ url, request }) =>
    request.method === "GET" && url.origin === self.location.origin && url.pathname.startsWith("/api/cities"),
  apiCache("api-cities", 60)
);

// --- Web Push ---

self.addEventListener("push", (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch {
    data = { body: event.data ? event.data.text() : "" };
  }

  const title = data.title || "Poland&World Weather";
  const options = {
    body: data.body || "",
    icon: data.icon || "/pwa-192x192.png",
    badge: data.badge || "/pwa-badge-96x96.png",
    tag: data.tag || undefined,
    renotify: !!data.tag,
    lang: "pl",
    data: { url: data.url || "/", eventId: data.event_id || null },
  };

  event.waitUntil(
    Promise.all([
      self.registration.showNotification(title, options),
      // Otwarte karty odświeżają licznik nieprzeczytanych alertów
      self.clients
        .matchAll({ type: "window", includeUncontrolled: true })
        .then((clients) => clients.forEach((client) => client.postMessage({ type: "alert-push", payload: data }))),
    ])
  );
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const target = new URL(event.notification.data?.url || "/", self.location.origin).href;

  event.waitUntil(
    (async () => {
      const clients = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
      const client = clients.find((c) => new URL(c.url).origin === self.location.origin);

      if (client) {
        try {
          await client.focus();
          if (client.url !== target && "navigate" in client) await client.navigate(target);
          return;
        } catch {
          // karta niekontrolowana przez SW – otwieramy nowe okno
        }
      }
      await self.clients.openWindow(target);
    })()
  );
});
