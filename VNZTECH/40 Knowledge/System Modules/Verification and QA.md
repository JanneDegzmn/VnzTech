---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-20
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Quinn]]"
---

# Verification and QA

## Admin maintenance PDO-scope regression - 2026-11-20

`tests/site_status_guard_test.mjs` now copies the real `backend/auth.php` into an isolated HTTP fixture and supplies a temporary SQLite database/session bootstrap. This reproduces the first include from inside `vnzEnforceSiteMaintenance()` and checks active Admin access through the actual `getCurrentAdmin()` SQL path, including invalid/inactive cases. The new assertion failed before the scope fix and passed afterward. Focused route/service checks pass too; this is not live MySQL/runtime evidence. [[90 System/Tasks/Records/TASK-20261120-016]] · [[90 System/Runs/2026-11-20 180944 Admin Maintenance PDO Scope Fix]]

Current local behavior: [[90 System/Runs/2026-11-19 213300 Submission Selfie v6|submission selfie v6]], active2026-11-19. Earlier dated ID-review sections below remain history.

Owner: [[90 System/Agents/Quinn|Quinn]]. Review date: **2026-11-14**.

## Implemented in source

- `tests/seller_preference_button_alignment_browser_test.mjs`: latest **232 checks PASS** with actual form markup (dynamic PHP values stripped), actual seller CSS and synthetic settings. Verifies select/login-button edges in one row above500px, matching widths/right edges/heights, footer spacing, stacked full-width375px, English/Filipino labels, focus and overflow across light/dark1200/542/375px. Earlier112/136 iteration results remain historical in the run. Three synthetic previews inspected; no authenticated preference POST/live account/physical phone. [[90 System/Runs/2026-11-20 053913 Seller Preference Button Alignment]].
- Seller account UX v1.0 adds current-source coverage for compact Seller Security action sizing and the grayscale nav lock. **448 checks PASS** across light/dark at375/499/500/542/768/1440px, including Security enabled/disabled states, text fit, responsive overflow and unchanged Settings notification button sizing; four synthetic previews inspected. PHP lint and Node syntax pass. No authenticated account/live POST, physical-device, screen-reader, or deployment check. Two older Seller Security tests fail before style assertions because their fixtures still expect Security controls in Settings; current source coverage is in the account UX regression. [[90 System/Tasks/Records/TASK-20261120-021]] · [[90 System/Runs/2026-11-20 215336 Seller Security UI v1.0]].
- Admin Trade Requests ordering regression `tests/admin_trade_request_order_test.py` extracts the actual CASE/ORDER BY clause from `admin/pages/trade_requests.php` and runs it against synthetic SQLite rows. It confirms PENDING first/newest first, APPROVED next, and all other statuses afterward. The test and PHP lint pass. This is not authenticated/live-database or browser verification. [[90 System/Runs/2026-11-20 011633 Admin Trade Request Ordering]].

- Seller bulk regression `tests/seller_bulk_submission_browser_test.mjs` extracts the actual seller item/mode functions, uses the real seller modal/theme CSS and PHP-rendered selfie-card markup, and supplies synthetic account values. Ten grouped Chrome checks pass for light/dark desktop 1200x900 and mobile 375x812, including computed text contrast, keyboard focus, 44px controls, overflow, mixed bulk lots, quantity certainty, lot totals, removal of device-spec payloads, and return to Per Piece. Screenshots were inspected. `tests/submission_face_submit_http_test.py` passes 41 isolated routes/validation/persistence cases; positive face-presence inference is mocked only inside that disposable fixture. Ten relevant PHP files lint; changed JS checks and scoped diff whitespace pass. The authenticated dashboard redirected to login, so this is not an authenticated portal, live migration, camera, physical-device, screen-reader, or native file-picker test. [[90 System/Runs/2026-11-20 003109 Seller Bulk Submission Flow]].

- V6.1 extends the existing actual-PHP/CSS admin-photo Chrome fixture to the actual scoped viewer JS, generated JPEGs and transformed nested review modal. Covers on-demand loading, fit/zoom/reset, themes/mobile, failures, close/focus/scroll, AJAX removal/rebind and existing item-photo viewing. It caught and drove correction of a real subtree-observer replacement bug that left detached loading viewers and page-scroll locks. Protected-photo HTTP24 rerun passed; final browser counts/commands and preview limits are in [[90 System/Runs/2026-11-19 222413 Submission Selfie Viewer v6.1]]. No real personal-photo fixture or authenticated live admin/device test.

- The baseline review ran six PHP tests plus one PowerShell check: five passed and two failed. Historical results cover social-auth helpers, delivery states, offer authorization, moderation, and legacy attachment inference.
- The guest-link fixture failure is now resolved by adding its missing `offers.sender_type` column. All eight existing cases pass without changing production linking rules.
- The claim-popup task added a PHP/SQLite onboarding test and an offline headless-Chrome integration test. Focused onboarding, guest linking, offer authorization, PHP syntax, JavaScript syntax, and mocked-browser checks pass; see the dated run for commands and scope.
- A subsequent user report exposed missing redirect/feedback coverage in the original mock-only browser check. The new isolated HTTP test runs actual PHP verification/status/login routes with temporary fake data; all ten cases pass. Extended helper/browser checks now cover reopening after dismissal, stale feedback acknowledgement, visible invalid links, and refresh events.
- Remember-login regression now adds 14 passing isolated HTTP cases for real login/session/remember helpers, with SQLite/fake identity and stubbed chat presence. A separate rolled-back MySQL check passed issuance/hash/expiry after applying the missing local token-table migration. Shared-dialog theme fixtures pass 12 public/seller/admin light/dark desktop/mobile cases.
- The unrelated social-login setup check's expected-placeholder failure remains unresolved; Scaledrone authorization was not run because of database bootstrap. This task did not repeat the whole baseline suite.

## Obsidian Agent Office — 2026-11-19

The new vault-local test `VNZTECH/90 System/Tools/agent-office-test.cjs` runs the actual bundled plugin and styles with a stubbed Obsidian API and real Chrome DOM. Coverage includes registration, first-layout welcome, single-tab reuse, all 20 agent-note targets, a specialist module target, search/no-results/reset, map/list, animation pause, missing-note handling, cleanup/reopen, absence of network calls, and theme/layout behavior. Exact case counts and preview results are recorded in the dated office run.

This is fixture coverage. The owner later showed the plugin enabled, but the new build's actual app reload/loading is not yet verified; the earlier CLI check was disabled. Physical-device, screen-reader, and live task-runner integration are not established by this test.

The company/task-memory extension compares all 20 titles, departments, and strategies against the canonical roster; checks CEO desk note targets; and exercises readonly pointer parsing/status/approval/ID/path/timestamp validation, errors/missing records, relevant-file events, stale-read races, cleanup/reopen, and no-write/no-network behavior. Desktop/light/dark/mobile/split-pane coverage stays in the same focused browser test. Actual final counts/results are recorded in [[90 System/Tasks/Records/TASK-20261119-001]] and the corresponding dated run.


## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `tests/chat_attachment_message_type_test.php`
- `tests/chat_scaledrone_auth_test.php`
- `tests/configure_social_login_test.ps1`
- `tests/global_chat_moderation_test.php`
- `tests/guest_submission_link_test.php`
- `tests/offer_response_authorization_test.php`
- `tests/social_auth_service_test.php`
- `tests/transaction_delivery_workflow_test.php`
- `tests/guest_submission_onboarding_test.php`
- `tests/guest_submission_onboarding_browser_test.py`
- `tests/email_verification_http_test.py`
- `tests/remember_login_http_test.py`
- `tests/admin_trade_request_order_test.py`
- `tests/seller_preference_button_alignment_browser_test.mjs`

## Verification limits and open items

- Existing helper/source tests are narrow evidence. Passing them does not verify browser behavior, migrations, provider configuration, or external integrations.
- The historical guest fixture failure did not establish a production defect. Its corrected regression now passes. Token completion is covered by isolated real-route HTTP tests; live SMTP/fresh signup, mobile rendering, and full portal flows remain separate evidence gaps.
- Remember-login HTTP checks isolate chat presence and do not exercise full 2FA confirmation or a real user's login. Browser theme checks use actual dialog assets with fixture content. The local remember-token migration is verified independently from unrelated migration/deployment state.

## Related tests

- `tests/chat_attachment_message_type_test.php`
- `tests/chat_scaledrone_auth_test.php`
- `tests/configure_social_login_test.ps1`
- `tests/global_chat_moderation_test.php`
- `tests/guest_submission_link_test.php`
- `tests/offer_response_authorization_test.php`
- `tests/social_auth_service_test.php`
- `tests/transaction_delivery_workflow_test.php`
- `tests/guest_submission_onboarding_test.php`
- `tests/guest_submission_onboarding_browser_test.py`
- `tests/email_verification_http_test.py`
- `tests/remember_login_http_test.py`
- `tests/seller_preference_button_alignment_browser_test.mjs`

Latest focused checks: [[90 System/Runs/2026-11-14 050800 Remember Login and Theme Consistency]]. Previous verification checks: [[90 System/Runs/2026-11-14 044636 Email Verification Feedback]].

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Aegis|Aegis]] · [[90 System/Agents/Cipher|Cipher]] · [[90 System/Agents/Echo|Echo]] · [[90 System/Agents/Vega|Vega]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]

## ID verification v2 - 2026-11-19

Identity v2 focused evidence: 48 synthetic PHP helper checks; 17 isolated actual API/submission HTTP; 11 signup HTTP; 10 existing verification HTTP; 10 disposable actual InnoDB concurrency/rollback/revocation; nine asset integrity checks and all3 real models loaded/blank-canvas inference; 12 Chrome theme/viewport cases with camera/model recovery and synthetic-track cleanup. Fourteen PHP lints, JS syntax, whitespace and seven readonlylocalhost probes pass. No real ID/camera/device/SMTP/provider flow. Windows cleanup-task Access denied and canceled elevation keep capture disabled.

[[90 System/Runs/2026-11-19 163005 Identity Verification v2]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]

### Activation follow-up - 2026-11-19 17:07

Earlier disabled/setup-pending state is historical. Owner Administrator setup completed; independent live form/API/private health and successful daily03:00 cleanup readback confirm local activation. No personal ID/device/human-review test or owner acceptance inferred. [[90 System/Runs/2026-11-19 163005 Identity Verification v2]]

## Submission selfie v6 - 2026-11-19 21:53

V6 passes63 helper,36 real-route isolated submit/API,24 real-route isolated admin-photo,33 worker and10 actual disposable InnoDB schema/trigger checks. Capture UI24 + inline-admin UI7 Chrome checks cover light/dark desktop/375 and camera/consent/retry/draft lifecycle. Earlier-mode48 helper/26 API/10 email/13 matcher and24 themed browser regression checks also pass. Fourteen PHP lints, changed JS syntax, default whitespace and11 live anonymous probes pass. Positive face inference is mocked only in disposable route fixtures; actual CPU blank refusal is separate. No real camera acceptance, physical phone, authenticated live admin/seller or SMTP claim.

[[90 System/Runs/2026-11-19 213300 Submission Selfie v6]] - [[90 System/Tasks/Records/TASK-20261119-004]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]

## Seller Topbar v1 - 2026-11-20

79 headless Chrome assertions pass using actual PHP-rendered components and production JS/CSS with synthetic APIs at1200/375 light/dark and375x480 short viewport. Covers sticky/overflow/placement, AJAX destinations, notifications/unread counts, empty/error/expired/Taglish refresh, Escape/focus, selected chat reopen/collapse/call-owner preservation, reduced motion and default floating placement.8 helper +4 isolated endpoint boundary cases pass;10 PHP files lint,3 JS files syntax,3 shell/profile integration assertions and default git whitespace pass.8 synthetic screenshots generated;4 visually inspected across iterations. Tests: tests/seller_topbar_test.php, seller_topbar_api_test.py + isolated fixture, seller_topbar_browser_test.mjs + PHP component fixture. No live auth seller/user records/messages/calls/provider/phone/browser-matrix/screen-reader tests; no migration. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

## Admin Settings pagination and palette v2 - 2026-11-20

`tests/admin_moderation_ui_browser_test.mjs` exercises the production inline moderation controller with actual admin dashboard/operations/theme CSS and synthetic responses. PASS:178 browser assertions at320/375/768/1024/1440 in light/dark, covering 12-rule pages, search one/zero results and page reset, deletion clamp, empty collection/refresh reset, Tab reachability/focus, section order, palette contrast, responsive columns and horizontal overflow. `node --check`, Admin Settings PHP lint, `tests/global_chat_moderation_test.php`, and scoped tracked-file `git diff --check` pass. Four synthetic previews inspected. No authenticated account, production records, screen-reader, physical device or deployed environment exercised. [[90 System/Tasks/Records/TASK-20261120-015]] - [[90 System/Runs/2026-11-20 161822 Admin Settings UI v2]]

## Admin Settings v3 compact rules and black cards - 2026-11-20

The focused browser suite now passes318 assertions at320/375/768/1024/1440 in both Admin selector modes. It checks three rendered rules/page with25 synthetic rules, search one/zero results, current page clamp on deletion, refresh/empty state, keyboard page navigation, black Settings-only card surfaces, no matching black token outside Settings, full-width password, 1.25rem inter-card gaps, text/status contrast and no horizontal overflow. `node --check tests/admin_moderation_ui_browser_test.mjs`, `C:\xampp\php\php.exe -l admin\pages\settings.php`, pure `tests/global_chat_moderation_test.php`, and scoped `git diff --check` pass. Six synthetic previews were inspected. No authenticated account/production data, physical device, screen-reader or deployed environment. [[90 System/Tasks/Records/TASK-20261120-015]] - [[90 System/Runs/2026-11-20 163311 Admin Settings UI v3]]

Plan v2 presentation regression: **245 Chrome checks pass** at1200/768/440/375/320px in light/dark using actual PHP component markup and production CSS/JS. Added checks for redundant heading removal, seller shell/sidebar gutter alignment, fixed bell/chat/avatar sizing, right-edge fit, centered reduced-stroke message glyph and existing interactions. Five synthetic previews inspected. PHP partial/fixture lint, JS syntax, 8 data/helper checks and scoped whitespace pass. No authenticated seller/device/screen-reader acceptance. [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]].

### Topbar owner-reported defects - 2026-11-20

Extended tests/seller_topbar_browser_test.mjs with production loader CSS/markup, document.elementFromPoint hit-testing at the header, category-label display/background/border/shadow assertions, and actual Emulation.setTouchEmulationEnabled.281 Chrome checks PASS across five widths1200/768/440/375/320 in both themes, including6 explicit hover:none checks; two category previews visually inspected. Changed test JS syntax and scoped/new-file whitespace pass. Prior v1/v2 checks remain historical evidence; no live account/physical-phone/provider or new PHP/backend change in this correction. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Chat v3 actual-widget regression - 2026-11-20

503 Chrome assertions PASS using tests/seller_topbar_browser_test.mjs and new tests/seller_chat_widget_fixture.php. Fixture strips bootstrap imports and stubs helpers to render production includes/transaction-chat.php without database/session/provider access. Synthetic ready state/messages/input simulate controller initialization; no actual controller delivery/send/call claim. Six widths1200/768/600/440/375/320 in light/dark plus375x480 short screen, with touch emulation at440/375/320. Asserts bottom docking, actual viewport-fill dimensions excluding desktop scrollbar, repeated restore with unsent draft, nonempty visible messages/composer, list-to-conversation state, topbar-control occlusion, existing keyboard/menu/loader/unread/profile/lifecycle regressions. New fixture PHP lint, two changed JS syntax checks and scoped/new-file whitespace PASS. Two final screenshots inspected; earlier snapshot caught a layering defect now covered by hit-test. No live seller/phone/provider or full admin-page acceptance. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Chat v4 verification - 2026-11-20

621 full headless Chrome assertions PASS with actual PHP topbar/widget template + production CSS/JS, synthetic network/data and extracted existing seller-sidebar toggle script. Six normal widths1200/768/600/440/375/320 in both themes, short375x480 widget and explicit touch media; multi-chat lifecycle at1200/375 in both themes. Tests cover exactly5 complete contact rows of12, scrolling/call controls; topbar Back; outside/bell/menu persistence;2 desktop/1 small slots, third-chat overflow and drafts; hover gap traversal/keyboard/click selection; duplicate prevention, fullscreen arrangement restore,10-window cap, per-window Close and sidebar44px hit target/focus. Existing widget/call-owner-node and loading/theme regressions remain.

An explicit UTF-8 fixture declaration corrected garbled preview symbols without changing product glyphs;187 focused assertions pass afterward, covering375 normal + multi-chat desktop/mobile. Four final previews inspected. Three changed JS syntax checks, widget fixture PHP lint and five-file UTF-8/trailing-whitespace + git whitespace PASS. Sources: tests/seller_topbar_browser_test.mjs (VNZ_TOPBAR_FOCUSED=1 narrows normal-width loop; multi-chat suite retained), tests/seller_chat_widget_fixture.php now parameterizes synthetic widget IDs. No live account/provider/send/call/media/phone/screen-reader or complete admin/public-page matrix. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Chat v5 reference coverage - 2026-11-20

755 full headless Chrome assertions PASS using production PHP widget/topbar markup and assets with synthetic API/component initialization. Existing six normal viewport/theme regressions and multi-chat lifecycle remain; new reference suite checks1200/1440/1760/1920/768/375/320 in light/dark,1760x643 reference-sized screenshot and375x480 resize. Verifies360x500 normal geometry,12px gaps/bottom alignment, expected2/3/4/1 slots, rail no-overlap/bounds/accessible fallback names, safe local avatar crop and unsafe-scheme fallback, +N selection, resize/node/draft retention, message/composer visibility and v4/fullscreen regressions. Three reference screenshots visually inspected. Earlier focused128-selection and124-layout passes were intermediate, not independent extra coverage totals.

Three attributable files: assets/js/floating-messages.js, assets/css/seller_topbar.css, tests/seller_topbar_browser_test.mjs. JS syntax(2), scoped UTF-8/trailing whitespace and git whitespace PASS. Native Enter tests now include text/keypress metadata; keyDown-only did not invoke default button activation in the fixture, while direct click confirmed component behavior. No product keyboard workaround added. No live account/provider/call/send/media/physical-phone/screen-reader or authenticated admin-page matrix evidence. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]


## Mobile Messages v1 - 2026-11-20T11:49:24+08:00

216 mobile/tablet assertions PASS, plus 223 existing topbar/desktop regression assertions PASS. Actual production PHP topbar/widget markup and JS/CSS under synthetic API responses, no DB/session/providers. Mobile/tablet 320x667,375x812,768x1024,1024x768,812x375 in light/dark; additional812x260 short-height and1440 desktop resize. Verified direct inbox, authorized people/local avatar/presence, search/unread/New message, full-screen real-widget content/composer, outside-click persistence, Back/query/scroll/filter/draft restore, browser Back, explicit Close/Escape/focus wrap, delayed-open cancellation, loading/error/retry/empty, call-owner node retention and desktop resize/dimensions. Three final previews inspected: light/dark375 inbox and light375 conversation. No live sends/calls, actual device keyboard, physical phone/tablet, screen-reader or full authenticated admin matrix verification. New mobile suite lives at tests/seller_mobile_messages_browser_test.mjs; legacy suite keeps desktop/v5 checks and cross-breakpoint preservation. The earlier short-height failure was corrected by compact composer and context collapse. JS syntax/scoped whitespace checked. [[90 System/Tasks/Records/TASK-20261120-012]] - [[90 System/Runs/2026-11-20 114924 Mobile Messages Inbox v1]].


### Alignment + Chat Space v2 - 2026-11-20T13:52:53+08:00

PASS:77 new actual-component browser assertions across1200x800,375x812,768x1024,1024x768 light/dark (native disclosure/click/Enter, five short replies, draft and message/composer preservation, centered arrow/SVG, left burger/close);216 mobile/tablet regression assertions including812x260;223 existing topbar/desktop regressions. PHP syntax, two JS syntax checks and admin PHP widget unchanged-info guard PASS. Two inspected previews: light desktop and dark phone collapsed-info conversation. No live seller messages/calls/provider, physical devices/keyboard or screen-reader acceptance. Source: includes/transaction-chat.php; assets/js/seller-topbar.js; assets/css/seller_topbar.css; tests/seller_chat_space_browser_test.mjs. Earlier five-reply assertion initially failed at desktop200px message-list height with10px gaps;6px seller-only gaps corrected the five-short-reply case. Expanded-info diagnostic also exposed fixture text mismatch in the reused visible-content helper; final fixture preserves expected marker. No product change made to satisfy that text marker. [[90 System/Tasks/Records/TASK-20261120-012]] - [[90 System/Runs/2026-11-20 114924 Mobile Messages Inbox v1]].


## Admin UX + Queue v1 - 2026-11-20T14:43:32+08:00

PASS:185 actual admin-component/endpoint/browser assertions on320/375/768/1024/1440 light/dark;235 SQLite query/pagination/synthetic-burst assertions;41 permission endpoint/controller assertions;16 pure seller next-step assertions; seller regressions77 chat-space +216 mobile/tablet +223 topbar/desktop assertions. PHP lint11 application files and JS syntax5 checks; existing admin decision beginTransaction/FOR UPDATE/status-validation ordering preserved by source check. Four final previews inspected: light375 queue/header, dark1440 multi-chat, light/dark375 inbox. Synthetic data/services only; no live account/provider/message/call writes, real MySQL concurrency/load test, physical phone/keyboard or screen-reader acceptance. Queue query tests execute actual helper SQL in isolated SQLite, not production MySQL; endpoint tests stub only connection/session/auth at boundary and execute actual reader bodies. Actual header/tools/widget PHP rendered under synthetic helpers; queue control markup extracted from production page (not full DB-backed page). Native real-device callbacks/calls unverified. New tests listed in [[90 System/Runs/2026-11-20 144332 Admin UX Queue v1]]. No combined total implies independent coverage. [[90 System/Tasks/Records/TASK-20261120-013]].


## Header Alignment v1 - 2026-11-20T14:55:00+08:00

PASS:215 admin,226 seller mobile/tablet,225 topbar regression assertions using actual header/widget assets and synthetic data. Header-specific checks cover85px row heights/bottom-edge alignment, matching admin border colors and neutral dark/existing light surfaces at320/375/768/1024/1440 (seller adds812 landscape/1200 desktop). Three previews inspected: dark admin1440, light admin375, light seller1200. Three test JS syntax and changed-line Git whitespace PASS. No live provider/call/device/physical-keyboard or screen-reader acceptance; no backend/schema/config/push/deploy changes. Existing suites extended with geometry/theme checks; initial new color-parser assertion had a template-string regex escape error, corrected in test only. No product workaround. Attributable source/tests: assets/css/seller_topbar.css, assets/css/admin_topbar.css; existing tests/admin_topbar_browser_test.mjs, tests/seller_mobile_messages_browser_test.mjs, tests/seller_topbar_browser_test.mjs. [[90 System/Tasks/Records/TASK-20261120-014]].

## Branded seller-only maintenance v1.1 - 2026-11-20

`tests/site_status_routes_test.mjs`: **211 PASS**, checking 87 inventoried guarded HTTP entries, 28 seller-only routes, and action-specific seller/Admin/public scopes on `backend/identity_api.php`. `tests/site_status_guard_test.mjs`: **25 isolated HTTP PASS**, including seller `face_token` blocking and authenticated Admin evidence/review availability. `tests/site_status_service_test.php`: **45 PASS**; fail-closed malformed-state cases emitted expected error-log lines. Thirty PHP syntax checks and four Node syntax checks pass. `tests/site_status_client_browser_test.mjs`: **331 PASS** across public/seller/admin × light/dark ×375/768/1440; checks include nonblocking Admin status, public access, seller overlay/fetch suspension, draft retention, reason escaping, theme contrast, keyboard focus cycling, and overflow. Four synthetic seller previews inspected: light/dark at375/1440. No live DB/account, active maintenance, migration/setup, deployment, screen-reader, or physical-device check. [[90 System/Tasks/Records/TASK-20261120-016]] · [[90 System/Runs/2026-11-20 175641 Branded Seller Maintenance Follow-up]]

## Admin UI full-width follow-up v1.2 - 2026-11-20

PASS: `tests/admin_moderation_ui_browser_test.mjs` **388**, `tests/admin_account_ux_browser_test.mjs` **127**, `tests/site_status_client_browser_test.mjs` **337**, and `tests/admin_site_updates_ui_browser_test.mjs` **263** synthetic browser assertions. Coverage asserts Moderation/Profile content width, Profile Role spacing, Admin card surface and heading contrast, removal of only the general Admin maintenance banner, preserved public announcements/seller behavior, and continued Site Updates status/control. PHP lint for `admin/pages/moderation.php` and `admin/pages/profile.php`, Node syntax for three browser tests plus `assets/js/site-status.js`, and six synthetic light/dark previews pass/inspected. No live Admin session/database, physical device, or screen-reader check; maintenance state unchanged. [[90 System/Tasks/Records/TASK-20261120-016]] · [[90 System/Runs/2026-11-20 184244 Admin UI Full-Width v1.2]]

## Admin Site Updates and service modes v1.3-R2 - 2026-11-20

PASS: **103** isolated service assertions (expected fail-closed logs for malformed temporary snapshots), **36** isolated HTTP guard assertions, and **263** route/source assertions over **87** guarded entries. PHP syntax passes for all 87 guarded route files and the changed maintenance PHP/fixture; selected Node syntax checks pass. Browser suites pass: **609** site-status assertions (public/seller/Admin plus PHP-generated Sell maintenance pages, light/dark at375/768/1440), **533** Site Updates assertions (permission/tab behavior, text contrast, XSS-safe announcements, pagination, explicit enable/disable/confirmation/stale handling), **24** identity UI cases, and **127** Admin account/Profile regressions. Representative light/dark mobile and desktop previews were inspected, including the generated public maintenance page under both Sell-page-only and all-services mode.

The browser/HTTP cases are synthetic; malformed snapshot logs are expected fixture evidence. No live MySQL, authenticated Admin/seller account, migration, maintenance activation, deployment, screen-reader, or physical-device validation was performed. Owner acceptance remains separate. [[90 System/Tasks/Records/TASK-20261120-016]] · [[90 System/Runs/2026-11-20 193429 Admin Site Updates and Service Modes v1.3-R2]]

## Admin transaction modal layering v1.0 - 2026-11-20

`tests/admin_transactions_modal_browser_test.mjs` loads the production Admin shell and transaction modal CSS with a synthetic modal fixture. Its **81 assertions PASS** across light/dark at 375/768/1440px: fixed backdrop covers the viewport, modal layer is above topbar/sidebar, hit-testing reaches the backdrop over the shell, theme-specific panel surfaces remain correct, no overflow occurs, and close state toggles. Three previews inspected (light 375/1440, dark 1440). No live Admin transaction/account/API, physical-device, screen-reader, or deployment check. [[90 System/Tasks/Records/TASK-20261120-020]] · [[90 System/Runs/2026-11-20 212655 Admin Transaction Modal Layering v1.0]]
