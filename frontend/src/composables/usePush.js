// Powiadomienia Web Push na tym urządzeniu (opt-in na stronie Alerty).
//
//   const push = usePush();
//   await push.refresh();          // stan: supported, permission, subscribed
//   await push.enable();           // prośba o zgodę + subskrypcja + zapis na serwerze
//   await push.disable();
//   await push.sendTest();
//
// Wymaga aktywnego service workera (vite-plugin-pwa, src/sw.js).

import { ref, computed } from "vue";
import { api } from "@/api/client";

const SW_TIMEOUT_MS = 4000;

function isSupported() {
  return (
    typeof window !== "undefined" &&
    "serviceWorker" in navigator &&
    "PushManager" in window &&
    "Notification" in window
  );
}

// Klucz VAPID (base64url) -> Uint8Array dla pushManager.subscribe()
function urlBase64ToUint8Array(base64) {
  const padding = "=".repeat((4 - (base64.length % 4)) % 4);
  const raw = atob((base64 + padding).replace(/-/g, "+").replace(/_/g, "/"));
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

// Rejestracja service workera z limitem czasu (np. gdy SW jest wyłączony w trybie dev)
async function getRegistration() {
  const existing = await navigator.serviceWorker.getRegistration();
  if (existing?.active) return existing;
  return Promise.race([
    navigator.serviceWorker.ready,
    new Promise((resolve) => setTimeout(() => resolve(null), SW_TIMEOUT_MS)),
  ]);
}

function contentEncoding() {
  const list = window.PushManager?.supportedContentEncodings || [];
  return list.includes("aes128gcm") || list.length === 0 ? "aes128gcm" : list[0];
}

export function usePush() {
  const supported = isSupported();
  const permission = ref(supported ? Notification.permission : "unsupported");
  const subscribed = ref(false);
  const busy = ref(false);
  const error = ref(null);
  const message = ref(null);

  const denied = computed(() => permission.value === "denied");

  async function currentSubscription() {
    const registration = await getRegistration();
    if (!registration) return { registration: null, subscription: null };
    return { registration, subscription: await registration.pushManager.getSubscription() };
  }

  async function refresh() {
    if (!supported) return;
    permission.value = Notification.permission;
    try {
      const { subscription } = await currentSubscription();
      subscribed.value = !!subscription && permission.value === "granted";
      // Odnowienie zapisu na serwerze (np. po zalogowaniu na inne konto na tym urządzeniu)
      if (subscribed.value) await saveSubscription(subscription);
    } catch {
      subscribed.value = false;
    }
  }

  async function saveSubscription(subscription) {
    const json = subscription.toJSON();
    await api.post("/push/subscriptions", {
      endpoint: json.endpoint,
      keys: json.keys,
      content_encoding: contentEncoding(),
    });
  }

  async function run(fn) {
    busy.value = true;
    error.value = null;
    message.value = null;
    try {
      await fn();
    } catch (e) {
      error.value = e?.message || "Wystąpił nieoczekiwany błąd";
    } finally {
      busy.value = false;
    }
  }

  const enable = () =>
    run(async () => {
      if (!supported) throw new Error("Ta przeglądarka nie obsługuje powiadomień push.");

      permission.value = await Notification.requestPermission();
      if (permission.value !== "granted") {
        throw new Error(
          permission.value === "denied"
            ? "Powiadomienia są zablokowane w ustawieniach przeglądarki. Zezwól na nie dla tej strony i spróbuj ponownie."
            : "Nie udzielono zgody na powiadomienia."
        );
      }

      const registration = await getRegistration();
      if (!registration) {
        throw new Error("Service worker nie jest aktywny – odśwież stronę i spróbuj ponownie.");
      }

      const { public_key: publicKey } = await api.get("/push/public-key", { auth: false });

      let subscription = await registration.pushManager.getSubscription();
      if (!subscription) {
        subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(publicKey),
        });
      }

      await saveSubscription(subscription);
      subscribed.value = true;
      message.value = "Powiadomienia push są włączone na tym urządzeniu.";
    });

  const disable = () =>
    run(async () => {
      const { subscription } = await currentSubscription();
      if (subscription) {
        try {
          await api.request("DELETE", "/push/subscriptions", { endpoint: subscription.endpoint });
        } finally {
          await subscription.unsubscribe();
        }
      }
      subscribed.value = false;
      message.value = "Powiadomienia push zostały wyłączone na tym urządzeniu.";
    });

  const sendTest = () =>
    run(async () => {
      const data = await api.post("/push/test");
      message.value = data?.message || "Wysłano powiadomienie testowe.";
    });

  return { supported, permission, denied, subscribed, busy, error, message, refresh, enable, disable, sendTest };
}

export default usePush;
