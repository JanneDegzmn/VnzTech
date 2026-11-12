$ErrorActionPreference = 'Stop'
$repositoryRoot = Split-Path -Parent $PSScriptRoot
$setup = Get-Content -LiteralPath (Join-Path $repositoryRoot 'scripts\configure-social-login.ps1') -Raw

$checks = @(
    @{
        Condition = $setup.Contains('Read-Host ''Google OAuth client secret (hidden)'' -AsSecureString') `
            -and $setup.Contains('Read-Host ''Facebook app secret (hidden)'' -AsSecureString')
        Message = 'Provider secrets must be requested using hidden input.'
    },
    @{
        Condition = $setup.Contains('[IO.FileMode]::CreateNew') `
            -and $setup.Contains('refusing to overwrite it') `
            -and $setup.Contains('SetAccessRuleProtection($true, $false)') `
            -and $setup.Contains('AreAccessRulesProtected') `
            -and $setup.Contains('$ownerSid -cne $currentIdentity.User.Value')
        Message = 'Setup must refuse overwrite and protect the local config ACL.'
    },
    @{
        Condition = $setup.Contains('validate-social-auth-config.php') `
            -and $setup.Contains('No database connection made')
        Message = 'Setup must validate offline without connecting to a database.'
    },
    @{
        Condition = $setup.Contains("'REPLACE_WITH_GOOGLE_OAUTH_CLIENT_ID'") `
            -and $setup.Contains("'REPLACE_WITH_GOOGLE_OAUTH_CLIENT_SECRET'") `
            -and $setup.Contains("'REPLACE_WITH_FACEBOOK_APP_ID'") `
            -and $setup.Contains("'REPLACE_WITH_FACEBOOK_APP_SECRET'")
        Message = 'Setup must target template placeholders rather than literal provider credentials.'
    }
)

foreach ($check in $checks) {
    if (-not $check.Condition) {
        throw $check.Message
    }
}

Write-Output 'Social login setup safety checks passed.'
