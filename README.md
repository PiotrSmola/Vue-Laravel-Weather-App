# Vue + Laravel Weather App 🌤️


A modern weather tracking application for monitoring weather conditions in various cities, with special focus on major Polish cities. The app provides current weather information, forecasts, and historical data tracking for favorite locations.

## ✨ Features

### Core Features
- **Current Weather** - Display real-time weather conditions
- **City Search** - Search for weather in any city worldwide
- **5-Day Forecast** - Detailed weather forecast for upcoming days
- **Hourly Forecast** - Precise forecast for the next 24 hours

### Features for Authenticated Users
- **Favorite Cities** - Add and manage a list of favorite locations
- **Historical Data** - Browse weather history for favorite cities
- **Charts & Statistics** - Visualize weather data with interactive charts
- **Automatic Updates** - Data for favorite cities is updated every 30 minutes

### Detailed Weather Information
- Temperature (current and feels like)
- Humidity
- Atmospheric pressure
- Wind speed and direction
- Sunrise and sunset times
- Weather conditions (precipitation, visibility)

## 🛠️ Technologies

### Backend
- **Laravel 11** - PHP framework for building the API
- **PHP 8.2+** - Backend programming language
- **MySQL** - Database
- **Laravel Sanctum** - API authentication

### Frontend
- **Vue.js 3** - Reactive JavaScript framework
- **Vite** - Build tool and development server
- **Pinia** - State management for Vue
- **Vue Router** - SPA routing
- **Tailwind CSS** - CSS framework for styling
- **Chart.js** - Charts and data visualization library

### External APIs
- **OpenWeatherMap API** - Weather data provider

## 📋 Requirements

### Backend
- PHP 8.2 or higher
- Composer
- MySQL 5.7+
- PHP extensions: `ext-ctype`, `ext-fileinfo`, `ext-filter`, `ext-hash`, `ext-mbstring`, `ext-openssl`, `ext-session`, `ext-tokenizer`

### Frontend
- Node.js 16+ 
- npm or yarn

### External
- OpenWeatherMap API key (free available)

## 🐳 Quick Start with Docker (recommended)

The only requirement is [Docker](https://docs.docker.com/get-docker/) with Docker Compose v2 (Docker Desktop on Windows/macOS). PHP, Composer, Node.js and MySQL run inside containers.

```bash
git clone https://github.com/PiotrSmola/Vue-Laravel-Weather-App.git
cd Vue-Laravel-Weather-App
```

Windows (PowerShell):
```powershell
.\setup.ps1
# if scripts are blocked: powershell -ExecutionPolicy Bypass -File .\setup.ps1
```

Linux / macOS / Git Bash:
```bash
./setup.sh
```

The script:
1. checks that Docker is installed and running,
2. creates `.env` (Compose settings) and `backend/.env`, picking free ports if the defaults are taken,
3. asks for your OpenWeatherMap API key (you can also pass it: `-ApiKey KEY` / `--api-key KEY`),
4. builds and starts the containers; on start the backend runs `composer install`, generates `APP_KEY` and runs migrations,
5. fetches initial weather data for the default cities (skip with `-NoFetch` / `--no-fetch`).

After setup the app is available at:

| Service  | Address                     |
|----------|-----------------------------|
| Frontend | http://localhost:5173       |
| API      | http://localhost:8000/api   |
| MySQL    | `localhost:3307` (user `weather`, password `weather`) |

If a port was taken, the script prints the one it used instead (stored in `.env`).

Containers: `db` (MySQL 8), `backend` (Laravel on PHP 8.3), `scheduler` (`php artisan schedule:work`, updates weather every 30 minutes) and `frontend` (Vite dev server with hot reload). The source code is mounted into the containers, so changes are visible immediately.

Useful commands:
```bash
docker compose logs -f                              # logs
docker compose stop                                 # stop
docker compose up -d                                # start again
docker compose exec backend php artisan migrate     # artisan commands
docker compose down -v                              # remove containers and the database
```

## 🚀 Manual Installation

### 1. Clone the repository
```bash
git clone https://github.com/PiotrSmola/Vue-Laravel-Weather-App.git
cd weather-app
```

### 2. Backend setup
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

### 3. Frontend setup
```bash
cd ../frontend
npm install
```

## ⚙️ Configuration

### Backend (.env)
```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=weather_app
DB_USERNAME=your_username
DB_PASSWORD=your_password

# OpenWeatherMap API
WEATHER_API_KEY=your_openweathermap_api_key
WEATHER_API_URL=https://api.openweathermap.org/data/2.5

# Application
APP_NAME="Weather App"
APP_ENV=local
APP_KEY=base64:generated_key
APP_DEBUG=true
APP_URL=http://localhost:8000

# CORS
FRONTEND_URL=http://localhost:5173
```

### Database setup
```bash
# Create database
mysql -u root -p
CREATE DATABASE weather_app;

# Run migrations
cd backend
php artisan migrate
```

### OpenWeatherMap API key
1. Register at [OpenWeatherMap](https://openweathermap.org/api)
2. Create a free account
3. Generate an API key
4. Add the key to your backend `.env` file

## ▶️ Running the App

### Development
```bash
# Backend (terminal 1)
cd backend
php artisan serve
# Available at: http://localhost:8000

# Frontend (terminal 2)
cd frontend
npm run dev
# Available at: http://localhost:5173
```

### Production
```bash
# Frontend build
cd frontend
npm run build

# Backend optimization
cd backend
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 📁 Project Structure

```
weather-app/
├── backend/                 # Laravel API backend
│   ├── app/
│   │   ├── Http/
│   │   │   └── Controllers/ # API controllers
│   │   ├── Models/          # Eloquent models
│   │   └── Services/        # Business logic
│   ├── config/              # Laravel configuration
│   ├── database/
│   │   ├── migrations/      # Database migrations
│   │   └── seeders/         # Database seeders
│   └── routes/
│       └── api.php          # API route definitions
├── frontend/                # Vue.js frontend
│   ├── src/
│   │   ├── components/      # Vue components
│   │   ├── views/           # Views/pages
│   │   ├── stores/          # State management (Pinia)
│   │   ├── router/          # Router configuration
│   │   └── assets/          # Static assets
│   ├── public/              # Public files
│   └── package.json         # npm dependencies
└── README.md               # This file
```

## 🔌 API

### Public endpoints
```
GET /api/weather/{cityId}           # Current weather for city
GET /api/weather/forecast/{cityId}  # 5-day forecast
GET /api/cities/search             # Search cities
```

### Authenticated endpoints
```
POST /api/auth/register            # User registration
POST /api/auth/login              # User login
POST /api/auth/logout             # User logout
GET /api/user                     # User data

GET /api/user/cities              # List of favorite cities
POST /api/user/cities/{cityId}    # Add city to favorites
DELETE /api/user/cities/{cityId}  # Remove from favorites

GET /api/cities/{cityId}/historical # Historical data
```

**Weather data provided by [OpenWeatherMap](https://openweathermap.org)**
