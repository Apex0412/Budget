param(
    [switch]$All
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

if ($compose.UseDocker) {
    docker compose down --volumes --remove-orphans
} else {
    docker-compose down --volumes --remove-orphans
}

if ($All.IsPresent) {
    Write-Host '[FINANSES] Полная очистка docker system prune -a --volumes -f'
    docker system prune -a --volumes -f
}

Write-Host '[FINANSES] Очистка завершена.'
