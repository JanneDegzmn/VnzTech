---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-20
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


## Admin UX + Queue v1 - 2026-11-20T14:43:32+08:00

Admin header now reuses portal chat styling/lifecycle with an authenticated admin-topbar placement. Bell reads permission-filtered aggregate updates; profile displays existing name/initial because current admin record has no photo field. Phone/tablet <=1024 opens full-screen inbox/conversation with actual private/seller-request/guest/trade/global categories; desktop uses bounded existing multi-chat docks/rail. Admin submission/trade info now starts collapsed like seller. Context has one bounded, keyboard-focusable scroll region with hidden visual scrollbars and no nested info scrollbar. Shared message history is distinguished from shell page history in both admin/seller Back handlers so Back/Close does not reload review content. Existing permission gate for mounting admin chat remains view_submissions; backend chat membership/CSRF unchanged. Existing Submissions now has New/Under review/Waiting seller/Done tabs, My assigned/Unassigned/All filters, deterministic oldest/newest created_at+id sort, retained search/status/item type and12-row server pagination. Waiting seller requires latest active admin offer; latest seller counter-offer routes to Under review because offer_sent alone does not identify whose turn it is. Done means submission decision, not delivery/payment. Polling banner is read-only and explicit Refresh retains filters/current page and blocks while review modal open; no automatic navigation/assignment/accept/reject. Initial MAX(id)+COUNT snapshot plus newer-ID count/total-growth advisory catches usual late lower-ID insert commits; concurrent deletions can still mask exact arrivals. No durable event stream, exact queue position, ETA or production load guarantee. Seller cards show Next step based on existing submission/latest-offer sender/status, including whose response is needed. Two new readonly readers: backend/admin_topbar_updates.php and backend/admin_submission_queue_updates.php. No state acknowledgment on GET; existing explicit shell navigation/mark-seen flow not changed. PASS:185 actual admin-component/endpoint/browser assertions on320/375/768/1024/1440 light/dark;235 SQLite query/pagination/synthetic-burst assertions;41 permission endpoint/controller assertions;16 pure seller next-step assertions; seller regressions77 chat-space +216 mobile/tablet +223 topbar/desktop assertions. PHP lint11 application files and JS syntax5 checks; existing admin decision beginTransaction/FOR UPDATE/status-validation ordering preserved by source check. Four final previews inspected: light375 queue/header, dark1440 multi-chat, light/dark375 inbox. Synthetic data/services only; no live account/provider/message/call writes, real MySQL concurrency/load test, physical phone/keyboard or screen-reader acceptance. [[90 System/Tasks/Records/TASK-20261120-013]].


## Header Alignment v1 - 2026-11-20T14:55:00+08:00

Admin header and branding row now85px with continuous border, neutral dark surfaces and existing light palette. Content minimum height follows header token; notifications/chat still use measured header bounds. PASS:215 admin,226 seller mobile/tablet,225 topbar regression assertions using actual header/widget assets and synthetic data. Header-specific checks cover85px row heights/bottom-edge alignment, matching admin border colors and neutral dark/existing light surfaces at320/375/768/1024/1440 (seller adds812 landscape/1200 desktop). Three previews inspected: dark admin1440, light admin375, light seller1200. Three test JS syntax and changed-line Git whitespace PASS. No live provider/call/device/physical-keyboard or screen-reader acceptance; no backend/schema/config/push/deploy changes. [[90 System/Tasks/Records/TASK-20261120-014]].

## Admin moderation settings UI v1 - 2026-11-20

Admin Settings > Global Chat moderation now uses page scrolling instead of capped nested rules/review list scroll areas; desktop columns align independently, and narrower layouts/forms use responsive proportions. Moderation labels/cards/actions are larger; action, focus, danger and status colors work with active admin light/dark themes. Source: `assets/css/admin_operations.css`, `assets/css/admin_theme.css`; regression: `tests/admin_moderation_ui_browser_test.mjs`. PASS:130 browser assertions across320/375/768/1024/1440 light/dark; four synthetic previews inspected. No live settings account, production data, physical device, screen-reader or moderation decision check. No PHP/JS/API/permission/database behavior changed. [[90 System/Tasks/Records/TASK-20261120-015]] - [[90 System/Runs/2026-11-20 153700 Admin Moderation UI v1]].

## Admin Settings pagination and palette v2 - 2026-11-20

Appearance and Admin profile now precede moderation. Configured rules use 12-item client-side pages after existing search, with range/count and accessible previous/numbered/next buttons. Search resets to page one; empty results suppress pagination; rendering clamps after deletions. The existing endpoint still returns the complete rules collection. A scoped token palette covers all Settings cards/forms/statuses/actions in both admin themes. Source: `admin/pages/settings.php`, `assets/css/admin_operations.css`, `assets/css/admin_theme.css`; test: `tests/admin_moderation_ui_browser_test.mjs`. PASS:178 synthetic assertions at320/375/768/1024/1440 light/dark and four inspected previews. No live database, device, screen-reader, or owner acceptance. [[90 System/Tasks/Records/TASK-20261120-015]] - [[90 System/Runs/2026-11-20 161822 Admin Settings UI v2]].

## Admin Settings black cards and compact rules v3 - 2026-11-20

Configured rules now render at most three cards per page. Change password spans the Settings grid; card groups have 1.25rem vertical spacing. All Settings card surfaces are black/charcoal in both Admin selector modes, while the selector and other Admin pages remain unchanged; public/seller styles are outside the scope. Specific light-selector overrides keep card titles, labels, metadata and moderation quotes readable. PASS:318 synthetic browser assertions across five widths and both selector modes; six previews inspected. No live account/physical-device/screen-reader evidence. [[90 System/Tasks/Records/TASK-20261120-015]] - [[90 System/Runs/2026-11-20 163311 Admin Settings UI v3]].

## Admin password action spacing v4 - 2026-11-20

The right-aligned Update password button now has a 1rem gap before the Role metadata divider, limited to the Admin Settings password card. No background/color or password behavior changed. PASS:368 synthetic browser assertions across320/375/768/1024/1440 in both selector modes, including measured button-to-divider spacing and password card contrast. No live account, physical-device, screen-reader, or deployment check. [[90 System/Tasks/Records/TASK-20261120-015]] - [[90 System/Runs/2026-11-20 165612 Admin Password Divider Spacing v4]].

## Site Updates maintenance boundary v1.1 - 2026-11-20

The approved maintenance amendment gates seller pages/actions only. Twenty-eight seller portal/API entries explicitly opt into the shared guard; the shared `backend/identity_api.php` scopes seller submission actions separately from Admin evidence/review actions. Default-public pages and public/guest routes remain available. Protected Admin routes retain the validated `getCurrentAdmin()` bypass, and the Admin status indicator stays nonblocking. The seller maintenance page and runtime overlay display VNZ TECH branding, the reason/estimate, and a status recheck. The route/service/browser fixtures pass; actual activation, migration/private-state setup, deployment, and owner acceptance remain separate. [[90 System/Tasks/Records/TASK-20261120-016]] · [[90 System/Runs/2026-11-20 175641 Branded Seller Maintenance Follow-up]]

## Admin full-width Profile and Moderation v1.2 - 2026-11-20

The Profile and Chat Moderation pages now use page-scoped single-column outer grids so their main cards fill the available Admin content width; the existing inner Moderation columns and responsive rules remain. The Profile Role row has a bottom divider and breathing room before fields. The general Admin shell no longer inserts a persistent maintenance banner; the Site Updates Maintenance panel continues to show status/control, and public announcements and seller behavior are unchanged. Existing black Admin cards remain unchanged in both selector modes; no new card background was introduced. A light-theme Admin heading uses the existing readable text token. PASS: Moderation UI 388, account/Profile UI 127, site-status client 337, Site Updates UI 263 browser assertions; two PHP lints, four Node syntax checks, and six synthetic previews. No live Admin account/database/device/screen-reader check; maintenance state unchanged. [[90 System/Tasks/Records/TASK-20261120-016]] · [[90 System/Runs/2026-11-20 184244 Admin UI Full-Width v1.2]]

## Admin navigation and Site Updates v1.3-R2 - 2026-11-20

The Account navigation heading now uses the same `nav-section-title` treatment and uppercase label as the other Admin groups. Site Updates defaults to VNZ TECH branding and an Admin-scoped black/charcoal palette; light-theme `#contentArea` text overrides are accounted for so card copy stays readable. The controls are explicit: Enable requires a clear reason, estimate and confirmation; Disable is one step. Plain-language writing guidance was added, but no announcement content was changed.

Maintenance supports seven server-enforced scopes: seller portal/workflows, `sell.php`, submissions/verification, offers/trades, seller account/settings, chat/messages, and all seller services. The existing state JSON carries the mode; legacy snapshots without a mode remain seller-portal-only. Admin access stays available, the all-services mode includes the public Sell page, and the seller dashboard renders scoped AJAX 503 copy. No maintenance activation or deployment occurred. Verification details and limits: [[90 System/Tasks/Records/TASK-20261120-016]] · [[90 System/Runs/2026-11-20 193429 Admin Site Updates and Service Modes v1.3-R2]].

## Admin Site Updates palette v1.2 - 2026-11-20

The v1.3-R2 maintenance scopes, wording, enable/disable behavior, and server enforcement remain unchanged. The earlier black-in-both-themes Site Updates card palette is superseded by TASK-017: light Admin surfaces in light mode; black/charcoal in dark; light dialog, fields, guidance, status, controls, and focus. The Rates-style tabs and gray sidebar icon remain. Tests passed 815 themed/responsive Site Updates assertions; 225 Admin topbar assertions also cover the coordinated chat panels. Synthetic fixtures only; maintenance stayed OFF, and owner visual acceptance remains pending. [[90 System/Tasks/Records/TASK-20261120-017]] · [[90 System/Runs/2026-11-20 202901 Admin Site Updates and Chat Palettes v1.2]]

## Admin light-theme and maintenance action v1.3 - 2026-11-20

The newer owner-approved scope supersedes earlier black-in-light Admin cards for Chat Moderation, Activity Log, Profile, Security, and Settings only. Those modules now inherit light Admin surfaces/text/controls/status colors in light mode; their existing dark palette remains. Site Updates uses one stateful maintenance button: Enable while OFF, Disable while ON; enable confirmation/reason and one-step disable behavior are unchanged. Public/Seller styling and maintenance enforcement were not modified. PASS: 211 account/activity, 388 moderation, and 875 Site Updates browser assertions across themes/responsive widths. Synthetic data/previews; no live Admin/database, maintenance activation, or deployment check. [[90 System/Tasks/Records/TASK-20261120-018]] · [[90 System/Runs/2026-11-20 204827 Admin Light Theme and Maintenance Toggle v1.3]]

## Admin Site Updates header refinement v1.4 - 2026-11-20

The Site Updates header removes only the logo/wordmark/tagline, keeps its `ADMINISTRATION` eyebrow, and reuses the same `operations-header` markup/styles as Reports. This keeps the module's title and description hierarchy consistent without changing Reports or Site Updates behavior. PASS: 1,037 actual-page browser assertions across light/dark, 375/768/1440px, and permission combinations; three synthetic previews inspected. PHP lint and Node syntax pass. No live Admin/device/screen-reader/deployment check; owner acceptance pending. [[90 System/Tasks/Records/TASK-20261120-019]] · [[90 System/Runs/2026-11-20 211328 Admin Site Updates Header Refinement v1.4]]

## Admin transaction modal layering v1.0 - 2026-11-20

The transaction details modal now shares the existing Admin overlay layer at `z-index: 11035`, above the Admin topbar (`11010`) and sidebar (`11030`). Its fixed viewport backdrop dims the complete shell; no transaction content, permission, API, or close behavior changed. PASS: 81 actual-browser fixture assertions across light/dark and 375/768/1440px, with production Admin/modal CSS; three synthetic previews inspected. No live transaction/API, device, screen-reader, or deployment check; owner review pending. [[90 System/Tasks/Records/TASK-20261120-020]] · [[90 System/Runs/2026-11-20 212655 Admin Transaction Modal Layering v1.0]]


## TASK016 original base and local setup evidence - 2026-11-20 22:06:02 Asia/Manila

TASK016 original base added Profile/Security personal pages, separated Chat Moderation/Activity Log, and introduced Site Updates with dedicated publish/maintenance grants. Codex actually applied the approved local additive setup after private backup; current health confirms both tables/permissions/private state and maintenance OFF. Publishing/activation/live authenticated UX were not exercised. Later TASK016 amendments and TASK017-021 retain their source/acceptance evidence. [[90 System/Runs/2026-11-20 220602 Site Updates Base Delivery Reconciliation]] - [[90 System/Tasks/Records/TASK-20261120-016]]
