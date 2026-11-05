# Repair XAMPP MariaDB system tables (e.g. "Incorrect file format 'db'").
# Stop MySQL in XAMPP before running.
$ErrorActionPreference = "Stop"
$bin = "C:\xampp\mysql\bin"
$data = "C:\xampp\mysql\data"
$mysqlDb = Join-Path $data "mysql"

if (Get-Process -Name "mysqld" -ErrorAction SilentlyContinue) {
    Write-Host "Stopping mysqld..." -ForegroundColor Yellow
    Get-Process -Name "mysqld" | Stop-Process -Force
    Start-Sleep -Seconds 2
}

$ariaChk = Join-Path $bin "aria_chk.exe"
if (Test-Path $ariaChk) {
    Write-Host "Repairing Aria tables in mysql system database..."
    & $ariaChk -r (Join-Path $mysqlDb "*.MAI")
}

foreach ($f in @("aria_log_control", "aria_log.00000001")) {
    $p = Join-Path $data $f
    if (Test-Path $p) {
        $bak = Join-Path $data ("_bak_" + $f + "_" + (Get-Date -Format "HHmmss"))
        Move-Item $p $bak -Force
        Write-Host "Moved $f to backup name."
    }
}

Write-Host ""
Write-Host "Done. Start MySQL from XAMPP Control Panel." -ForegroundColor Green
