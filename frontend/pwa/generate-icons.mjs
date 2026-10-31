// Generuje ikony PNG aplikacji (PWA) z plików SVG. Wymaga pakietu "sharp" (nie jest zależnością projektu):
//   cd /tmp && npm i sharp && node /app/pwa/generate-icons.mjs /app
// Wynik trafia do public/ i jest commitowany - generowanie potrzebne tylko po zmianie logo.
import { readFile } from "node:fs/promises";
import { join } from "node:path";
import { createRequire } from "node:module";

const root = process.argv[2] || process.cwd();
const require = createRequire(join(process.cwd(), "noop.js"));
const sharp = require("sharp");

const icons = [
  ["public/pwa-icon.svg", "public/pwa-192x192.png", 192],
  ["public/pwa-icon.svg", "public/pwa-512x512.png", 512],
  ["public/pwa-icon.svg", "public/apple-touch-icon-180x180.png", 180],
  ["pwa/icon-maskable.svg", "public/pwa-maskable-512x512.png", 512],
  ["pwa/icon-maskable.svg", "public/pwa-maskable-192x192.png", 192],
  ["pwa/icon-badge.svg", "public/pwa-badge-96x96.png", 96],
];

for (const [src, out, size] of icons) {
  const svg = await readFile(join(root, src));
  await sharp(svg, { density: 384 }).resize(size, size).png({ compressionLevel: 9 }).toFile(join(root, out));
  console.log(`${out} (${size}x${size})`);
}
