#!/bin/sh
set -e

cd /var/www/html

if [ "$CONTAINER_ROLE" = "scheduler" ]; then
    echo "Uruchamianie harmonogramu Laravela..."
    exec php artisan schedule:work
fi

if [ "$CONTAINER_ROLE" = "queue" ]; then
    echo "Uruchamianie przetwarzania kolejki Laravela..."
    exec php artisan queue:work --tries=3
fi

if [ ! -f .env ]; then
    echo "Brak pliku .env - kopiowanie z .env.example"
    cp .env.example .env
fi

echo "Instalacja zależności Composera..."
composer install --no-interaction --prefer-dist --no-progress

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "Generowanie APP_KEY..."
    php artisan key:generate --force
fi

php artisan config:clear >/dev/null
php artisan migrate --force

# Nie używamy `artisan serve` - przekazuje on serwerowi tylko wybrane zmienne
# środowiskowe, więc DB_HOST itp. z docker-compose by nie dotarły
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
echo "Serwer PHP nasłuchuje na 0.0.0.0:8000"
cd public
exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
