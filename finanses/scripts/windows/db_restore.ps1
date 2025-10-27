param(
    [string]$File
)

$projectPath = (Resolve-Path "$PSScriptRoot\..\..").Path
Set-Location $projectPath

function Test-Command {
    param([string]$Name)
    return $null -ne (Get-Command $Name -ErrorAction SilentlyContinue)
}

if (-not (Test-Command 'docker')) {
    Write-Error 'Docker Desktop не найден.'
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
    Write-Error 'Команда docker compose не найдена.'
    exit 1
}

$backupDir = Join-Path $projectPath 'backups'
if (-not $File) {
    if (-not (Test-Path $backupDir)) {
        Write-Error 'Каталог backups не найден. Сначала создайте дамп.'
        exit 1
    }
    $files = Get-ChildItem -Path $backupDir -Filter '*.sql' | Sort-Object Name -Descending
    if (-not $files) {
        Write-Error 'Не найдено SQL-файлов для восстановления.'
        exit 1
    }
    Write-Host 'Выберите файл для восстановления:'
    for ($i = 0; $i -lt $files.Count; $i++) {
        Write-Host "[$i] $($files[$i].Name)"
    }
    $choice = Read-Host 'Введите номер'
    if (-not ($choice -as [int]) -or [int]$choice -lt 0 -or [int]$choice -ge $files.Count) {
        Write-Error 'Неверный выбор.'
        exit 1
    }
    $File = $files[[int]$choice].FullName
}

if (-not (Test-Path $File)) {
    Write-Error "Файл $File не найден."
    exit 1
}

Write-Host "[FINANSES] Восстанавливаем базу из $File"
$inputData = Get-Content -Path $File -Raw
if ($compose.UseDocker) {
    $inputData | docker compose exec -T db mysql -u root -proot finanses
} else {
    $inputData | docker-compose exec -T db mysql -u root -proot finanses
}

Write-Host '[FINANSES] Восстановление завершено.'
