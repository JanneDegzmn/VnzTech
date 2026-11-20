---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-19
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Rhea]]"
---

# Admin Operations and Reports

Current local behavior: [[90 System/Runs/2026-11-19 213300 Submission Selfie v6|submission selfie v6]], active2026-11-19. Earlier dated ID-review sections below remain history.

Owner: [[90 System/Agents/Rhea|Rhea]]. Review date: **2026-11-14**.

## Implemented in source

- Submission details contain **View submission selfie**, opening a themed private-photo dialog with 2x zoom/reset. V6.2 uses bounded mouse/touch drag without zoom scrollbars, plus focused arrow keys; dragging does not reset the photo. Unavailable/expired/old-submission states remain explicit. Uses the existing protected endpoint/current view_submissions authorization and retention. UI source: admin/pages/submission_review.php and assets/js/submission-selfie-viewer.js/CSS. [[90 System/Runs/2026-11-19 224454 Submission Selfie Drag v6.2|V6.2 source/check evidence and limits]].

- Admin navigation/dashboard modules and recent submission/admin activity views.
- Settings gated by `manage_settings`, with profile/sign-in settings, activity logs, and moderation interface.
- Reports gated by `view_reports`, validated date ranges, submission-status totals, offers sent, and transactions.
- Completed transaction values are aggregated separately; report tables use pagination.
- CSV export includes handling for values that could be interpreted as spreadsheet formulas.

## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `admin/dashboard.php`
- `admin/pages/dashboard.php`
- `admin/pages/settings.php`
- `admin/pages/reports.php`
- `backend/update_admin_settings.php`
- `backend/admin_activity.php`
- `backend/pagination.php`
- `backend/get_unseen_admin_counts.php`

## Verification limits and open items

- Report totals, permissions per deployed role, pagination, CSV output, and activity completeness were not checked against a live database.
- Existing screens and SQL are source evidence; they do not establish accounting correctness.

## Related tests

Walang dedicated test na natukoy para sa buong module sa kasalukuyang test directory.

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Aegis|Aegis]] · [[90 System/Agents/Vega|Vega]] · [[90 System/Agents/Beacon|Beacon]] · [[90 System/Agents/Quinn|Quinn]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]

## ID verification v2 - 2026-11-19

Admin dashboard now exposes a dedicated-permission ID verification queue; source admin/pages/identity_verifications.php and backend/identity_api.php. Review exact private evidence and record approve/reject/revoke with confirmation, reason, CSRF and audit; similarity cannot grant authority. Active admin+active role+identity_review rechecked. Queue explicitly reports rollout disabled. Existing nonreviewer staff have no private access. Local schema is applied; administrator cleanup setup/activation still outstanding.

[[90 System/Runs/2026-11-19 163005 Identity Verification v2]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]

### Activation follow-up - 2026-11-19 17:07

Earlier disabled/setup-pending state is historical. Owner Administrator setup completed; independent live form/API/private health and successful daily03:00 cleanup readback confirm local activation. No personal ID/device/human-review test or owner acceptance inferred. [[90 System/Runs/2026-11-19 163005 Identity Verification v2]]

## Submission selfie v6 - 2026-11-19 21:53

In active v6, ID-verification navigation is absent; its direct page and old ID API return410. No replacement admin module. Existing admin/pages/submission_review.php shows the submission selfie within Seller Information. backend/submission_face_photo.php rechecks active admin, active role and view_submissions on every uncached read, plus private hash/expiry. Existing submissions without a photo display an honest empty state. Face detected is not identity verified. Historical ID evidence is not backfilled.

[[90 System/Runs/2026-11-19 213300 Submission Selfie v6]] - [[90 System/Tasks/Records/TASK-20261119-004]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]
