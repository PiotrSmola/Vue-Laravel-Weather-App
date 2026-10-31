# Konfiguruje i uruchamia Weather App w Dockerze (Windows PowerShell 5.1+ / PowerShell 7).
# Użycie: .\setup.ps1 [-ApiKey KLUCZ] [-NoFetch]
# Jeśli skrypty są zablokowane: powershell -ExecutionPolicy Bypass -File .\setup.ps1
[CmdletBinding()]
param(
    [string]$ApiKey = $env:OPENWEATHER_API_KEY,
    [switch]$NoFetch
)

$ErrorActionPreference = 'Stop'
Set-Location -Path $PSScriptRoot

function Info($msg) { Write-Host "==> $msg" -ForegroundColor Cyan }
function Warn($msg) { Write-Host "!! $msg" -ForegroundColor Yellow }
function Fail($msg) { Write-Host "Błąd: $msg" -ForegroundColor Red; exit 1 }

$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)

# Ustawia KEY=VALUE w pliku .env (dodaje linię, jeśli jej nie ma)
function Set-EnvValue([string]$File, [string]$Key, [string]$Value) {
    $path = Join-Path $PSScriptRoot $File
    $lines = [System.Collections.Generic.List[string]]([System.IO.File]::ReadAllLines($path))
    $found = $false
    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i].StartsWith("$Key=")) { $lines[$i] = "$Key=$Value"; $found = $true }
    }
    if (-not $found) { $lines.Add("$Key=$Value") }
    [System.IO.File]::WriteAllLines($path, $lines, $Utf8NoBom)
}

function Get-EnvValue([string]$File, [string]$Key) {
    $path = Join-Path $PSScriptRoot $File
    if (-not (Test-Path $path)) { return '' }
    $line = Get-Content $path | Where-Object { $_.StartsWith("$Key=") } | Select-Object -First 1
    if ($line) { return $line.Substring($Key.Length + 1).Trim('"') }
    return ''
}

Info 'Sprawdzanie Dockera...'
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Fail 'Docker nie jest zainstalowany: https://docs.docker.com/desktop/install/windows-install/'
}
docker info *> $null
if ($LASTEXITCODE -ne 0) { Fail 'Docker nie działa. Uruchom Docker Desktop i spróbuj ponownie.' }
docker compose version *> $null
if ($LASTEXITCODE -ne 0) { Fail "Brak Docker Compose v2 (polecenie 'docker compose')." }

if (-not (Test-Path '.env')) {
    Info 'Tworzenie .env dla docker compose...'
    Copy-Item '.env.docker.example' '.env'
}

function Test-PortInUse([int]$Port) {
    $listeners = [System.Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners()
    return [bool]($listeners | Where-Object { $_.Port -eq $Port })
}

# Jeśli port z .env jest zajęty, wybiera następny wolny i zapisuje go w .env
function Confirm-FreePort([string]$Key) {
    $original = [int](Get-EnvValue '.env' $Key)
    $port = $original
    while (Test-PortInUse $port) { $port++ }
    if ($port -ne $original) {
        Warn "Port $original ($Key) jest zajęty - używam $port."
        Set-EnvValue '.env' $Key "$port"
    }
}

# Porty sprawdzamy tylko, gdy nasze kontenery nie działają (inaczej same je zajmują)
if (-not (docker compose ps -q 2>$null)) {
    Confirm-FreePort 'FRONTEND_PORT'
    Confirm-FreePort 'BACKEND_PORT'
    Confirm-FreePort 'DB_HOST_PORT'
}

if (-not (Test-Path 'backend\.env')) {
    Info 'Tworzenie backend\.env z backend\.env.example...'
    Copy-Item 'backend\.env.example' 'backend\.env'
}
$BackendPort = Get-EnvValue '.env' 'BACKEND_PORT'
$FrontendPort = Get-EnvValue '.env' 'FRONTEND_PORT'
Set-EnvValue 'backend\.env' 'APP_NAME' '"Weather App"'
Set-EnvValue 'backend\.env' 'APP_URL' "http://localhost:$BackendPort"

$CurrentKey = Get-EnvValue 'backend\.env' 'OPENWEATHER_API_KEY'
if (-not $ApiKey -and -not $CurrentKey) {
    Write-Host ''
    Write-Host 'Aplikacja potrzebuje klucza API OpenWeatherMap (darmowy: https://openweathermap.org/api).'
    $ApiKey = Read-Host 'Podaj klucz (Enter = pomiń, możesz dodać go później w backend\.env)'
}
if ($ApiKey) {
    Set-EnvValue 'backend\.env' 'OPENWEATHER_API_KEY' $ApiKey.Trim()
    $CurrentKey = $ApiKey
}
if (-not $CurrentKey) { Warn 'Brak klucza OpenWeatherMap - dane pogodowe nie będą się pobierać.' }

Info 'Budowanie i uruchamianie kontenerów (pierwsze uruchomienie może potrwać kilka minut)...'
docker compose up -d --build
if ($LASTEXITCODE -ne 0) { Fail 'docker compose up nie powiódł się.' }

Info 'Oczekiwanie na backend (composer install + migracje)...'
$containerId = docker compose ps -q backend
$healthy = $false
for ($i = 0; $i -lt 120; $i++) {
    $status = docker inspect -f '{{.State.Health.Status}}' $containerId 2>$null
    if ($status -eq 'healthy') { $healthy = $true; break }
    Start-Sleep -Seconds 5
}
if (-not $healthy) {
    docker compose logs --tail 50 backend
    Fail 'Backend nie wystartował w ciągu 10 minut. Logi: docker compose logs backend'
}

if (-not $NoFetch -and $CurrentKey) {
    Info 'Pobieranie danych pogodowych dla domyślnych miast...'
    docker compose exec -T backend php artisan weather:update
    if ($LASTEXITCODE -ne 0) { Warn 'Nie udało się pobrać danych - sprawdź klucz API.' }
}

$DbPort = Get-EnvValue '.env' 'DB_HOST_PORT'
$DbUser = Get-EnvValue '.env' 'DB_USERNAME'
$DbPass = Get-EnvValue '.env' 'DB_PASSWORD'

Write-Host ''
Info 'Gotowe!'
Write-Host "  Frontend:  http://localhost:$FrontendPort"
Write-Host "  API:       http://localhost:$BackendPort/api"
Write-Host "  MySQL:     localhost:$DbPort (użytkownik: $DbUser, hasło: $DbPass)"
Write-Host ''
Write-Host 'Przydatne polecenia:'
Write-Host '  docker compose logs -f      # logi'
Write-Host '  docker compose stop         # zatrzymanie'
Write-Host '  docker compose up -d        # ponowne uruchomienie'
Write-Host '  docker compose down -v      # usunięcie kontenerów wraz z bazą danych'
