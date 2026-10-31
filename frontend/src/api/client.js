// Centralny klient HTTP dla API Laravela.
//
//   import { api, ApiError } from "@/api/client";
//   const cities = await api.get("/cities/search", { params: { q: "Kra" } });
//   await api.post("/cities", { city_id: 123 });
//   await api.put("/user/settings", { units: "imperial" });
//   await api.delete(`/cities/${id}`);
//
// Ścieżki są względne względem /api (można też podać pełne "/api/...").
// Opcje (ostatni argument): { params, headers, signal, timeout, auth }
//   - params  – obiekt query string (null/undefined są pomijane)
//   - timeout – ms, po których żądanie jest przerywane (AbortError)
//   - auth    – false = nie wysyłaj tokenu (domyślnie wysyłany, jeśli istnieje)
//   - handleUnauthorized – false = nie wywołuj globalnego handlera 401 (obsłuż samodzielnie)
// Błędy: rzucany jest ApiError { status, message, errors, data }.
// 401 dla żądania z tokenem wywołuje handler zarejestrowany przez setUnauthorizedHandler().

import { TOKEN_STORAGE_KEY } from "@/config";

const BASE_URL = "/api";

export class ApiError extends Error {
  constructor(message, { status = 0, errors = null, data = null } = {}) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    // Obiekt błędów walidacji Laravela: { pole: ["komunikat", ...] }
    this.errors = errors;
    this.data = data;
  }

  // Wszystkie komunikaty walidacji jako płaska lista
  get messages() {
    if (!this.errors) return this.message ? [this.message] : [];
    return Object.values(this.errors).flat();
  }

  // Pierwszy komunikat dla danego pola (lub null)
  fieldError(field) {
    const list = this.errors?.[field];
    return Array.isArray(list) ? list[0] : list || null;
  }
}

let unauthorizedHandler = null;
let tokenProvider = () => {
  try {
    return localStorage.getItem(TOKEN_STORAGE_KEY);
  } catch {
    return null;
  }
};

export function setUnauthorizedHandler(fn) {
  unauthorizedHandler = fn;
}

export function setTokenProvider(fn) {
  tokenProvider = fn;
}

function buildUrl(path, params) {
  let url = path.startsWith("http") || path.startsWith(BASE_URL + "/")
    ? path
    : `${BASE_URL}${path.startsWith("/") ? "" : "/"}${path}`;

  if (params) {
    const search = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
      if (value === undefined || value === null || value === "") continue;
      if (Array.isArray(value)) value.forEach((v) => search.append(`${key}[]`, v));
      else search.append(key, value);
    }
    const qs = search.toString();
    if (qs) url += (url.includes("?") ? "&" : "?") + qs;
  }
  return url;
}

function extractMessage(data, status) {
  if (data && typeof data === "object") {
    if (typeof data.message === "string" && data.message) return data.message;
    if (typeof data.error === "string" && data.error) return data.error;
    if (data.errors && typeof data.errors === "object") {
      const first = Object.values(data.errors).flat()[0];
      if (first) return first;
    }
  }
  if (typeof data === "string" && data && data.length < 300) return data;
  return `Błąd żądania (${status})`;
}

async function request(method, path, body, options = {}) {
  const { params, headers = {}, signal, timeout, auth = true, handleUnauthorized = true } = options;

  const finalHeaders = { Accept: "application/json", ...headers };
  const token = auth ? tokenProvider() : null;
  if (token) finalHeaders.Authorization = `Bearer ${token}`;

  const init = { method, headers: finalHeaders };
  if (body !== undefined && body !== null) {
    if (body instanceof FormData) {
      init.body = body;
    } else {
      finalHeaders["Content-Type"] = "application/json";
      init.body = JSON.stringify(body);
    }
  }

  let timeoutId = null;
  if (timeout || signal) {
    const controller = new AbortController();
    if (signal) {
      if (signal.aborted) controller.abort();
      else signal.addEventListener("abort", () => controller.abort(), { once: true });
    }
    if (timeout) timeoutId = setTimeout(() => controller.abort(), timeout);
    init.signal = controller.signal;
  }

  let response;
  try {
    response = await fetch(buildUrl(path, params), init);
  } finally {
    if (timeoutId) clearTimeout(timeoutId);
  }

  const text = response.status === 204 ? "" : await response.text();
  let data = null;
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = text;
    }
  }

  if (!response.ok) {
    const error = new ApiError(extractMessage(data, response.status), {
      status: response.status,
      errors: data && typeof data === "object" ? data.errors || null : null,
      data,
    });

    if (response.status === 401 && token && handleUnauthorized && unauthorizedHandler) {
      try {
        unauthorizedHandler(error);
      } catch (e) {
        console.error("Unauthorized handler error:", e);
      }
    }
    throw error;
  }

  return data;
}

export const api = {
  get: (path, options) => request("GET", path, undefined, options),
  post: (path, body, options) => request("POST", path, body, options),
  put: (path, body, options) => request("PUT", path, body, options),
  patch: (path, body, options) => request("PATCH", path, body, options),
  delete: (path, options) => request("DELETE", path, undefined, options),
  request,
};

export default api;
