---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-14
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Cipher]]"
---

# Social Login

Owner: [[90 System/Agents/Cipher|Cipher]]. Review date: **2026-11-14**.

## Implemented in source

- Google/Facebook start and callback routes; single-use OAuth state with a 600-second limit.
- Google PKCE S256, TLS-verified provider requests, and provider-subject SHA-256 lookup in `user_oauth_accounts`.
- Matching email alone does not automatically link an existing account; mapped accounts retain inactive-account and seller-2FA checks.
- Social signup collects mobile number and Terms acceptance, with an optional password checked by the common strength policy.
- Local configuration/setup and offline-validation scripts exist. Google verified-email handling and Facebook verification follow different paths.

## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `backend/social_auth.php`
- `backend/social_auth_service.php`
- `backend/social_signup_complete.php`
- `backend/password_policy.php`
- `account/social_signup.php`
- `includes/social_auth_buttons.php`
- `docs/social-login-development.md`
- `scripts/configure-social-login.ps1`
- `scripts/validate-social-auth-config.php`
- `database/migrations/20261112_create_user_oauth_accounts.sql`

## Verification limits and open items

- Provider credentials, applied OAuth migration, and successful live Google/Facebook callbacks were not verified.
- The service helper test passes; the setup safety test fails its placeholder-targeting assertion. That assertion alone does not establish the script's credential contents.

## Related tests

- `tests/social_auth_service_test.php`
- `tests/configure_social_login_test.ps1`

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Aegis|Aegis]] · [[90 System/Agents/Beacon|Beacon]] · [[90 System/Agents/Vega|Vega]] · [[90 System/Agents/Quinn|Quinn]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]
