# Run OpenXE DB schema upgrade inside the Docker app container (non-interactive).
# Usage (from project root):  powershell -File docker/upgrade-db.ps1

$ErrorActionPreference = "Stop"
$Root = Split-Path $PSScriptRoot -Parent
Push-Location $Root

Write-Host "Running: php data/upgrade.php -db -do"
docker compose exec app sh -lc "cd /var/www/html/upgrade && php data/upgrade.php -db -do"
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "Done."
Pop-Location
