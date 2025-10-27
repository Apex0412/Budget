param(
    [string]$Database = 'finanses',
    [string]$Output = "finanses_$(Get-Date -Format yyyyMMdd_HHmm).sql"
)
& mysqldump.exe -u root -p $Database > $Output
Write-Host "Дамп сохранен в $Output"
