param(
    [string]$ProjectRoot = (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path))
)

Set-Location $ProjectRoot
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    Write-Error 'Composer не найден. Установите Composer for Windows.'
    exit 1
}
composer install
php database/cli.php migrate
php database/cli.php seed
