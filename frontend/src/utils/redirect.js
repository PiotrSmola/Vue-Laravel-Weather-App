// Bezpieczny adres powrotu po zalogowaniu (?redirect=) – tylko ścieżki wewnętrzne
export function safeRedirect(value, fallback = "/") {
  const target = Array.isArray(value) ? value[0] : value;
  if (typeof target === "string" && target.startsWith("/") && !target.startsWith("//")) {
    return target;
  }
  return fallback;
}
