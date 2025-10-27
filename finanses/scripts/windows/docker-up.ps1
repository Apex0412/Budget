param(
    [string]$ProjectPath
)

if (-not $ProjectPath) {
    $ProjectPath = Resolve-Path "$PSScriptRoot\..\.." | Select-Object -ExpandProperty Path
}

Write-Host "[FINANSES] Используется каталог проекта: $ProjectPath"
if (-not (Test-Path $ProjectPath)) {
    Write-Error "Указанный путь не существует. Передайте верный путь через -ProjectPath."
    exit 1
}

Set-Location $ProjectPath

function Test-Command {
    param([string]$Name)
    $null -ne (Get-Command $Name -ErrorAction SilentlyContinue)
}

if (-not (Test-Command "docker")) {
    Write-Error "Docker Desktop не найден. Установите Docker Desktop для Windows и повторите попытку."
    exit 1
}

if (-not (Test-Command "docker-compose")) {
    Write-Host "docker-compose не найден отдельно — используем встроенную команду 'docker compose'."
}

Write-Host "[FINANSES] Останавливаем предыдущие контейнеры (если были)..."
try {
    docker compose down --remove-orphans | Out-Null
} catch {
    Write-Warning "Команда docker compose down завершилась с ошибкой: $($_.Exception.Message)"
}

Write-Host "[FINANSES] Собираем Docker-образы (добавьте флаг --no-cache, если требуется)..."
docker compose build || exit 1

Write-Host "[FINANSES] Запускаем контейнеры..."
docker compose up -d || exit 1

docker compose ps

$appId = docker compose ps -q app
if (-not $appId) {
    Write-Error "Контейнер приложения не найден. Проверьте вывод 'docker compose ps'."
    exit 1
}

Write-Host "[FINANSES] Устанавливаем PHP-зависимости и выполняем миграции..."
docker exec -it $appId bash -lc "composer install && php database/cli.php migrate && php database/cli.php seed" || exit 1

Write-Host "[FINANSES] Готово! Откройте http://localhost:8080 в браузере."

