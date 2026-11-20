param([ValidateSet('us', 'cn')][string]$Region = 'us')
$ErrorActionPreference = 'Stop'
# Run locally as the account that can access the protected private config.
# Keys are prompted securely; no command-line keys, transcript output or vault copies.
$appRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if ($appRoot -ne 'C:\xampp\htdocs\VNZ-TECH') { throw 'Review deployment paths for this checkout first.' }
$phpBinary = 'C:\xampp\php\php.exe'
$setupScript = Join-Path $PSScriptRoot 'activate_identity_dual.php'
if (-not (Test-Path -LiteralPath $phpBinary) -or -not (Test-Path -LiteralPath $setupScript)) { throw 'Required local setup executable is missing.' }
$apiKey = Read-Host 'AFjfEy5EylecCy-Ec6oqdoXJCwDyQpid' -AsSecureString
$apiSecret = Read-Host 'XVAGZrvgM_LGbiyMFmc_IkmllfUUG5Xp' -AsSecureString
$keyPointer = [IntPtr]::Zero
$secretPointer = [IntPtr]::Zero
$process = $null
try {
    $keyPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($apiKey)
    $secretPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($apiSecret)
    $payload = @{
        region = $Region
        api_key = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($keyPointer)
        api_secret = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($secretPointer)
    } | ConvertTo-Json -Compress
    $start = New-Object Diagnostics.ProcessStartInfo
    $start.FileName = $phpBinary
    $start.Arguments = '-d display_errors=0 -d log_errors=0 "' + $setupScript + '" --prepare'
    $start.WorkingDirectory = $appRoot
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardInput = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $process = New-Object Diagnostics.Process
    $process.StartInfo = $start
    if (-not $process.Start()) { throw 'Private configuration process could not start.' }
    $process.StandardInput.Write($payload)
    $process.StandardInput.Close()
    if (-not $process.WaitForExit(15000)) { $process.Kill(); throw 'Private configuration timed out.' }
    # Child output is intentionally not echoed; only fixed success/failure text leaves this wrapper.
    $null = $process.StandardOutput.ReadToEnd()
    $null = $process.StandardError.ReadToEnd()
    if ($process.ExitCode -ne 0) { throw 'Private configuration failed. Review the protected path/permissions and credentials locally.' }
    Write-Output 'Face++ credentials saved privately. Existing mode preserved; provider/matching readiness remains unconfirmed. No image sent and no activation performed.'
} finally {
    $payload = $null
    if ($keyPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($keyPointer) }
    if ($secretPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($secretPointer) }
    if ($apiKey) { $apiKey.Dispose() }
    if ($apiSecret) { $apiSecret.Dispose() }
    if ($process) { $process.Dispose() }
}
