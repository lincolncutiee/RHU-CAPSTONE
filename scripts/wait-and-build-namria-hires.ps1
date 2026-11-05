# Waits for HDX shapefile download, then builds magallanes-barangays-namria.geojson
$root = Split-Path -Parent $PSScriptRoot
$shpZip = Join-Path $root "assets\geo\_hdx_phl_admin_boundaries.shp.zip"
$minBytes = 800000000
$php = "c:\xampp\php\php.exe"
if (-not (Test-Path $php)) { $php = "php" }

Write-Host "Waiting for HDX shapefile zip (>= $minBytes bytes)..."
while ($true) {
    if ((Test-Path $shpZip) -and ((Get-Item $shpZip).Length -ge $minBytes)) { break }
    $part = "$shpZip.part"
    if (Test-Path $part) {
        $mb = [math]::Round((Get-Item $part).Length / 1MB, 1)
        Write-Host "  Downloaded ${mb} MB..."
    }
    Start-Sleep -Seconds 60
}

Write-Host "Running build..."
& $php (Join-Path $PSScriptRoot "build-magallanes-namria-hires.php")
& $php (Join-Path $PSScriptRoot "validate-magallanes-boundaries.php")
Write-Host "Done."
