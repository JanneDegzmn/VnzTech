---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-14
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Iris]]"
---

# Interface and Portal UX

Owner: [[90 System/Agents/Iris|Iris]]. Review date: **2026-11-14**.

## User requirement: light and dark themes

Recorded **2026-11-14**: every new or modified UI component must follow the active light/dark theme, including modals, popups, alerts, login errors, confirmations, forms, and other user-facing elements. Apply this across public, seller, and admin pages without another reminder. Reuse theme tokens and check readable text, status colors, actions, focus, backdrops, and small-screen layout in both modes.

This requirement is also stored in the repository-root `AGENTS.md`, vault `AGENTS.md`, and Iris instructions. It is a development default, not a claim that every historical page has already been audited.

## Implemented in source

- Separate public, seller, and admin page/layout assets with client-side portal interactions.
- Public/seller/admin theme scripts, shared dialogs, Terms modal, password visibility/strength UI, and loader components.
- Seller language endpoint persists `en` or `fil` behind authentication and CSRF; the client has supported-label translations.
- Public selling/tracking/rates/trade interfaces and seller/admin submission/transaction pages exist.
- Messaging has shared embedded/floating interfaces and dedicated styling.
- A new guest-to-seller signup shows **Claim your guest submission** on the first dashboard visit, after existing reminders close. Actions are **Yes, claim it** and **Maybe later**; a persistent banner reopens the prompt after dismissal.
- The native dialog shows the account email, verification instructions, resend cooldown/errors, and a distinct verified-only state. **Email verified successfully** confirms verification; its description adds submission-link success only after actual ownership is confirmed. English/Filipino copy and seller theme variables are used.
- Verification status refreshes on focus/visibility and polls while its dialog is open. A verification transition reopens the popup after an earlier dismissal, waits behind existing reminders, and refreshes the current page fragment without replacing the dialog. My Submissions opens after acknowledgement.
- Invalid/expired/replaced links show an explanation and a resend action. Already-verified accounts instead see their actual verified state. Sellers without a guest marker can receive verification feedback with the claim steps hidden and a Continue to dashboard action.
- After successful password signup, users go directly to login instead of seeing an empty registration form. The login notice confirms account creation and explains sent/pending verification email status; it allows login before email verification. Old registration-success URLs forward to login with only allowed email-status flags.
- The shared seller alert now uses the dashboard theme variables instead of fixed dark colors. Approval alerts show a green check; other alerts reset to the notice icon. A wider card, inset message area, readable spacing, full-width actions, visible focus, and constrained scrolling support desktop and small screens. Styles live in `assets/css/seller_alert.css`.
- Shared `VNZDialog` alerts/confirmations, including Login Failed, now resolve dialog colors from the active public/seller/admin theme. Portal theme flags take precedence over lower-priority flags. Danger actions, focus rings, 44px buttons, backdrops, and scroll constraints support both modes while preserving dialog queue behavior.
- Dashboard logout now uses the shared themed confirmation. Seller copy follows English/Filipino language settings with **Stay signed in / Manatili** and **Log out / Mag-logout** actions; admin uses English. Cancel, Escape, or backdrop dismissal does not navigate. Duplicate clicks are prevented while a confirmation is pending, and the admin logout no longer has the eager loader attribute.
- Shared confirmation links accept `data-vnz-confirm-cancel-label`; other confirmations keep their existing default Cancel text. The shared dialog script URL is now versioned by file modification time so updated behavior is fetched.

## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `index.php`
- `sell.php`
- `account/dashboard.php`
- `admin/dashboard.php`
- `assets/js/seller.portal.js`
- `assets/js/seller-language.js`
- `assets/js/public-theme.js`
- `assets/js/seller-theme.js`
- `assets/js/admin-theme.js`
- `assets/js/vnz-dialog.js`
- `assets/js/loader.js`
- `includes/terms_modal.php`
- `backend/update_seller_language.php`
- `includes/guest-submission-claim-prompt.php`
- `assets/js/guest-submission-claim.js`
- `assets/css/guest_submission_claim.css`
- `account/register.php`
- `account/login.php`
- `backend/register.php`
- `backend/email_verification_feedback.php`
- `backend/verify_email.php`
- `assets/css/seller_alert.css`
- `assets/css/vnz_dialog.css`
- `includes/loader-head.php`

## Verification limits and open items

- Supported-label translation does not establish that every string in the product is translated.
- The baseline review did not exercise visual rendering/accessibility. The new claim dialog was exercised in desktop headless Chrome with real component/assets and mocked APIs: reminder ordering, dialog focus, dismissal/reopen, mail error/retry/cooldown, and verified/linked states passed. Its desktop screenshot was inspected. Full portal navigation, mobile rendering, screen-reader behavior, and live signup/email remain unverified.
- Signup-redirect follow-up: six read-only localhost HTTP checks passed for normal registration rendering, legacy success redirects, whitelisted flags, and login notices for sent/pending email. PHP syntax passed on all three changed files. No fresh signup or visual browser pass was performed for this small follow-up.
- Verification-feedback follow-up: the extended Chrome test passes dismissal → verification → automatic reopen, reminder ordering, portal refresh events, once-only presentation, invalid-link recovery, and real ownership copy. Ten isolated route-level HTTP cases also pass. The desktop success screenshot was inspected; a full live signup/SMTP/browser flow remains untested.
- Approval-modal styling follow-up: PHP syntax and diff checks passed. Temporary Chrome fixtures using the actual alert markup/controller and theme CSS passed light/dark desktop, true 375px mobile emulation, and short-screen/long-message checks. Approval → notice queue reset, Close, and the Chat with Admin callback were checked with fake data/stubbed chat. Light/dark/mobile screenshots were inspected; a live transaction/chat request and screen-reader pass were not performed.
- Shared-dialog follow-up: 12 Chrome fixture cases pass (public/seller/admin × light/dark × 1200px/375px iframe widths), including conflicting theme flags, live theme switching, text/danger-button contrast, focus, 44px actions, confirmation/cancel, queue reset, and long content in a short mobile frame. The light login-error screenshot was inspected. This is fixture coverage, not a live credential login or screen-reader/device pass.
- Logout-confirmation follow-up: 12 Chrome fixtures pass (seller en/fil and admin en × light/dark × desktop/375px iframe widths), using actual rendered anchors and shared assets. Initial/duplicate clicks are prevented, one localized dialog appears, cancel/backdrop/native cancel preserve the page, and only confirm navigates to the fixture hash. No early loader appears. The Filipino light/mobile screenshot was inspected; no real logout, OS Escape keypress, or screen-reader/device pass was performed.

## Related tests

- `tests/guest_submission_onboarding_browser_test.py` covers the new prompt with mocked APIs; it does not cover the full module.
- `tests/email_verification_http_test.py` exercises real verification/status/login routes with temporary SQLite/fake-session boundaries.

Latest implementation and checks: [[90 System/Runs/2026-11-14 050800 Remember Login and Theme Consistency]]. Previous verification feedback: [[90 System/Runs/2026-11-14 044636 Email Verification Feedback]].

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Echo|Echo]] · [[90 System/Agents/Lyra|Lyra]] · [[90 System/Agents/Mira|Mira]] · [[90 System/Agents/Peso|Peso]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]
