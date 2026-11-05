# Run BEFORE starting MySQL in XAMPP when you see "shutdown unexpectedly".
# Fixes recurring Aria privilege-table corruption + stray replication junk files.
$ErrorActionPreference = "Continue"
$bin = "C:\xampp\mysql\bin"
$data = "C:\xampp\mysql\data"
$mysqlDb = Join-Path $data "mysql"

if (Get-Process -Name "mysqld" -ErrorAction SilentlyContinue) {
    Write-Host "Stopping mysqld..." -ForegroundColor Yellow
    Get-Process -Name "mysqld" | Stop-Process -Force
    Start-Sleep -Seconds 3
}

# Crash dumps sometimes create bogus master/relay files in datadir — breaks next boot.
$junkPatterns = @(
    "master-*", "relay-log-*",
    "multi-master.info", "master.info", "relay-log.info"
)
$removed = 0
foreach ($pat in $junkPatterns) {
    Get-ChildItem -Path $data -Filter $pat -File -ErrorAction SilentlyContinue | ForEach-Object {
        Remove-Item -LiteralPath $_.FullName -Force
        $removed++
    }
}
if ($removed -gt 0) {
    Write-Host "Removed $removed replication junk file(s)."
}

$ariaChk = Join-Path $bin "aria_chk.exe"
if (-not (Test-Path $ariaChk)) {
    Write-Host "ERROR: aria_chk not found at $ariaChk"
    exit 1
}

Push-Location $data
try {
    Write-Host "Repairing Aria system tables (mysql)..."
    & $ariaChk -r --sort_buffer_size=256M (Join-Path $mysqlDb "*.MAI")
    foreach ($table in @("proxies_priv", "tables_priv", "columns_priv", "help_topic")) {
        $mai = Join-Path $mysqlDb "$table.MAI"
        if (Test-Path $mai) {
            & $ariaChk -o -f --sort_buffer_size=256M $mai 2>$null | Out-Null
        }
    }
}
finally {
    Pop-Location
}

foreach ($f in @("aria_log_control", "aria_log.00000001")) {
    $p = Join-Path $data $f
    if (Test-Path $p) {
        $bak = Join-Path $data ("_bak_" + $f + "_" + (Get-Date -Format "yyyyMMdd_HHmmss"))
        Move-Item $p $bak -Force
        Write-Host "Reset Aria log: $f -> backup"
    }
}

Write-Host ""
Write-Host "Preflight done. Start MySQL in XAMPP (wait until green)." -ForegroundColor Green
Write-Host "Tip: Stop MySQL in XAMPP before shutting down Windows; avoid force-killing mysqld."
