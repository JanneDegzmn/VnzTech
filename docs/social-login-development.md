# Local Development social login

This adds Google and Facebook OAuth to seller login and signup on the local
XAMPP application. It does not configure provider credentials, run a database
migration, or alter Staging/Production.

## Provider callback configuration

Register this exact callback URL in both OAuth applications:

`http://localhost/VNZ-TECH/backend/social_auth.php`

The login buttons appear only on localhost. Sign-in remains unavailable until
the local provider config is installed. Staging and Production callbacks and
credentials are outside this change.

For Google, create a Web application OAuth client and allow the local origin
`http://localhost`. For Facebook, enable Facebook Login and add the callback to
Valid OAuth Redirect URIs. The Facebook Graph API version defaults to `v27.0`; keep it aligned with a
version enabled for the Meta app.

## Protected local credentials

Run the local setup script in PowerShell:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\xampp\htdocs\VNZ-TECH\scripts\configure-social-login.ps1"
```

Enter the client IDs and app ID at the visible prompts and the client secrets
at the hidden prompts. The script creates the ignored local file
`backend/social_auth_config.local.php`, restricts its Windows ACL to the
current user, SYSTEM, and local Administrators, and validates it without a
database connection. Never commit or share that local file.

The offline validator can be run again with:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\VNZ-TECH\scripts\validate-social-auth-config.php
```

## Required Development database migration

The login flow stores a SHA-256 hash of each provider's stable user ID in
`user_oauth_accounts`. Before enabling the buttons, apply
`database/migrations/20261112_create_user_oauth_accounts.sql` to the local
Development MySQL database using the project's normal database backup and
migration process. This migration has not been run by this change.

## Account behavior and protections

- Google email must be provider-verified. Facebook signup uses the existing
  email-verification flow because the Graph profile does not expose a reliable
  verified-email assertion.
- Social signup still requires a mobile number and explicit Terms acceptance.
- Social signup lets the user skip password creation. If supplied, a password
  must be Medium or Strong and is stored in `users.password_hash`, so the same
  email and password can be used on normal seller sign-in. Medium is 8+
  characters with three character types; Strong is 16+ characters with
  uppercase, lowercase, a number, and a symbol.
- If password creation is skipped, a random inaccessible password is hashed;
  the user can continue with the provider and set a password later through the
  existing reset flow after verifying their email.
- Regular seller signup still requires at least a Medium-strength password.
- Existing accounts are not automatically linked by matching email. A matching
  email must use the existing sign-in method; an authenticated account-linking
  flow is not included.
- Account recovery remains through the existing password-reset flow.
- Existing mapped accounts retain inactive-account and seller 2FA checks.
- OAuth state is single-use and time-limited; Google uses PKCE. Provider calls
  require TLS certificate verification, and tokens/secrets are not logged.
