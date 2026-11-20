---
type: agent-run
status: ready_for_review
created: 2026-11-20
updated: 2026-11-20
tags: [seller, submissions, theme, qa]
task_id: TASK-20261119-006
runner: Copilot SDK in VS Code
---

# Seller Bulk Submission Flow

## Request and approved scope

Implemented owner-approved Plan v2 for TASK-20261119-006: keep per-piece flow, add seller-only bulk lots for phones and scrap boards, remove only the redundant overall Seller Notes field, retain item-level Additional Information and the existing selfie capture/check, enlarge the modal, and preserve legacy/public behavior. The additive migration and deployment were explicitly excluded. Owner result acceptance remains pending.

Roles consulted for context: Atlas coordination, Iris (seller UI), Orion (submission journey), Vega (schema), and Quinn (verification). No delegated execution is attributed.

## Changes

- `account/dashboard.php` separates Per Piece and Bulk, supports multiple mixed phone/scrap lots, and preserves the selfie capture. Bulk lots ask for photos, quantity with exact/estimated choice, total asking price, and item-level Additional Information; per-device specifications are disabled. The overall duplicate Seller Notes field is removed.
- `assets/css/seller_dashboard.css`, `assets/css/seller_theme.css`, and `assets/js/seller-language-copy.js` improve modal sizing/readability, control and focus states, touch sizing, theme support, and bulk-mode copy.
- `backend/submit_submission.php` applies account-only bulk validation, requires the existing selfie and photos, skips per-device details only for the explicit bulk marker, and stores each lot's total price and quantity certainty. Admin, seller, history, and tracking views identify new bulk lots without reinterpreting legacy wholesale totals.
- `database/migrations/20261119_add_bulk_submission_format.sql` adds the default-off compatibility markers. It remains unapplied.
- Focused submission and browser fixtures were added/extended. `tests/browser_cdp.mjs` received a Chrome shutdown/temp-profile cleanup improvement used by the new browser fixture.

Attributable app paths also include `account/pages/dashboard.php`, `account/pages/submission.php`, `account/pages/submissions.php`, `account/submissions.php`, `admin/pages/submission_review.php`, `admin/pages/submissions.php`, `backend/track_submission.php`, and `seller/tracking.php`.

## Verification

- `python tests/submission_face_submit_http_test.py`: **41 isolated HTTP checks passed**. Includes rejection of guest/public bulk, account-only mixed lots, exact/estimated quantity, lot-total pricing, no per-device specs, legacy wholesale distinction, and required photo/selfie guards. Positive face-presence inference is mocked only in this disposable fixture; no real face acceptance, SMTP, or Face++ call.
- `node tests/seller_bulk_submission_browser_test.mjs`: **10 grouped browser checks passed** using the actual seller modal CSS, extracted item/mode functions, and PHP-rendered selfie-card markup with synthetic seller values. Light/dark desktop (1200x900) and mobile (375x812) were previewed and inspected. Checks include text contrast, keyboard focus, 44px touch targets, horizontal overflow, mixed bulk lots, quantity certainty, price/notes fields, and returning to Per Piece.
- PHP syntax passed for 10 changed PHP files. `node --check` passed for `assets/js/seller-language-copy.js`, `tests/browser_cdp.mjs`, and `tests/seller_bulk_submission_browser_test.mjs`.
- Scoped `git diff --check` passed; Git emitted only LF-to-CRLF normalization warnings.

## Limits and next safe action

The local authenticated dashboard redirects to login, so browser previews use synthetic seller values and do not establish an authenticated live portal flow. No live database migration, deployment, camera, native file-picker, physical-device, screen-reader, or actual SMTP verification was performed. The migration remains unapplied by design. Screenshots were inspected and temporary preview files removed.

Next: owner reviews the source and seller flow; implementation approval is separate from result acceptance. Do not apply the migration or deploy without separate approved scope. TASK-20261119-004 v6.2 remains independently handed off/incomplete.

## Light-theme dropdown follow-up - 2026-11-20 00:41 Asia/Manila

Owner screenshot showed the bulk category option popup with a dark surface while the modal was in light mode. Root cause: the existing `.seller-item-card select option` rule in `assets/css/seller_dashboard.css` explicitly sets dark option colors; the light-theme CSS styled the control but not its option list.

`assets/css/seller_theme.css` now scopes a light color-scheme, white option background, and dark option text to the seller bulk select. The dark theme retains its existing palette. `tests/seller_bulk_submission_browser_test.mjs` now asserts the select color-scheme and option contrast (minimum 4.5:1) in both themes. The updated 10 grouped Chrome checks pass for light/dark desktop and mobile, and the synthetic previews were re-inspected. The checks exercise computed option styles; they do not represent a live authenticated native picker or physical-device test.

No application database/schema change, migration, or deployment was made. Owner acceptance remains pending.

## Related notes

[[90 System/Tasks/Records/TASK-20261119-006]] · [[40 Knowledge/System Modules/Interface and Portal UX]] · [[40 Knowledge/System Modules/Submissions and Offers]] · [[40 Knowledge/System Modules/Data and Migrations]] · [[40 Knowledge/System Modules/Verification and QA]] · [[40 Knowledge/Engineering Lessons]]
