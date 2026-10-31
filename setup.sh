#!/usr/bin/env bash
# Konfiguruje i uruchamia Weather App w Dockerze (Linux / macOS / Git Bash).
# Użycie: ./setup.sh [--api-key KLUCZ] [--no-fetch]
set -euo pipefail

cd "$(dirname "$0")"

API_KEY="${OPENWEATHER_API_KEY:-}"
FETCH_WEATHER=1

while [ $# -gt 0 ]; do
    case "$1" in
        --api-key) API_KEY="${2:-}"; shift 2 ;;
        --no-fetch) FETCH_WEATHER=0; shift ;;
        -h|--help)
            echo "Użycie: ./setup.sh [--api-key KLUCZ] [--no-fetch]"
            echo "  --api-key KLUCZ  klucz OpenWeatherMap (domyślnie z OPENWEATHER_API_KEY lub pytanie)"
            echo "  --no-fetch       nie pobieraj danych pogodowych po starcie"
            exit 0 ;;
        *) echo "Nieznana opcja: $1" >&2; exit 1 ;;
    esac
done

info() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m!!\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31mBłąd:\033[0m %s\n' "$*" >&2; exit 1; }

# Ustawia KEY=VALUE w pliku .env (dodaje linię, jeśli jej nie ma)
set_env() {
    local file="$1" key="$2" value="$3"
    if grep -q "^${key}=" "$file"; then
        local tmp; tmp="$(mktemp)"
        awk -v k="$key" -v v="$value" 'index($0, k "=") == 1 { print k "=" v; next } { print }' "$file" > "$tmp"
        cat "$tmp" > "$file" && rm -f "$tmp"
    else
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
}

get_env() {
    grep "^$2=" "$1" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"\r' || true
}

port_in_use() {
    if command -v ss >/dev/null 2>&1; then
        ss -ltn 2>/dev/null | awk '{print $4}' | grep -qE "[:.]$1\$"
    elif command -v lsof >/dev/null 2>&1; then
        lsof -iTCP:"$1" -sTCP:LISTEN >/dev/null 2>&1
    else
        netstat -an 2>/dev/null | grep -iE 'listen' | grep -qE "[:.]$1[[:space:]]"
    fi
}

# Jeśli port z .env jest zajęty, wybiera następny wolny i zapisuje go w .env
ensure_free_port() {
    local key="$1" port; port="$(get_env .env "$key")"
    local original="$port"
    while port_in_use "$port"; do port=$((port + 1)); done
    if [ "$port" != "$original" ]; then
        warn "Port $original ($key) jest zajęty - używam $port."
        set_env .env "$key" "$port"
    fi
}

info "Sprawdzanie Dockera..."
command -v docker >/dev/null 2>&1 || fail "Docker nie jest zainstalowany: https://docs.docker.com/get-docker/"
docker info >/dev/null 2>&1 || fail "Docker nie działa. Uruchom Docker Desktop / usługę docker i spróbuj ponownie."
docker compose version >/dev/null 2>&1 || fail "Brak Docker Compose v2 (polecenie 'docker compose')."

# Plik .env dla docker compose (UID/GID, aby pliki tworzone w kontenerze należały do Ciebie)
if [ ! -f .env ]; then
    info "Tworzenie .env dla docker compose..."
    cp .env.docker.example .env
    if [ "$(uname -s)" = "Linux" ]; then
        set_env .env UID "$(id -u)"
        set_env .env GID "$(id -g)"
    fi
fi

# Starsze pliki .env mogą nie mieć portu Mailpit
[ -n "$(get_env .env MAILPIT_PORT)" ] || set_env .env MAILPIT_PORT 8025

# Porty sprawdzamy tylko, gdy nasze kontenery nie działają (inaczej same je zajmują)
if [ -z "$(docker compose ps -q 2>/dev/null)" ]; then
    ensure_free_port FRONTEND_PORT
    ensure_free_port BACKEND_PORT
    ensure_free_port DB_HOST_PORT
    ensure_free_port MAILPIT_PORT
fi

if [ ! -f backend/.env ]; then
    info "Tworzenie backend/.env z backend/.env.example..."
    cp backend/.env.example backend/.env
fi
set_env backend/.env APP_NAME '"Weather App"'
set_env backend/.env APP_URL "http://localhost:$(get_env .env BACKEND_PORT)"

CURRENT_KEY="$(get_env backend/.env OPENWEATHER_API_KEY)"
if [ -z "$API_KEY" ] && [ -z "$CURRENT_KEY" ] && [ -t 0 ]; then
    echo
    echo "Aplikacja potrzebuje klucza API OpenWeatherMap (darmowy: https://openweathermap.org/api)."
    read -r -p "Podaj klucz (Enter = pomiń, możesz dodać go później w backend/.env): " API_KEY
fi
if [ -n "$API_KEY" ]; then
    set_env backend/.env OPENWEATHER_API_KEY "$API_KEY"
    CURRENT_KEY="$API_KEY"
fi
[ -n "$CURRENT_KEY" ] || warn "Brak klucza OpenWeatherMap - dane pogodowe nie będą się pobierać."

info "Budowanie i uruchamianie kontenerów (pierwsze uruchomienie może potrwać kilka minut)..."
docker compose up -d --build

BACKEND_PORT="$(get_env .env BACKEND_PORT)"
FRONTEND_PORT="$(get_env .env FRONTEND_PORT)"

info "Oczekiwanie na backend (composer install + migracje)..."
for i in $(seq 1 120); do
    status="$(docker inspect -f '{{.State.Health.Status}}' "$(docker compose ps -q backend)" 2>/dev/null || echo starting)"
    [ "$status" = "healthy" ] && break
    if [ "$i" -eq 120 ]; then
        docker compose logs --tail 50 backend
        fail "Backend nie wystartował w ciągu 10 minut. Logi: docker compose logs backend"
    fi
    sleep 5
done

if [ "$FETCH_WEATHER" -eq 1 ] && [ -n "$CURRENT_KEY" ]; then
    info "Pobieranie danych pogodowych dla domyślnych miast..."
    docker compose exec -T backend php artisan weather:update || warn "Nie udało się pobrać danych - sprawdź klucz API."
fi

echo
info "Gotowe!"
echo "  Frontend:  http://localhost:${FRONTEND_PORT}"
echo "  API:       http://localhost:${BACKEND_PORT}/api"
echo "  Mailpit:   http://localhost:$(get_env .env MAILPIT_PORT) (podgląd wysłanych maili)"
echo "  MySQL:     localhost:$(get_env .env DB_HOST_PORT) (użytkownik: $(get_env .env DB_USERNAME), hasło: $(get_env .env DB_PASSWORD))"
echo
echo "Przydatne polecenia:"
echo "  docker compose logs -f      # logi"
echo "  docker compose stop         # zatrzymanie"
echo "  docker compose up -d        # ponowne uruchomienie"
echo "  docker compose down -v      # usunięcie kontenerów wraz z bazą danych"
