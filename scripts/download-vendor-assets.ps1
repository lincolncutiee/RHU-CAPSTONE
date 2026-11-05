# Download Bootstrap, Icons, SweetAlert2 for offline use. Run once while online.
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$vendor = Join-Path $root "assets\vendor"

$dirs = @(
    "bootstrap\5.3.3\css",
    "bootstrap\5.3.3\js",
    "bootstrap-icons\1.11.3\fonts",
    "sweetalert2\11"
)
foreach ($d in $dirs) {
    New-Item -ItemType Directory -Force -Path (Join-Path $vendor $d) | Out-Null
}

$files = @{
    "bootstrap\5.3.3\css\bootstrap.min.css" = "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    "bootstrap\5.3.3\js\bootstrap.bundle.min.js" = "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    "bootstrap-icons\1.11.3\bootstrap-icons.min.css" = "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    "bootstrap-icons\1.11.3\fonts\bootstrap-icons.woff2" = "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2"
    "bootstrap-icons\1.11.3\fonts\bootstrap-icons.woff" = "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff"
    "sweetalert2\11\sweetalert2.min.css" = "https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"
    "sweetalert2\11\sweetalert2.all.min.js" = "https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"
}

foreach ($entry in $files.GetEnumerator()) {
    $dest = Join-Path $vendor $entry.Key
    Write-Host "Downloading $($entry.Key)..."
    Invoke-WebRequest -Uri $entry.Value -OutFile $dest -UseBasicParsing
}

# Fix bootstrap-icons font paths (npm package uses ./fonts/)
$iconsCss = Join-Path $vendor "bootstrap-icons\1.11.3\bootstrap-icons.min.css"
$content = Get-Content $iconsCss -Raw
$content = $content -replace 'url\("\./fonts/', 'url("./fonts/'
Set-Content -Path $iconsCss -Value $content -NoNewline

Write-Host "Done. Vendor assets are in assets/vendor/"
