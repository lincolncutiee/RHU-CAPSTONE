# Recreate corrupted mysql SYSTEM database (privilege tables). Keeps rhu_management and other DB folders.
# Stop MySQL in XAMPP first.
$ErrorActionPreference = "Stop"
$data = "C:\xampp\mysql\data"
$mysqlSys = Join-Path $data "mysql"
$installDb = "C:\xampp\mysql\bin\mysql_install_db.exe"

if (Get-Process -Name "mysqld" -ErrorAction SilentlyContinue) {
    Write-Host "Stopping mysqld..."
    Get-Process -Name "mysqld" | Stop-Process -Force
    Start-Sleep -Seconds 2
}

if (-not (Test-Path $installDb)) {
    Write-Host "ERROR: $installDb not found"
    exit 1
}

if (Test-Path $mysqlSys) {
    $bak = Join-Path $data ("mysql_corrupt_" + (Get-Date -Format "yyyyMMdd_HHmmss"))
    Rename-Item $mysqlSys $bak
    Write-Host "Renamed broken system DB to: $bak"
}

$tmp = "C:\xampp\mysql\_fresh_init"
if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
New-Item -ItemType Directory -Path $tmp | Out-Null

Push-Location "C:\xampp\mysql\bin"
& .\mysql_install_db.exe --datadir="C:/xampp/mysql/_fresh_init"
if ($LASTEXITCODE -ne 0) {
    Pop-Location
    Write-Host "ERROR: mysql_install_db failed. Restore mysql folder from mysql_corrupt_* backup if needed." -ForegroundColor Red
    exit 1
}
Pop-Location

Copy-Item (Join-Path $tmp "mysql") $mysqlSys -Recurse -Force
Remove-Item $tmp -Recurse -Force

Write-Host ""
Write-Host "System tables recreated. Start MySQL in XAMPP." -ForegroundColor Green
Write-Host "Root password is empty (default XAMPP). User databases (e.g. rhu_management) were NOT deleted."
