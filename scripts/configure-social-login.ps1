$ErrorActionPreference = 'Stop'

function ConvertTo-PhpString([string]$Value) {
    return "'" + $Value.Replace('\', '\\').Replace("'", "\'") + "'"
}

function Set-RestrictedConfigAcl([string]$Path) {
    $currentIdentity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $expectedSids = @($currentIdentity.User.Value, 'S-1-5-18', 'S-1-5-32-544')
    $acl = [IO.File]::GetAccessControl($Path)
    $acl.SetAccessRuleProtection($true, $false)
    foreach ($rule in @($acl.Access)) {
        [void]$acl.RemoveAccessRuleAll($rule)
    }
    foreach ($principal in @($currentIdentity.Name, 'SYSTEM', 'BUILTIN\Administrators')) {
        $rule = [Security.AccessControl.FileSystemAccessRule]::new(
            $principal,
            [Security.AccessControl.FileSystemRights]::FullControl,
            [Security.AccessControl.AccessControlType]::Allow
        )
        [void]$acl.AddAccessRule($rule)
    }
    [IO.File]::SetAccessControl($Path, $acl)

    $sections = [Security.AccessControl.AccessControlSections]::Access `
        -bor [Security.AccessControl.AccessControlSections]::Owner
    $verified = [IO.File]::GetAccessControl($Path, $sections)
    $ownerSid = [Security.Principal.NTAccount]::new([string]$verified.Owner).Translate(
        [Security.Principal.SecurityIdentifier]
    ).Value
    if (
        -not $verified.AreAccessRulesProtected `
        -or $ownerSid -cne $currentIdentity.User.Value
    ) {
        throw 'Social login config owner or ACL inheritance is invalid.'
    }
    $actualSids = @(
        foreach ($rule in $verified.Access) {
            if (
                $rule.AccessControlType -ne [Security.AccessControl.AccessControlType]::Allow `
                -or $rule.IsInherited `
                -or ($rule.FileSystemRights -band [Security.AccessControl.FileSystemRights]::FullControl) `
                    -ne [Security.AccessControl.FileSystemRights]::FullControl
            ) {
                throw 'Social login config has an unexpected ACL entry.'
            }
            $rule.IdentityReference.Translate(
                [Security.Principal.SecurityIdentifier]
            ).Value
        }
    )
    if ((@($actualSids | Sort-Object -Unique) -join '|') -cne (@($expectedSids | Sort-Object -Unique) -join '|')) {
        throw 'Social login config ACL does not match the restricted local policy.'
    }
}

$repositoryRoot = Split-Path -Parent $PSScriptRoot
$php = 'C:\xampp\php\php.exe'
$templatePath = Join-Path $repositoryRoot 'backend\social_auth_config.example.php'
$configPath = Join-Path $repositoryRoot 'backend\social_auth_config.local.php'
$validatorPath = Join-Path $repositoryRoot 'scripts\validate-social-auth-config.php'

if (-not (Test-Path -LiteralPath $php -PathType Leaf) -or -not (Test-Path -LiteralPath $templatePath -PathType Leaf)) {
    throw 'PHP CLI or the social login template is unavailable.'
}
if (Test-Path -LiteralPath $configPath) {
    throw 'Social login config already exists; refusing to overwrite it.'
}

$googleClientId = Read-Host 'Google OAuth client ID'
$facebookClientId = Read-Host 'Facebook app ID'
$graphVersion = Read-Host 'Facebook Graph API version [v27.0]'
if ([string]::IsNullOrWhiteSpace($graphVersion)) {
    $graphVersion = 'v27.0'
}
$googleSecretSecure = Read-Host 'Google OAuth client secret (hidden)' -AsSecureString
$facebookSecretSecure = Read-Host 'Facebook app secret (hidden)' -AsSecureString
$googleSecretPointer = [IntPtr]::Zero
$facebookSecretPointer = [IntPtr]::Zero
$googleSecret = $null
$facebookSecret = $null
$configText = $null
$createdConfig = $false

try {
    $invalidInput = [string]::IsNullOrWhiteSpace($googleClientId) `
        -or [string]::IsNullOrWhiteSpace($facebookClientId) `
        -or $graphVersion -notmatch '^v[0-9]+\.[0-9]+$'
    if ($invalidInput) {
        throw 'Google/Facebook client IDs and a valid Facebook Graph API version are required.'
    }

    $googleSecretPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($googleSecretSecure)
    $facebookSecretPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($facebookSecretSecure)
    $googleSecret = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($googleSecretPointer)
    $facebookSecret = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($facebookSecretPointer)
    if ([string]::IsNullOrWhiteSpace($googleSecret) -or [string]::IsNullOrWhiteSpace($facebookSecret)) {
        throw 'OAuth client secrets cannot be empty.'
    }

    $configText = Get-Content -LiteralPath $templatePath -Raw
    $configText = $configText.Replace(
        "'REPLACE_WITH_GOOGLE_OAUTH_CLIENT_ID'",
        (ConvertTo-PhpString $googleClientId)
    )
    $configText = $configText.Replace(
        "'REPLACE_WITH_GOOGLE_OAUTH_CLIENT_SECRET'",
        (ConvertTo-PhpString $googleSecret)
    )
    $configText = $configText.Replace(
        "'REPLACE_WITH_FACEBOOK_APP_ID'",
        (ConvertTo-PhpString $facebookClientId)
    )
    $configText = $configText.Replace(
        "'REPLACE_WITH_FACEBOOK_APP_SECRET'",
        (ConvertTo-PhpString $facebookSecret)
    )
    $configText = $configText.Replace("'v27.0'", (ConvertTo-PhpString $graphVersion))
    if ($configText -match 'REPLACE_WITH_') {
        throw 'The social login config template contains an unresolved placeholder.'
    }

    $stream = [IO.File]::Open($configPath, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
    $createdConfig = $true
    try {
        $bytes = [Text.UTF8Encoding]::new($false).GetBytes($configText)
        $stream.Write($bytes, 0, $bytes.Length)
        [Array]::Clear($bytes, 0, $bytes.Length)
    } finally {
        $stream.Dispose()
    }
    Set-RestrictedConfigAcl $configPath
    & $php -d display_errors=0 -d log_errors=0 $validatorPath
    if ($LASTEXITCODE -ne 0) {
        throw 'Social login config validation failed.'
    }
    Write-Output 'Protected local social login config created and validated. No database connection made.'
} catch {
    if ($createdConfig -and (Test-Path -LiteralPath $configPath -PathType Leaf)) {
        Remove-Item -LiteralPath $configPath -Force
    }
    throw
} finally {
    foreach ($pointer in @($googleSecretPointer, $facebookSecretPointer)) {
        if ($pointer -ne [IntPtr]::Zero) {
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
        }
    }
    if ($null -ne $googleSecretSecure) {
        $googleSecretSecure.Dispose()
    }
    if ($null -ne $facebookSecretSecure) {
        $facebookSecretSecure.Dispose()
    }
    $googleSecret = $null
    $facebookSecret = $null
    $configText = $null
}
