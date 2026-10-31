// Krótki opis urządzenia na podstawie navigator.userAgent, np. "Chrome na Windows".
// Używany jako nazwa tokenu logowania (lista aktywnych sesji na stronie konta).
export function deviceName(userAgent = typeof navigator !== "undefined" ? navigator.userAgent : "") {
  const ua = String(userAgent || "");
  if (!ua) return "web";

  const browsers = [
    [/Edg(e|A|iOS)?\//, "Edge"],
    [/OPR\/|Opera/, "Opera"],
    [/SamsungBrowser\//, "Samsung Internet"],
    [/Firefox\/|FxiOS\//, "Firefox"],
    [/Chrome\/|CriOS\//, "Chrome"],
    [/Safari\//, "Safari"],
  ];
  const systems = [
    [/Windows/, "Windows"],
    [/Android/, "Android"],
    [/iPhone|iPad|iPod/, "iOS"],
    [/Mac OS X|Macintosh/, "macOS"],
    [/CrOS/, "ChromeOS"],
    [/Linux/, "Linux"],
  ];

  const browser = browsers.find(([re]) => re.test(ua))?.[1];
  const system = systems.find(([re]) => re.test(ua))?.[1];

  if (browser && system) return `${browser} na ${system}`;
  return browser || system || "web";
}
