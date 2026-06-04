# Quick HTTP benchmark for local OpenXE Docker (run on the host).
# Usage: powershell -File docker/benchmark.ps1

$Base = if ($env:OPENXE_BENCH_URL) { $env:OPENXE_BENCH_URL } else { "http://127.0.0.1:8081" }
$Urls = @(
    "$Base/",
    "$Base/www/"
)

Write-Host "OpenXE Docker benchmark - $Base"
Write-Host ""

foreach ($Url in $Urls) {
    $samples = @()
    1..5 | ForEach-Object {
        $m = curl.exe -s -o NUL -w "%{time_total}" $Url
        if ($LASTEXITCODE -ne 0) {
            Write-Host "FAIL $Url (curl exit $LASTEXITCODE)"
            continue 2
        }
        $samples += [double]$m
    }
    $avg = ($samples | Measure-Object -Average).Average
    $min = ($samples | Measure-Object -Minimum).Minimum
    $max = ($samples | Measure-Object -Maximum).Maximum
    Write-Host ("{0,-40} avg={1:N3}s min={2:N3}s max={3:N3}s" -f $Url, $avg, $min, $max)
}

Write-Host ""
Write-Host "Container resources:"
docker stats --no-stream 2>$null | Select-String "openxe-github-fork"
