// Rejestr pozycji menu. Każdy plik w tym katalogu (poza index.js) eksportuje
// `export default [{ label, to, requiresAuth?, guestOnly?, order? }]`.
//   - to          – RouteLocationRaw, np. { name: "history" } lub "/history"
//   - requiresAuth – widoczne tylko dla zalogowanych
//   - guestOnly    – widoczne tylko dla gości
//   - order        – kolejność (rosnąco, domyślnie 50)
//   - desktop      – false: pozycja tylko w menu mobilnym (np. gdy na desktopie dubluje inny link)

const files = import.meta.glob("./*.js", { eager: true });

export const navigationItems = Object.entries(files)
  .filter(([file]) => file !== "./index.js")
  .flatMap(([file, mod]) =>
    (mod.default || []).map((item, i) => ({
      order: 50,
      ...item,
      key: item.key || `${file}:${i}`,
    }))
  )
  .sort((a, b) => a.order - b.order || String(a.label).localeCompare(String(b.label), "pl"));

// Pozycje widoczne dla danego stanu zalogowania
export function visibleNavigation(isLoggedIn) {
  return navigationItems.filter(
    (item) => !(item.requiresAuth && !isLoggedIn) && !(item.guestOnly && isLoggedIn)
  );
}

export default navigationItems;
