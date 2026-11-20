---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-20
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Quinn]]"
---

# Verification and QA

Current local behavior: [[90 System/Runs/2026-11-19 213300 Submission Selfie v6|submission selfie v6]], active2026-11-19. Earlier dated ID-review sections below remain history.

Owner: [[90 System/Agents/Quinn|Quinn]]. Review date: **2026-11-14**.

## Implemented in source

- `tests/seller_preference_button_alignment_browser_test.mjs`: latest **232 checks PASS** with actual form markup (dynamic PHP values stripped), actual seller CSS and synthetic settings. Verifies select/login-button edges in one row above500px, matching widths/right edges/heights, footer spacing, stacked full-width375px, English/Filipino labels, focus and overflow across light/dark1200/542/375px. Earlier112/136 iteration results remain historical in the run. Three synthetic previews inspected; no authenticated preference POST/live account/physical phone. [[90 System/Runs/2026-11-20 053913 Seller Preference Button Alignment]].
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
