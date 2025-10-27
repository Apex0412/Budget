param(
    [string]$ProjectPath,
    [switch]$NoCache
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

function Get-ComposeInvoker {
    if (Test-Command "docker") {
        & docker compose version *> $null
        if ($LASTEXITCODE -eq 0) {
            return @{ Name = 'docker compose'; UseDocker = $true }
        }
    }

    if (Test-Command "docker-compose") {
        & docker-compose --version *> $null
        if ($LASTEXITCODE -eq 0) {
            return @{ Name = 'docker-compose'; UseDocker = $false }
        }
    }

    return $null
}

$compose = Get-ComposeInvoker
if (-not $compose) {
    Write-Error "Команда docker compose / docker-compose не найдена. Проверьте установку Docker Desktop и включите интеграцию с PowerShell."
    exit 1
}

Write-Host "[FINANSES] Используется команда: $($compose.Name)"

function Invoke-Compose {
    param([string[]]$Args)

    if ($compose.UseDocker) {
        & docker compose @Args
    } else {
        & docker-compose @Args
    }
}

Write-Host "[FINANSES] Останавливаем предыдущие контейнеры (если были)..."
try {
    Invoke-Compose -Args @('down', '--remove-orphans') | Out-Null
} catch {
    Write-Warning "Команда docker compose down завершилась с ошибкой: $($_.Exception.Message)"
}

Write-Host "[FINANSES] Собираем Docker-образы..."
$buildArgs = @('build')
if ($NoCache.IsPresent) {
    $buildArgs += '--no-cache'
}
Invoke-Compose -Args $buildArgs || exit 1

Write-Host "[FINANSES] Запускаем контейнеры..."
Invoke-Compose -Args @('up', '-d') || exit 1

Invoke-Compose -Args @('ps')

$appId = Invoke-Compose -Args @('ps', '-q', 'app')
$appId = ($appId | Where-Object { $_ -and $_.Trim() -ne '' } | Select-Object -First 1)
if (-not $appId) {
    Write-Error "Контейнер приложения не найден. Проверьте вывод 'docker compose ps'."
    exit 1
}

Write-Host "[FINANSES] Устанавливаем PHP-зависимости и выполняем миграции..."
docker exec -it $appId bash -lc "composer install --no-interaction --prefer-dist && php database/cli.php migrate && php database/cli.php seed" || exit 1

Write-Host "[FINANSES] Готово! Откройте http://localhost:8080 в браузере."

