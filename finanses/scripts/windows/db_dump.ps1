param(
    [string]$Output
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
if (-not (Test-Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir | Out-Null
}

if (-not $Output) {
    $Output = Join-Path $backupDir ("finanses_{0}.sql" -f (Get-Date -Format 'yyyyMMdd_HHmmss'))
} elseif (-not (Test-Path (Split-Path $Output -Parent))) {
    New-Item -ItemType Directory -Path (Split-Path $Output -Parent) | Out-Null
}

Write-Host "[FINANSES] Создаём дамп в $Output"
$command = @('exec', '-T', 'db', 'mysqldump', '-u', 'root', '-proot', 'finanses')
if ($compose.UseDocker) {
    & docker compose @command | Out-File -FilePath $Output -Encoding utf8
} else {
    & docker-compose @command | Out-File -FilePath $Output -Encoding utf8
}

Write-Host '[FINANSES] Дамп готов.'
