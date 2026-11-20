---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-19
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Aegis]]"
---

# Identity and Accounts

Current local behavior: [[90 System/Runs/2026-11-19 213300 Submission Selfie v6|submission selfie v6]], active2026-11-19. Earlier dated ID-review sections below remain history.

Owner: [[90 System/Agents/Aegis|Aegis]]. Review date: **2026-11-14**.

## Implemented in source

- Active-admin checks at role-based permissions sa `getCurrentAdmin`, `hasPermission`, at `requirePermission`.
- Strict cookie-only sessions, HttpOnly, SameSite=Lax, HTTPS-dependent Secure flag, at hiwalay na seller/admin session cleanup.
- CSRF token generation at timing-safe comparison; password-policy helper at login/register/update routes.
- Admin TOTP/recovery helpers, seller setup/confirmation/disable/login-requirement actions, at remember-login issue/revoke/restore functions.
- Guest ownership-linking logic checks verified seller identity and preserves conversation context; its eight regression cases now pass after the missing fixture column was restored.
- Same-session guest-to-seller signup retains a seller/email-bound onboarding marker after registration prefill is cleared. The marker only controls presentation; ownership still requires the existing verified-email linker.
- The authenticated onboarding status endpoint checks the actual owner of the last server-recorded submission. Dismiss/acknowledge use CSRF; no guest submission details are returned before verification.
- Successful password registration now returns HTTP 303 to the login page with signup confirmation and the actual email-send status. The seller remains signed out until login, and the guest-onboarding marker is retained. Legacy registration-success URLs also redirect to login.
- Verification results now create seller-bound session feedback after a successful commit. A success query alone cannot create a confirmation, and feedback requires current database verification. Signed-in invalid/used links retain visible feedback instead of losing it through the login redirect.
- A verified guest-onboarding session has a separate once-only confirmation flag, so dismissing the original claim prompt does not suppress the later verification popup. Stale tabs cannot consume newer feedback events.
- Remember-token issuance now returns a success flag and treats database persistence failure as a session-only credential login, with no persistent cookie issued. Token restoration completes its database update before setting authenticated session fields and clears the cookie on database failure.
- Seller/admin dashboard logout links now ask for confirmation before navigating to their existing logout endpoints. Cancel, Escape, and backdrop dismissal stay on the current page; session/remember-token cleanup begins only after confirmed navigation. This is an interface confirmation; logout endpoints were not changed.

## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `backend/auth.php`
- `backend/session.php`
- `backend/csrf.php`
- `backend/login.php`
- `admin/login.php`
- `backend/admin_totp.php`
- `backend/seller_2fa.php`
- `backend/verify_seller_2fa.php`
- `backend/remember_login.php`
- `account/forgot_password.php`
- `account/reset_password.php`
- `backend/guest_submission_link.php`
- `backend/guest_submission_onboarding.php`
- `backend/guest_submission_onboarding_status.php`
- `backend/register.php`
- `backend/social_signup_complete.php`
- `account/register.php`
- `account/login.php`
- `backend/email_verification_feedback.php`
- `backend/verify_email.php`
- `account/dashboard.php`
- `admin/dashboard.php`
- `assets/js/vnz-dialog.js`

## Verification limits and open items

- The baseline review did not exercise live login/password-reset/2FA or schema application. Later focused remember-login work applied the existing token-table migration locally and verified token storage/hash/expiry in a rolled-back MySQL transaction; no real user's password login or full 2FA completion was submitted.
- On 2026-11-14 the guest-link fixture gained the existing helper's `offers.sender_type` column; all eight cases pass. New onboarding tests cover account/email binding, verification gating, dismissal, and exact submission ownership. This does not establish live email delivery or signup/token completion.
- The popup marker is session-based: a different browser or lost session has no onboarding banner. Existing verified-account linking behavior is unchanged.
- Signup-redirect follow-up: PHP lint passed on the three changed files; six read-only localhost HTTP checks passed for legacy redirects, allowed verification flags, the normal registration form, and sent/pending login notices. A new account POST and SMTP delivery were not exercised.
- Verification-feedback follow-up: ten isolated HTTP cases exercise the actual verification/status/login routes with fake sessions and SQLite, including POST verification, exact guest linking, CSRF, expired/used links, feedback replay, and account isolation. These pass alongside helper/browser checks. Production user rows were only inspected read-only; no live verification token was submitted by the assistant.
- Remember-login follow-up: the local `remember_login_tokens` table was absent, causing checked login to fail during INSERT. The existing `20261026_create_remember_login_tokens.sql` migration was applied and its required columns verified. Fourteen isolated HTTP cases pass for issuance/restoration, missing-schema fallback, invalid/expired/tampered tokens, inactive actors, storage failures, wrong credentials, and 2FA deferral. Social-auth regression also passes.
- Logout-confirmation follow-up: 12 browser fixtures pass across seller English/Filipino, admin English, light/dark, and desktop/375px layouts. Cancel/backdrop/native cancel events preserve the page; only explicit confirmation navigates to a fake hash. No real session or remembered-login token was cleared during verification.

## Related tests

- `tests/offer_response_authorization_test.php`
- `tests/guest_submission_link_test.php`
- `tests/guest_submission_onboarding_test.php`
- `tests/email_verification_http_test.py`
- `tests/remember_login_http_test.py`

Latest implementation and checks: [[90 System/Runs/2026-11-14 050800 Remember Login and Theme Consistency]]. Previous verification feedback: [[90 System/Runs/2026-11-14 044636 Email Verification Feedback]].

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Cipher|Cipher]] · [[90 System/Agents/Echo|Echo]] · [[90 System/Agents/Lyra|Lyra]] · [[90 System/Agents/Quinn|Quinn]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]

## ID verification v2 - 2026-11-19

ID/selfie v2 is implemented: private encrypted evidence, active-owner/dedicated active-reviewer access, authoritative admin decisions, durable account approval, one-use guest proof and exact signup/email carryover. Source: backend/identity_verification.php, identity_api.php, identity_evidence_cleanup.php and signup/verification hooks. Login/TOTP/remember completion unchanged. Local schema/private config applied; capture disabled pending Windows administrator cleanup setup. No personal ID/device test. See the run for 48 helper, 17 API, 11 signup, 10 email and 10 InnoDB checks.

[[90 System/Runs/2026-11-19 163005 Identity Verification v2]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]

### Activation follow-up - 2026-11-19 17:07

Earlier disabled/setup-pending state is historical. Owner Administrator setup completed; independent live form/API/private health and successful daily03:00 cleanup readback confirm local activation. No personal ID/device/human-review test or owner acceptance inferred. [[90 System/Runs/2026-11-19 163005 Identity Verification v2]]

## Submission selfie v6 - 2026-11-19 21:53

Current local mode is `face_submission_v6`, activated and live-verified21:50 Asia/Manila. ID enrollment/manual approvals are retired for new submissions; signup/email/login remain separate and existing email claims remain intact. Guest/account sellers take a new selfie per submission. No reference matching, Face++, face template or durable identity approval. Source: backend/submission_security_face.php, identity_automation.php, identity_verification.php and identity_api.php. Nonce/actor/CSRF and server presence gate replace old ID authority; old histories remain historical.

[[90 System/Runs/2026-11-19 213300 Submission Selfie v6]] - [[90 System/Tasks/Records/TASK-20261119-004]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]
