# Vue + Laravel Weather App 🌤️

A weather app with a Laravel 11 REST API and a Vue 3 SPA, focused on Polish cities but working for any city in the world. Data comes from [OpenWeatherMap](https://openweathermap.org). Logged-in users can keep favorite cities with measurement history, set alerts (e-mail and web push), and check air quality. The app can be installed as a PWA. The UI and backend messages are in Polish.

## ✨ Features

### Weather
- **Current weather and a 5-day / 3-hour forecast** for any city: temperature, feels-like, humidity, pressure, wind, visibility, sunrise and sunset.
- **City search**, plus weather and forecast **for your location** (browser geolocation).
- **Rule-based suggestions** (`frontend/src/utils/suggestions.js`), built from the current conditions and the forecast: what to wear, umbrella, storm, snow, icing or frost, wind, heat or cold, fog, and good days for a bike ride or a walk. A UV hint is added when One Call is enabled.
- **City comparison** (`/compare`) for 2 to 4 cities side by side, with SVG charts. The selection is stored in the URL (`/compare?ids=756135,3094802`), so it can be shared.
- **Weather map** (`/map`) built on Leaflet with OpenWeatherMap layers (temperature, precipitation, clouds, wind, pressure). Tiles go through a backend proxy, so **the API key never reaches the browser**. Tiles are cached and rate-limited.
- **Optional One Call API 3.0** panel (UV index, official warnings, 8-day forecast). Turn it on with `WEATHER_ONECALL_ENABLED=true`. It needs a separate "One Call by Call" subscription at OpenWeatherMap.

### Air quality
- The current OWM AQI (1–5), plus the **European (EEA)** and **Polish (GIOŚ)** indexes (1–6), which the backend calculates from pollutant concentrations.
- An air-quality forecast, and history for favorite cities.

### Account and personalisation (logged-in users)
- **Favorites**: up to `WEATHER_FAVORITES_LIMIT` cities (default 10). You can reorder them with drag & drop and give each one a custom name (alias).
- **Home city** and **settings**: metric or imperial units, wind unit (m/s, km/h, mph), and description language (pl/en).
- **History for favorite cities**: a range selector (24 h, 7, 30 or 90 days, or custom dates up to 366 days). Ranges longer than 7 days show daily aggregates (a min–max band). Also shows records, comparisons with yesterday and the last week, and **CSV export**. All charts are hand-made SVG; no chart library is used.
- **Weather alerts**: rules of the following types:
  - for current measurements: `temp_below`, `temp_above`, `wind_above`, `humidity_above`, `pressure_below`, `condition` (rain/snow/thunderstorm/fog)
  - from the forecast: `rain_forecast_tomorrow`, `frost_forecast`

  Alerts are delivered by **e-mail** and/or **web push**. Each rule has a cooldown (30 min to 7 days, default 6 h). There are also in-app notifications with an unread counter (the bell icon).
- **Account management**: edit your profile, verify your e-mail, change your password, reset a forgotten password by e-mail, view and revoke sessions per device (Sanctum tokens), export your data (JSON), and delete your account.

### Platform
- **PWA**: installable. The service worker uses network-first caching for weather and city API responses, so the last data you loaded is shown offline. It also receives web push notifications.
- **Security**: rate limiting on login/register (5/min per e-mail and IP), password reset, e-mail verification, compare (30/min), push test (5/min) and map tiles (per IP). API tokens expire (`SANCTUM_EXPIRATION`, 30 days by default). CORS allows only the origins in `FRONTEND_URL`.
- **OpenWeather free-plan protection**: a global limiter for outgoing requests, a separate budget for tiles, and staggered scheduled jobs. See [Notes on OpenWeather limits](#-notes-on-openweather-limits).

## 🛠️ Tech stack

| Layer | Technologies |
|-------|--------------|
| Backend | PHP 8.2+ (8.3 in Docker), Laravel 11, Laravel Sanctum (API tokens), `minishlink/web-push` (VAPID), MySQL 8, database queue and cache |
| Frontend | Vue 3, Vite 5, Pinia, Vue Router 4, Tailwind CSS 3, Leaflet, `vite-plugin-pwa` + Workbox (custom `src/sw.js`) |
| Tests | PHPUnit 11 (SQLite in-memory, faked HTTP), Vitest |
| Dev environment | Docker Compose: MySQL, PHP, scheduler, queue worker, Mailpit, Node 20 |
| External | OpenWeatherMap: Current Weather, 5-day Forecast, Geocoding, Air Pollution, Weather Maps 1.0 tiles, optional One Call 3.0 |

## 🐳 Quick Start with Docker (recommended)

You only need [Docker](https://docs.docker.com/get-docker/) with Docker Compose v2 (Docker Desktop on Windows and macOS). PHP, Composer, Node.js and MySQL all run inside containers. Get a free API key at [openweathermap.org/api](https://openweathermap.org/api).

```bash
git clone https://github.com/PiotrSmola/Vue-Laravel-Weather-App.git
cd Vue-Laravel-Weather-App
```

Windows (PowerShell):
```powershell
.\setup.ps1                      # or: .\setup.ps1 -ApiKey YOUR_KEY [-NoFetch]
# if scripts are blocked: powershell -ExecutionPolicy Bypass -File .\setup.ps1
```

Linux / macOS / Git Bash:
```bash
./setup.sh                       # or: ./setup.sh --api-key YOUR_KEY [--no-fetch]
```

The script does the following:
1. Checks that Docker and Compose v2 are installed and running.
2. Creates the root `.env` (Compose settings: ports, DB credentials, UID/GID) from `.env.docker.example`, and creates `backend/.env` from `backend/.env.example`.
3. **If a default port is busy, picks the next free one** and saves it in the root `.env`.
4. Asks for your OpenWeatherMap key, unless you passed `--api-key` / `-ApiKey` or set the `OPENWEATHER_API_KEY` env var. The key is written to `backend/.env`.
5. Builds and starts the containers. On first start the backend runs `composer install`, generates `APP_KEY`, runs migrations and generates VAPID keys (`php artisan webpush:vapid`).
6. Waits for the backend health check, then runs `php artisan weather:update` to fetch data for the default cities. Skip this step with `--no-fetch` / `-NoFetch`.
7. Prints the URLs.

| Service | Default address |
|---------|-----------------|
| Frontend | http://localhost:5173 |
| API | http://localhost:8000/api |
| Mailpit (sent e-mails) | http://localhost:8025 |
| MySQL | `localhost:3307` (user `weather`, password `weather`, database `weather_app`) |

If a port was busy, the script prints the one it used instead (`FRONTEND_PORT`, `BACKEND_PORT`, `MAILPIT_PORT`, `DB_HOST_PORT` in the root `.env`).

Containers:

| Service | Role |
|---------|------|
| `db` | MySQL 8.0 (volume `db-data`) |
| `backend` | Laravel on PHP 8.3, built-in PHP server on port 8000 |
| `scheduler` | `php artisan schedule:work` (scheduled jobs, see below) |
| `queue` | `php artisan queue:work --tries=3` (e-mails and push notifications) |
| `mailpit` | Test SMTP server. All e-mails (verification, password reset, alerts) show up in its web UI |
| `frontend` | Vite dev server with hot reload. It proxies `/api` to the backend |

The source code is mounted into the containers, so your changes show up right away.

```bash
docker compose logs -f                              # logs
docker compose stop                                 # stop
docker compose up -d                                # start again
docker compose exec backend php artisan migrate     # any artisan command
docker compose down -v                              # remove containers and the database
```

## ⚙️ Configuration

The backend reads `backend/.env`. In Docker, `docker-compose.yml` overrides `DB_*`, `MAIL_*` (Mailpit), `APP_LOCALE=pl` and `FRONTEND_URL`.

| Variable | Default | Description |
|----------|---------|-------------|
| `OPENWEATHER_API_KEY` | – | OpenWeatherMap API key (required) |
| `OPENWEATHER_API_URL` | `https://api.openweathermap.org` | API base URL |
| `WEATHER_TIMEOUT` | `10` | HTTP timeout in seconds (`WEATHER_RETRY_TIMES`=2, `WEATHER_RETRY_SLEEP`=200 ms) |
| `WEATHER_FAVORITES_LIMIT` | `10` | Maximum number of favorite cities per user |
| `WEATHER_ONECALL_ENABLED` | `false` | Turns on One Call 3.0 (needs a separate subscription) |
| `WEATHER_RATE_LIMIT_PER_MINUTE` | `45` | Global limit on outgoing OWM data requests per minute. When it is used up, the API returns 503. `0` turns the limit off |
| `WEATHER_TILE_RATE_LIMIT_PER_MINUTE` | `15` | Separate budget for OWM tile downloads per minute. When it is used up, a transparent tile is returned |
| `WEATHER_UPDATE_DELAY_MS` | `1500` | Pause between requests in `weather:update`, `air:update` and `alerts:forecast` |
| `WEATHER_TILE_CACHE_MINUTES` | `60` | How long map tiles are cached |
| `WEATHER_TILE_CACHE_STORE` | `file` | Cache store for tiles |
| `WEATHER_TILE_RATE_LIMIT` | `300` | Tile requests per minute per browser IP (cached tiles count too) |
| `WEATHER_CACHE_CURRENT` / `_FORECAST` / `_SEARCH` | `10` / `30` / `60` | Response cache in minutes (air quality: `AIR_CACHE_CURRENT`=15, `AIR_CACHE_FORECAST`=30) |
| `FRONTEND_URL` | `http://localhost:5173,http://localhost:5174` | Allowed CORS origins (comma-separated). The first one is used for links in e-mails and notifications |
| `SANCTUM_EXPIRATION` | `43200` | Token lifetime in minutes (30 days) |
| `ALERTS_RULES_LIMIT` | `20` | Maximum number of alert rules per user |
| `ALERTS_DEFAULT_COOLDOWN` | `360` | Default minutes between notifications from the same rule |
| `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` | – | Web push keys, generated by `php artisan webpush:vapid` |
| `VAPID_SUBJECT` | `mailto:admin@example.com` | Contact address for push services |
| `QUEUE_CONNECTION` | `database` | Mail and push notifications are queued |
| `MAIL_MAILER` | `log` | `smtp` with Mailpit in Docker |
| `APP_LOCALE` | `pl` | Language of backend messages (validation, e-mails). `en` is the fallback |

The root `.env` (copied from `.env.docker.example`) holds only Compose settings: host ports, MySQL credentials and `UID`/`GID`.

## 🚀 Manual installation (without Docker)

Requirements:
- PHP 8.2 or 8.3 with the `pdo_mysql`, `mbstring`, `openssl`, `intl`, `bcmath`, `zip` and **`gmp`** extensions (`gmp` is required by `web-push`).
- Composer 2
- Node.js 20 and npm
- MySQL 8

```bash
git clone https://github.com/PiotrSmola/Vue-Laravel-Weather-App.git
cd Vue-Laravel-Weather-App

# Backend
cd backend
composer install
cp .env.example .env              # set DB_*, OPENWEATHER_API_KEY, MAIL_*
php artisan key:generate
php artisan webpush:vapid         # VAPID keys for web push (writes them to .env)
php artisan migrate               # database must exist, e.g. CREATE DATABASE weather_app;
php artisan weather:update        # optional: initial data for the default cities

# Frontend
cd ../frontend
npm install
```

Run these processes in separate terminals:

```bash
cd backend && php artisan serve        # API on http://localhost:8000
cd backend && php artisan queue:work   # e-mails and push notifications (QUEUE_CONNECTION=database)
cd backend && php artisan schedule:work  # scheduled jobs (or a cron entry running schedule:run every minute)
cd frontend && npm run dev             # SPA on http://localhost:5173 (proxies /api to 127.0.0.1:8000)
```

E-mail: the default `MAIL_MAILER=log` writes messages to `storage/logs/laravel.log`. To get real delivery, set an SMTP server (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, ...), or run Mailpit locally.

Production build of the frontend: `npm run build` (output goes to `frontend/dist`). The Vite proxy is used in development only, so in production serve `/api` from the same origin or put a reverse proxy in front.

## 🧪 Running tests

```bash
docker compose exec backend php artisan test    # backend (PHPUnit)
docker compose exec frontend npm test           # frontend (Vitest)
```

Backend tests always run on **SQLite in-memory**. `phpunit.xml` forces it, and `tests/TestCase.php` refuses to start on any other database, so tests never touch your MySQL data. `Http::preventStrayRequests()` is enabled and every OpenWeather call is faked, so no API key is needed and no requests are sent.

## 📁 Project structure

```
Vue-Laravel-Weather-App/
├── docker-compose.yml          # db, backend, scheduler, queue, mailpit, frontend
├── setup.sh / setup.ps1        # one-command Docker setup
├── .env.docker.example         # Compose settings (ports, DB credentials)
├── backend/                    # Laravel 11 API
│   ├── app/
│   │   ├── Console/Commands/   # weather:update, air:update, alerts:forecast, weather:demo-history, webpush:vapid
│   │   ├── Http/Controllers/   # API controllers
│   │   ├── Models/             # City, WeatherData, AirQualityData, AlertRule, AlertEvent, PushSubscription, User
│   │   ├── Services/           # OpenWeatherClient, WeatherService, AirQualityService, HistoryService,
│   │   │                       # CompareService, Alerts/, Push/
│   │   ├── Support/            # AirQualityIndex (EEA/GIOŚ), FrontendUrl
│   │   ├── Events/ Listeners/ Notifications/ Providers/
│   ├── config/                 # weather.php, air.php, alerts.php, webpush.php, ...
│   ├── database/               # migrations, factories, seeders (HistoryDemoSeeder)
│   ├── docker/entrypoint.sh    # container start-up (composer, key, migrate, VAPID)
│   ├── lang/                   # pl / en
│   ├── routes/
│   │   ├── api.php             # core routes + auto-loading of routes/api/*.php
│   │   ├── api/                # account, air, alerts, favorites, history-compare, map, push, settings
│   │   └── console.php         # schedule
│   └── tests/Feature/
└── frontend/                   # Vue 3 SPA
    ├── public/                 # PWA icons
    ├── pwa/                    # icon sources and generator
    └── src/
        ├── api/                # fetch client (token, errors) and API modules
        ├── components/         # weather, air/, alerts/, compare/, account/
        ├── composables/        # useGeolocation, usePush, useUnits
        ├── navigation/         # menu registry (one file per feature)
        ├── router/             # index.js + modules/*.js
        ├── stores/             # Pinia: auth, weather, settings, alerts
        ├── utils/              # units, dates, air quality, suggestions, map layers
        ├── views/              # Home, WeatherDetails, Compare, Map, Alerts, Settings, Account, Auth/
        └── sw.js               # service worker (cache + push)
```

## 🔌 API reference

All paths have the `/api` prefix. **Auth** means a Sanctum bearer token (`Authorization: Bearer <token>`), which you get from `/login` or `/register`. Most weather endpoints take an optional `lang` (`pl`/`en`). City IDs are OpenWeatherMap city IDs.

### Authentication

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/register` | – | Create an account and get a token (throttled) |
| POST | `/login` | – | Log in and get a token (throttled) |
| POST | `/logout` | ✔ | Revoke the current token |
| POST | `/logout-all` | ✔ | Revoke all tokens |
| GET | `/user` | ✔ | Current user |
| POST | `/forgot-password` | – | Send a password reset link by e-mail (throttled) |
| POST | `/reset-password` | – | Set a new password using the reset token (throttled) |
| GET | `/email/verify/{id}/{hash}` | – | Signed link from the e-mail. Redirects to the frontend |
| POST | `/email/verify` | ✔ | Confirm e-mail verification (throttled) |
| POST | `/email/verification-notification` | ✔ | Resend the verification e-mail (throttled) |

### Weather

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/weather/{cityId}` | – | Current weather for a city |
| GET | `/weather/city?q=` | – | Current weather by city name |
| GET | `/weather/coordinates?lat=&lon=` | – | Current weather by coordinates |
| GET | `/weather/forecast/{cityId}` | – | 5-day / 3-hour forecast |
| GET | `/weather/forecast/city?q=` | – | Forecast by city name |
| GET | `/weather/forecast/coordinates?lat=&lon=` | – | Forecast by coordinates |
| GET | `/weather/onecall/{cityId}` | – | One Call 3.0 data (only when enabled) |
| GET | `/features` | – | Optional features that are turned on (`onecall`) |
| GET | `/compare?ids=a,b[,c,d]` | – | Current weather and forecast for 2 to 4 cities (30/min) |

### Cities and favorites

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/cities` | optional | Default cities. When a token is sent, the user's favorites are left out |
| GET | `/cities/search?q=` | – | City search (at least 3 characters) |
| GET | `/cities/user` | ✔ | The user's favorites |
| POST | `/cities` | ✔ | Add a favorite (`city_id`), up to the configured limit |
| DELETE | `/cities/{cityId}` | ✔ | Remove a favorite |
| PUT | `/cities/user/order` | ✔ | Save the order of favorites |
| PATCH | `/cities/user/{cityId}` | ✔ | Set or clear a custom name (`alias`) |

### History (favorite cities only)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/cities/{cityId}/historical?from=&to=` | ✔ | Raw measurements (default 7 days, max 366) |
| GET | `/cities/{cityId}/history/daily?from=&to=&tz=` | ✔ | Daily aggregates |
| GET | `/cities/{cityId}/history/summary?from=&to=&tz=` | ✔ | Records, averages, comparison with yesterday and last week |
| GET | `/cities/{cityId}/history/export?from=&to=&tz=&format=csv` | ✔ | CSV download |

### Air quality

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/air/{cityId}` | – | Current air quality (OWM AQI, EEA, GIOŚ) |
| GET | `/air/coordinates?lat=&lon=` | – | Current air quality by coordinates |
| GET | `/air/{cityId}/forecast` | – | Air-quality forecast |
| GET | `/air/{cityId}/historical?from=&to=` | ✔ | Air-quality history (favorites only) |

### Map

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/map/tiles/{layer}/{z}/{x}/{y}.png` | – | Tile proxy. Layers: `temp_new`, `precipitation_new`, `clouds_new`, `wind_new`, `pressure_new`. Max zoom 10 |
| GET | `/map/cities` | – | Cities with their latest stored measurement (map markers) |

### Settings and account

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/user/settings` | ✔ | Units, wind unit, language, home city |
| PUT | `/user/settings` | ✔ | Update the settings (`units`, `wind_unit`, `lang`, `home_city_id`) |
| GET | `/account` | ✔ | Account details |
| PUT | `/account/profile` | ✔ | Update name and e-mail |
| PUT | `/account/password` | ✔ | Change password |
| GET | `/account/tokens` | ✔ | Active sessions (tokens per device) |
| DELETE | `/account/tokens/{tokenId}` | ✔ | Revoke a session |
| GET | `/account/export` | ✔ | Export your data (JSON) |
| DELETE | `/account` | ✔ | Delete the account (password required) |

### Alerts and push

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/alerts` | ✔ | The user's alert rules |
| GET | `/alerts/meta` | ✔ | Rule types, conditions, channels, threshold ranges and cooldown limits |
| POST | `/alerts` | ✔ | Create a rule |
| PUT / PATCH | `/alerts/{id}` | ✔ | Update a rule |
| DELETE | `/alerts/{id}` | ✔ | Delete a rule |
| GET | `/alerts/events` | ✔ | Triggered alerts, paginated (in-app notifications) |
| GET | `/alerts/events/unread-count` | ✔ | Unread counter |
| POST | `/alerts/events/{id}/read` | ✔ | Mark one alert as read |
| POST | `/alerts/events/read-all` | ✔ | Mark all alerts as read |
| GET | `/push/public-key` | – | VAPID public key |
| POST | `/push/subscriptions` | ✔ | Register this device's push subscription |
| DELETE | `/push/subscriptions` | ✔ | Remove a push subscription |
| POST | `/push/test` | ✔ | Send a test notification (5/min) |

## ⏰ Scheduled jobs

These are defined in `backend/routes/console.php` and run by `schedule:work` (the `scheduler` container). The jobs are staggered so they never hit the OpenWeather API at the same minute.

| Command | When | What it does |
|---------|------|--------------|
| `weather:update` | every 30 min (`:00`, `:30`) | Stores current weather for favorite and default cities, then evaluates alert rules for the current measurements |
| `air:update` | hourly at `:15` | Stores air quality for favorite and default cities |
| `alerts:forecast` | every 3 h at `:45` | Checks forecast alerts (rain tomorrow, frost) for cities with active rules |

Other commands:

```bash
php artisan weather:demo-history 759734 --days=60 --interval=60 --user=you@example.com
#   generates artificial history (1–366 days, interval 10–240 min) for previewing charts.
#   It never overwrites real measurements. --seed for repeatable data, --force to allow it in production.
#   The HistoryDemoSeeder does the same for Rzeszów (60 days).
php artisan webpush:vapid [--force] [--show]   # generate VAPID keys into .env
```

## 🧩 Extending the app

- **Backend routes**: add `backend/routes/api/<feature>.php`. Every file in that folder is loaded automatically, in alphabetical order, with the `/api` prefix and the `api` middleware group. Register new scheduled jobs in `routes/console.php`.
- **Frontend routes**: add `frontend/src/router/modules/<feature>.js` that exports `export default [{ path, name, component, meta }]`. Use `meta.requiresAuth` (redirects to login with `?redirect=`) or `meta.guest`.
- **Menu items**: add `frontend/src/navigation/<feature>.js` that exports `[{ label, to, requiresAuth?, guestOnly?, order?, desktop? }]`. The header collects the items automatically, sorted by `order`.

## 📉 Notes on OpenWeather limits

The free OpenWeatherMap plan allows **60 calls per minute**. Going over it returns HTTP 429. The app stays below the limit in three ways:

- A **global limiter for outgoing requests**: `WEATHER_RATE_LIMIT_PER_MINUTE=45` covers weather, forecast, geocoding and air quality. When it is used up, the API answers 503 without calling OpenWeather.
- A **separate tile budget**: `WEATHER_TILE_RATE_LIMIT_PER_MINUTE=15`. Browsing the map can't use up the budget for weather data, and 45 + 15 = 60 per minute. Tiles are cached (`WEATHER_TILE_CACHE_MINUTES`), and a transparent tile is returned when the budget is used up.
- **Staggered, throttled jobs**: jobs run at `:00/:30`, `:15` and `:45`, with a `WEATHER_UPDATE_DELAY_MS=1500` pause between requests (about 40 per minute). Responses are also cached (current weather 10 min, forecast 30 min).

With a paid plan, raise these values, and keep their sum within your plan's per-minute limit. One Call 3.0 is billed separately, so leave `WEATHER_ONECALL_ENABLED=false` unless you have that subscription. The app also remembers a 401 from One Call for an hour.

## 🙏 Attribution

**Weather data provided by [OpenWeatherMap](https://openweathermap.org)** (CC BY-SA 4.0). Map base layer © [OpenStreetMap](https://www.openstreetmap.org/copyright) contributors.

## 📄 License

This project is licensed under the **GNU General Public License v3.0**. See [LICENSE](LICENSE).
