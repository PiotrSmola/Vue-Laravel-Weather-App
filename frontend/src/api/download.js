// Pobieranie plików z API (np. eksport CSV). Frontend uwierzytelnia się tokenem Bearer,
// więc zwykły link <a href> nie wystarczy – plik pobieramy przez fetch i zapisujemy jako blob.
//
//   import { downloadFile } from "@/api/download";
//   await downloadFile("/cities/759734/history/export", { params: { from, to, format: "csv" } });
//
// Nazwa pliku: z nagłówka Content-Disposition, a w razie jego braku – opcja `filename`.
// Błędy: rzucany jest ApiError (jak w api/client.js).

import { ApiError } from "@/api/client";
import { TOKEN_STORAGE_KEY } from "@/config";

function buildUrl(path, params) {
  const base = path.startsWith("/api/") ? path : `/api${path.startsWith("/") ? "" : "/"}${path}`;
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params || {})) {
    if (value !== undefined && value !== null && value !== "") search.append(key, value);
  }
  const qs = search.toString();
  return qs ? `${base}?${qs}` : base;
}

// filename*=UTF-8''... (RFC 5987) ma pierwszeństwo przed filename="..."
export function filenameFromDisposition(header) {
  if (!header) return null;
  const extended = /filename\*\s*=\s*(?:UTF-8'')?([^;]+)/i.exec(header);
  if (extended) {
    try {
      return decodeURIComponent(extended[1].trim().replace(/^"|"$/g, ""));
    } catch {
      /* zwykła nazwa poniżej */
    }
  }
  const plain = /filename\s*=\s*("?)([^";]+)\1/i.exec(header);
  return plain ? plain[2].trim() : null;
}

export async function downloadFile(path, { params, filename = "plik", signal } = {}) {
  const headers = { Accept: "text/csv, application/json" };
  try {
    const token = localStorage.getItem(TOKEN_STORAGE_KEY);
    if (token) headers.Authorization = `Bearer ${token}`;
  } catch {
    /* brak dostępu do localStorage */
  }

  const response = await fetch(buildUrl(path, params), { headers, signal });

  if (!response.ok) {
    let data = null;
    try {
      data = await response.json();
    } catch {
      /* odpowiedź nie jest JSON-em */
    }
    const message =
      data?.error || data?.message || `Nie udało się pobrać pliku (${response.status})`;
    throw new ApiError(message, { status: response.status, errors: data?.errors || null, data });
  }

  const blob = await response.blob();
  const name = filenameFromDisposition(response.headers.get("Content-Disposition")) || filename;

  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = name;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);

  return name;
}

export default downloadFile;
