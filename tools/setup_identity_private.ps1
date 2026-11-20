param([switch]$RegisterCleanup, [switch]$Enable)
$ErrorActionPreference = 'Stop'
$appRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if ($appRoot -ne 'C:\xampp\htdocs\VNZ-TECH') { throw 'Review deployment paths for this checkout first.' }
$privateRoot = 'C:\xampp\private\VNZ-TECH\identity'
New-Item -ItemType Directory -Path $privateRoot -Force | Out-Null
$currentSid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
function Set-IdentityPrivateAcl([string]$Path) {
    $absolute = [IO.Path]::GetFullPath($Path)
    if ($absolute -ne $privateRoot -and -not $absolute.StartsWith($privateRoot + '\', [StringComparison]::OrdinalIgnoreCase)) { throw 'ACL target outside private identity root.' }
    $item = Get-Item -LiteralPath $absolute -Force
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Private identity paths cannot be reparse points.' }
    $allowed = @($currentSid, 'S-1-5-18', 'S-1-5-32-544')
    $rights = if ($item.PSIsContainer) { '(OI)(CI)F' } else { 'F' }
    & icacls.exe $absolute /inheritance:r /grant:r "*$($currentSid):$rights" "*S-1-5-18:$rights" "*S-1-5-32-544:$rights" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Private DACL update failed.' }
    # Remove old explicit broad grants/denies; preserve owner and SACL metadata.
    foreach ($entry in (Get-Acl -LiteralPath $absolute).Access) {
        $entrySid = $entry.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value
        if ($entrySid -notin $allowed) { & icacls.exe $absolute /remove "*$entrySid" | Out-Null }
        elseif ($entry.AccessControlType -eq 'Deny') { & icacls.exe $absolute /remove:d "*$entrySid" | Out-Null }
        else { continue }
        if ($LASTEXITCODE -ne 0) { throw 'Old private access rule removal failed.' }
    }
    $actual = Get-Acl -LiteralPath $absolute
    if (-not $actual.AreAccessRulesProtected) { throw 'Private ACL inheritance remains enabled.' }
    foreach ($entry in $actual.Access) {
        if ($entry.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value -notin $allowed -or $entry.AccessControlType -ne 'Allow') { throw 'Unexpected identity access rule remains.' }
    }
}
Set-IdentityPrivateAcl $privateRoot
New-Item -ItemType Directory -Path (Join-Path $privateRoot 'evidence') -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $privateRoot 'backups') -Force | Out-Null
$keyPath = Join-Path $privateRoot 'key.bin'
if (-not (Test-Path -LiteralPath $keyPath)) {
    if (Get-ChildItem -LiteralPath (Join-Path $privateRoot 'evidence') -File -Filter '*.enc' | Select-Object -First 1) { throw 'Encryption key missing while evidence exists; recover the original key.' }
    $keyBytes = New-Object byte[] 32
    $keyRng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $keyRng.GetBytes($keyBytes)
        $keyStream = [IO.File]::Open($keyPath, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
        try { $keyStream.Write($keyBytes, 0, $keyBytes.Length) } finally { $keyStream.Dispose() }
    }
    finally { $keyRng.Dispose(); [Array]::Clear($keyBytes, 0, $keyBytes.Length) }
}
if ((Get-Item -LiteralPath $keyPath).Length -ne 32) { throw 'Existing key is not 32 bytes; it was not overwritten.' }
$configPath = Join-Path $privateRoot 'config.php'
$configOff = @'
<?php
return [
    'enabled' => false,
    'storage_path' => 'C:/xampp/private/VNZ-TECH/identity',
    'key_path' => 'C:/xampp/private/VNZ-TECH/identity/key.bin',
];
'@
$utf8 = New-Object Text.UTF8Encoding($false)
if (-not (Test-Path -LiteralPath $configPath)) { [IO.File]::WriteAllText($configPath, $configOff, $utf8) }
foreach ($directory in @($privateRoot, (Join-Path $privateRoot 'evidence'), (Join-Path $privateRoot 'backups'))) {
    Set-IdentityPrivateAcl $directory
    foreach ($file in Get-ChildItem -LiteralPath $directory -File -Force) { Set-IdentityPrivateAcl $file.FullName }
}
$taskName = 'VNZ-TECH Identity Evidence Cleanup'
function Assert-IdentityCleanupTask($Task) {
    $cleanupFile = Join-Path $appRoot 'backend\identity_evidence_cleanup.php'
    if (-not $Task -or $Task.State -eq 'Disabled' -or -not $Task.Settings.Enabled -or @($Task.Actions).Count -ne 1 -or $Task.Actions.Execute -ne 'C:\xampp\php\php.exe' -or $Task.Actions.Arguments -ne ('"' + $cleanupFile + '"') -or $Task.Actions.WorkingDirectory -ne $appRoot) { throw 'Cleanup task action or enabled state differs; activation denied.' }
    $triggers = @($Task.Triggers)
    if ($triggers.Count -ne 1 -or $triggers[0].CimClass.CimClassName -ne 'MSFT_TaskDailyTrigger' -or $triggers[0].DaysInterval -ne 1 -or -not $triggers[0].Enabled -or ([datetime]$triggers[0].StartBoundary).TimeOfDay -ne (New-TimeSpan -Hours 3)) { throw 'Cleanup task must run daily at 03:00.' }
    $principalId = $Task.Principal.UserId
    if ($principalId -notin @('SYSTEM', 'S-1-5-18', 'NT AUTHORITY\SYSTEM', $currentSid, [Security.Principal.WindowsIdentity]::GetCurrent().Name)) { throw 'Unexpected cleanup principal.' }
}
if ($RegisterCleanup) {
    $cleanupPath = Join-Path $appRoot 'backend\identity_evidence_cleanup.php'
    if (-not (Test-Path -LiteralPath $cleanupPath)) { throw 'Cleanup CLI must exist first.' }
    if ((Get-TimeZone).Id -ne 'Singapore Standard Time') { throw 'Review scheduler timezone; requires Asia/Manila (Windows Singapore Standard Time).' }
    $existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
    if ($existingTask) {
        Assert-IdentityCleanupTask $existingTask
    } else {
        $action = New-ScheduledTaskAction -Execute 'C:\xampp\php\php.exe' -Argument ('"' + $cleanupPath + '"') -WorkingDirectory $appRoot
        $trigger = New-ScheduledTaskTrigger -Daily -At '03:00'
        $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
        $settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 10)
        try {
            Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description 'Delete expired encrypted VNZ-TECH identity evidence only.' | Out-Null
        } catch {
            # A normal desktop token cannot create SYSTEM tasks. Try least privilege;
            # no stored password or interactive elevation prompt is introduced.
            $desktopPrincipal = New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
            Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $desktopPrincipal -Settings $settings -Description 'Delete expired encrypted VNZ-TECH identity evidence only; runs when this desktop account is signed in.' | Out-Null
        }
    }
    Assert-IdentityCleanupTask (Get-ScheduledTask -TaskName $taskName)
    $startedAt = Get-Date
    $startedUnix = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    $previousRunTime = (Get-ScheduledTaskInfo -TaskName $taskName).LastRunTime
    $previousHeartbeat = Join-Path $privateRoot 'cleanup-heartbeat.json'
    $previousHeartbeatWrite = if (Test-Path -LiteralPath $previousHeartbeat) { (Get-Item -LiteralPath $previousHeartbeat).LastWriteTimeUtc } else { [datetime]::MinValue }
    Start-ScheduledTask -TaskName $taskName
    $cleanupFinished = $false
    for ($attemptNumber = 0; $attemptNumber -lt 100; $attemptNumber++) {
        Start-Sleep -Milliseconds 100
        $runningTask = Get-ScheduledTask -TaskName $taskName
        $runningInfo = Get-ScheduledTaskInfo -TaskName $taskName
        $heartbeatPath = Join-Path $privateRoot 'cleanup-heartbeat.json'
        if ($runningTask.State -ne 'Running' -and $runningInfo.LastTaskResult -eq 0 -and $runningInfo.LastRunTime -ge $startedAt.AddSeconds(-1) -and (Test-Path -LiteralPath $heartbeatPath)) {
            $heartbeatData = Get-Content -LiteralPath $heartbeatPath -Raw | ConvertFrom-Json
            $freshRun = $runningInfo.LastRunTime -gt $previousRunTime -and (Get-Item -LiteralPath $heartbeatPath).LastWriteTimeUtc -gt $previousHeartbeatWrite
            if ($freshRun -and $heartbeatData.success_at -ge ($startedUnix - 1)) { $cleanupFinished = $true; break }
        }
    }
    if (-not $cleanupFinished) { throw 'Scheduled cleanup did not finish successfully; capture stays disabled.' }
}
if ($Enable) {
    $task = Get-ScheduledTask -TaskName $taskName -ErrorAction Stop
    Assert-IdentityCleanupTask $task
    $taskInfo = Get-ScheduledTaskInfo -TaskName $taskName
    $heartbeat = Join-Path $privateRoot 'cleanup-heartbeat.json'
    if (-not (Test-Path -LiteralPath $heartbeat)) { throw 'Successful cleanup heartbeat missing.' }
    if ((Get-Date) - (Get-Item -LiteralPath $heartbeat).LastWriteTime -gt (New-TimeSpan -Hours 48)) { throw 'Cleanup heartbeat is stale.' }
    if ($taskInfo.LastTaskResult -ne 0) { throw 'Scheduled cleanup has not completed successfully.' }
    $current = [IO.File]::ReadAllText($configPath)
    if (-not $current.Contains("'enabled' => false") -and -not $current.Contains("'enabled' => true")) { throw 'Review existing custom config before activation.' }
    [IO.File]::WriteAllText((Join-Path $appRoot 'backend\identity_required.flag'), "Required on this local instance. Missing configuration must fail closed.`n", $utf8)
    [IO.File]::WriteAllText($configPath, $current.Replace("'enabled' => false", "'enabled' => true"), $utf8)
}
[pscustomobject]@{ private_root = $privateRoot; key_exists = (Test-Path -LiteralPath $keyPath); cleanup_requested = [bool]$RegisterCleanup; enable_requested = [bool]$Enable } | ConvertTo-Json -Compress
