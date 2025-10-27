param(
    [string]$ProjectPath,
    [switch]$NoCache
)

if (-not $ProjectPath) {
    $ProjectPath = (Resolve-Path "$PSScriptRoot\..\..").Path
}

Write-Host "[FINANSES] Каталог проекта: $ProjectPath"
if (-not (Test-Path $ProjectPath)) {
    Write-Error "Каталог не найден. Укажите корректный путь через -ProjectPath."
    exit 1
}

Set-Location $ProjectPath

function Test-Command {
    param([string]$Name)
    return $null -ne (Get-Command $Name -ErrorAction SilentlyContinue)
}

if (-not (Test-Command 'docker')) {
    Write-Error 'Docker Desktop не обнаружен. Установите и запустите Docker Desktop.'
    exit 1
}

function Get-ComposeInvoker {
    if (Test-Command 'docker') {
        docker compose version *> $null
        if ($LASTEXITCODE -eq 0) {
            return @{ Name = 'docker compose'; UseDocker = $true }
        }
    }
    if (Test-Command 'docker-compose') {
        docker-compose --version *> $null
        if ($LASTEXITCODE -eq 0) {
            return @{ Name = 'docker-compose'; UseDocker = $false }
        }
    }
    return $null
}

$compose = Get-ComposeInvoker
if (-not $compose) {
    Write-Error 'Не удалось найти docker compose. Проверьте установку Docker Desktop.'
    exit 1
}

function Invoke-Compose {
    param([string[]]$Args)
    if ($compose.UseDocker) {
        & docker compose @Args
    } else {
        & docker-compose @Args
    }
}

Write-Host '[FINANSES] Останавливаем предыдущие контейнеры...'
Invoke-Compose -Args @('down', '--remove-orphans') | Out-Null

$buildArgs = @('build')
if ($NoCache.IsPresent) {
    $buildArgs += '--no-cache'
}
Write-Host '[FINANSES] Собираем образы...'
Invoke-Compose -Args $buildArgs || exit 1

Write-Host '[FINANSES] Запускаем контейнеры...'
Invoke-Compose -Args @('up', '-d') || exit 1
Invoke-Compose -Args @('ps')

function Wait-Health {
    param(
        [string]$Container,
        [int]$Attempts = 60,
        [int]$DelaySeconds = 5
    )
    for ($i = 1; $i -le $Attempts; $i++) {
        $status = docker inspect --format '{{.State.Health.Status}}' $Container 2>$null
        if ($LASTEXITCODE -eq 0 -and $status -eq 'healthy') {
            Write-Host "[FINANSES] $Container: healthy"
            return $true
        }
        Start-Sleep -Seconds $DelaySeconds
    }
    return $false
}

if (-not (Wait-Health -Container 'finanses-db')) {
    Write-Warning 'БД не перешла в состояние healthy. Посмотрите docker compose logs -f db.'
}

Write-Host '[FINANSES] Проверяем доступность приложения...'
$healthUrl = 'http://localhost:8080/api/health.php?type=app'
$maxAttempts = 40
$success = $false
for ($i = 1; $i -le $maxAttempts; $i++) {
    try {
        $response = Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -TimeoutSec 5
        if ($response.StatusCode -eq 200) {
            $success = $true
            break
        }
    } catch {
        Start-Sleep -Seconds 3
    }
}

if (-not $success) {
    Write-Warning 'Приложение не ответило на healthcheck. Проверьте docker compose logs -f app.'
} else {
    Write-Host '[FINANSES] Приложение отвечает на healthcheck.'
}

Write-Host '[FINANSES] Синхронизируем зависимости и выполняем миграции...'
$execArgs = @('exec', '-T', 'app', 'bash', '-lc', 'composer install --no-interaction --prefer-dist --no-progress && php database/cli.php migrate && php database/cli.php seed')
Invoke-Compose -Args $execArgs

Write-Host '[FINANSES] Готово! Откройте http://localhost:8080 (логин admin / пароль admin123).'
