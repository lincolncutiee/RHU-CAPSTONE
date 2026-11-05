# Fix XAMPP MySQL "shutdown unexpectedly" when InnoDB log files are out of sync.
# USE ONLY when MySQL is STOPPED in XAMPP Control Panel.
$ErrorActionPreference = "Stop"

$dataDir = "C:\xampp\mysql\data"
$backupDir = Join-Path $dataDir ("innodb_log_backup_" + (Get-Date -Format "yyyyMMdd_HHmmss"))

$log0 = Join-Path $dataDir "ib_logfile0"
$log1 = Join-Path $dataDir "ib_logfile1"
$ibtmp = Join-Path $dataDir "ibtmp1"

$mysqlRunning = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue
if ($mysqlRunning) {
    Write-Host "ERROR: mysqld is still running. Stop MySQL in XAMPP first, then run this script again." -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $dataDir)) {
    Write-Host "ERROR: Data folder not found: $dataDir"
    exit 1
}

New-Item -ItemType Directory -Path $backupDir | Out-Null
Write-Host "Backup folder: $backupDir"

foreach ($f in @($log0, $log1, $ibtmp)) {
    if (Test-Path $f) {
        Copy-Item $f $backupDir
        Remove-Item $f -Force
        Write-Host "Removed $(Split-Path $f -Leaf) (backup saved)."
    }
}

Write-Host ""
Write-Host "Done. Next steps:" -ForegroundColor Green
Write-Host "  1. Open XAMPP Control Panel -> Start MySQL"
Write-Host "  2. If it starts, open phpMyAdmin and check rhu_management"
Write-Host "  3. In C:\xampp\mysql\bin\my.ini remove or comment innodb_force_recovery=1 after stable start"
Write-Host ""
Write-Host "If MySQL still fails, restore from backup folder above and ask for help with mysql_error.log tail."
