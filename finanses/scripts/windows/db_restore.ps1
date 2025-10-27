param(
    [string]$Database = 'finanses',
    [Parameter(Mandatory = $true)][string]$File
)
& mysql.exe -u root -p $Database < $File
Write-Host 'Восстановление завершено'
