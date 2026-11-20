$ErrorActionPreference = 'Stop'
# Run as Administrator only after reviewing the approved implementation/checks.
$setupPath = Join-Path $PSScriptRoot 'setup_identity_private.ps1'
$resultPath = 'C:\xampp\private\VNZ-TECH\identity\setup-result.json'
try {
    & $setupPath -RegisterCleanup
    & 'C:\xampp\php\php.exe' (Join-Path $PSScriptRoot 'deploy_identity.php') health
    if ($LASTEXITCODE -ne 0) { throw 'Private identity health check failed; feature stays disabled.' }
    & $setupPath -Enable
    [IO.File]::WriteAllText($resultPath, (@{ success = $true; recorded_at = [DateTimeOffset]::Now.ToString('o') } | ConvertTo-Json -Compress))
    Write-Output 'Identity verification enabled; cleanup schedule, private storage and local health verified.'
} catch {
    if (Test-Path -LiteralPath (Split-Path $resultPath)) {
        [IO.File]::WriteAllText($resultPath, (@{ success = $false; recorded_at = [DateTimeOffset]::Now.ToString('o'); message = 'Local setup failed; inspect administrator task permissions and private setup.' } | ConvertTo-Json -Compress))
    }
    throw
}
