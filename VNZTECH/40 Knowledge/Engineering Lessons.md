---
type: engineering-lessons
status: active
created: 2026-11-19
updated: 2026-11-20
tags: [engineering, agents, lessons]
---

# Engineering Lessons

Shared learning memory, requested by the owner on 2026-11-19. Keep practical lessons with evidence, affected roles and remaining limits. New evidence can correct an old conclusion; avoid invented success, universal rules from one fixture, or private user data. Current implementation/checkpoints remain in [[Task Center]].

## Identity and review boundaries — TASK-20261119-004

- **Do:** authorize submission from server state tied to the actor, immutable evidence and an authorized reviewer. **Avoid:** accepting a browser score/descriptor/`verified=true` as proof. Client checks can be bypassed. Roles: Aegis/Forge/Quinn. [OWASP validation](https://cheatsheetseries.owasp.org/cheatsheets/Input_Validation_Cheat_Sheet.html).
- **Do:** separate account activation, email verification, ID approval, submission ownership and login proofs. **Avoid:** granting identity from matching names/emails, UI onboarding markers or a different claim capability. Safe guest carryover needs exact proof and atomic reservation/finalization. Evidence: inspected signup/email/guest-claim paths and approved task 004 design; implementation tests remain in progress.
- **Do:** expire pending as well as abandoned/decided attempts, and verify the real cleanup schedule before activating capture. **Avoid:** promising automatic deletion because a CLI file exists. Roles: Aegis/Vega/Rhea/Quinn; final review identified the pending-state/scheduler gap and v2 includes both.
- **Do:** keep IDs/selfies outside public item-photo storage, encrypt privately and serve only through authorized non-cacheable endpoints. **Avoid:** copying evidence/keys/descriptors to this vault, Git, public URLs or logs. [OWASP uploads](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html).
- **Do:** check every creation entry point and consume guest grants atomically with submission INSERT. **Avoid:** a UI-only block, account-to-guest fallback on failed authentication, or consuming a grant before a later rollback. Roles: Kai/Orion/Forge/Quinn.
- **Do:** distinguish model-download, model-load, mocked-camera, actual-camera and physical-phone results. **Avoid:** claiming biometric accuracy or mobile compatibility from a single ID or a resized desktop viewport. Roles: Nova/Lyra/Quinn.

## Admin action-list ordering — 2026-11-20

- **Do:** prioritize actionable PENDING requests ahead of APPROVED and terminal statuses, then preserve newest-first order within each priority group. Include an older pending row and newer resolved row in the regression fixture so timestamp-only sorting would fail.
- **Evidence:** the admin Trade Requests SQL now uses this order; the focused test extracts the actual SQL ordering clause and confirms it with synthetic SQLite rows. This changes presentation order only, not status transitions or notification counts.
- **Source/task:** [[90 System/Tasks/Records/TASK-20261120-008]] · [[90 System/Runs/2026-11-20 011633 Admin Trade Request Ordering]] · `admin/pages/trade_requests.php` · `tests/admin_trade_request_order_test.py`.

## Localized paired-button sizing — 2026-11-20

- **Do:** size paired actions against the widest supported label, not only the English screenshot. Test rendered widths and text wrapping for each translation and responsive breakpoint; a 174px guess failed when the login-preference label rendered at 189.72px. Check placement too: equal widths alone did not resolve the mismatch while one save action sat beside its control and the other had a separate footer row.
- **Evidence:** scoped 190px minimum width and matching action-row placement now give equal button widths, single-line labels, and no horizontal overflow for English/Filipino, light/dark, desktop/mobile in 136 synthetic browser checks.
- **Source/task:** [[90 System/Tasks/Records/TASK-20261120-009]] · [[90 System/Runs/2026-11-20 053913 Seller Preference Button Alignment]] · `assets/css/seller_dashboard.css` · `tests/seller_preference_button_alignment_browser_test.mjs`.

### Preference placement correction - 2026-11-20 08:29

Latest owner wants login save beside its select, overriding the earlier separate-row preference above. Keep helper text outside the aligned controls; equal button widths alone do not align a select with a button. Test actual form markup and intermediate widths:542px exposed scoped login width190 versus generic mobile footer width498. Use narrow form selectors with consistent breakpoints/heights, keep mobile stretch, and verify the footer's right alignment as well as its width.232 actual-form/CSS checks pass; source/evidence: [[90 System/Tasks/Records/TASK-20261120-009]].

## Implementation findings — 2026-11-19

- **Do:** check current active user, active admin and active reviewer role for private evidence access. **Avoid:** trusting an old session flag after an account/role is disabled. A read-only review caught this before activation; regression checks now cover it.
- **Do:** check approved guest grant expiry during both signup reservation and email finalization. **Avoid:** promoting an attempt after its one-use approval expires between those two steps. Pending carryover remains pending; verification of an email alone cannot grant identity.
- **Do:** put user creation, exact identity reservation and OAuth association in one transaction; keep email-token consumption/identity finalization/guest claim changes atomic. Send SMTP after commit. **Avoid:** deleting a committed/existing profile picture when mail delivery fails. Eleven isolated signup HTTP cases exercise these boundaries.
- **Do:** use row locks and a conditional unused-grant update in the same submission transaction. Ten actual isolated InnoDB checks confirm one winner in a two-worker race, rollback restoring the grant, and revoke waiting for the active submission before blocking later requests.
- **Do:** interpret server UTC timestamps explicitly (`Z`) before browser-local formatting. **Avoid:** a silent eight-hour shift in Manila. Admin source timestamps are labeled UTC.
- **Do:** refresh status after BFCache/form reset, stop camera tracks on capture/cancel/hidden/page leave, and revoke preview blob URLs. **Avoid:** stale approved UI for a consumed guest grant or identity media in ordinary item POSTs.
- **Do:** read an existing dashboard wrapper's argument contract before wiring refresh/navigation. Its second argument is a parameter object, not a history flag. Queue refresh now passes only the page name; the browser fixture checks the actual call shape.
- **Do:** preserve owner/SACL metadata when narrowing file DACLs; validate both existing scheduled-task configuration and a fresh successful run. **Avoid:** assuming a tool's sandbox escalation grants Windows administrator rights. Actual SYSTEM/current-user task registration returned Access denied; feature activation remains dependent on a real administrator token. Native `icacls` DACL restriction/verification succeeded after `Set-Acl` requested an unavailable security privilege.
- **Do:** pass Windows PHP session paths through `session_save_path()` or quoted forward-slash INI values. A short-name path containing `~` was parsed incorrectly by an unquoted `-d` fixture option; corrected fixtures pass without warnings.

Evidence: [[90 System/Runs/2026-11-19 163005 Identity Verification v2]] and [[90 System/Tasks/Records/TASK-20261119-004]]. These are source/fixture/runtime findings; no personal ID, real camera or physical phone was tested.

## Existing UI evidence to reuse

- **Do:** remove redundant labels rather than squeeze them into narrow headers. Tie full-bleed header margins and inner padding to the same responsive gutters as the content; keep action buttons/avatar non-shrinking and hide/truncate optional names at measured narrow widths. Test intermediate and smallest widths, not just desktop/mobile endpoints. **Evidence:** seller Topbar v2 passes five widths in both themes with shell-edge, control-size, glyph-centering, and overflow assertions. [[90 System/Tasks/Records/TASK-20261120-010]] · [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]].
- **Do:** inspect actual DOM selectors before fixing image fit. The header image was itself `.profile-avatar`; an old `.profile-avatar img` rule missed it. **Avoid:** applying nested-wrapper width/height to the direct image. Historical evidence: [[90 System/Tasks/Records/TASK-20261119-003]], not new identity work. Roles: Iris/Quinn.
- **Do:** use active public/seller/admin theme tokens for every new or modified UI state, including errors, camera notices, pending reviews and confirmations. **Avoid:** fixed dark-only surfaces. Light/dark desktop/mobile checks and their limits are part of completion, per owner requirement in repository instructions.
- **Do:** keep an explicit default-off bulk-format marker so new account bulk lots do not reinterpret legacy wholesale/public records; verify actor/source guards, mixed categories, quantity certainty, total-per-lot pricing, and omitted per-device fields on the real submission route. For UI checks, pair actual seller CSS/templates/functions with synthetic account data and label authenticated/live-device/migration gaps honestly. Evidence: 41 isolated HTTP checks and ten grouped desktop/mobile light/dark browser checks in [[90 System/Runs/2026-11-20 003109 Seller Bulk Submission Flow]]. Roles: Orion/Vega/Iris/Quinn.

## Task and knowledge hygiene

- Read current task + relevant module/catalog entries; preserve historical snapshots and unrelated dirty-tree work. Save the last completed step and next safe action for Codex/Copilot handoff.
- Owner scope answers, implementation approval, passing checks and result acceptance are distinct evidence. Agents recommend; owner approves. Current explicit v2 approval is recorded in [[90 System/Tasks/Records/TASK-20261119-004]].
- API choice belongs in [[40 Knowledge/Integration Catalog]] with official sources, pinned version/data flow/limits. Reuse a concise shared entry instead of duplicating long histories in all 20 role files. This increases retrievable project knowledge for future runners.

[[40 Knowledge/Knowledge]] · [[90 System/Agent Directory]] · [[90 System/Workflows]]

## Administrator activation follow-up - 2026-11-19 17:07

The earlier Access-denied blocker was resolved by owner Administrator setup, corroborated by read-only task/heartbeat/private health/live API checks. Do not confuse sandbox escalation with Windows elevation, or successful setup with personal-ID/device testing and acceptance. Preserve failed attempts as history; append new runtime evidence and update the current checkpoint. [[90 System/Runs/2026-11-19 163005 Identity Verification v2]]

## Automation revision - 2026-11-19

Owner changed the desired policy from manual ID review to automatic face matching. Preserve approved v2 evidence as history; prepare explicit v3 approval before changing the trusted decision source. The lasting security boundary is trusted server authorization tied to immutable evidence, whether implemented by an authorized human or an approved server matcher. Hiding admin buttons alone leaves a manual API bypass; changing browser distance into a boolean is also insufficient.

Use Node tensor inputs to evaluate a no-native-canvas CPU path before installing legacy native dependencies; blank inference proves runtime loading only. Record policy thresholds/calibration honestly; distance is not a percent. Separate face comparison from ID-document authenticity/liveness. Cloud free-plan marketing needs account/region verification, provider consent and outside-webroot credentials; do not silently send old captures or enable paid fallback. Inspect SDK examples before reuse: the reviewed official Face++ Python example disables TLS verification; keep certificate verification enabled in real integrations. No user photos or secrets processed for this review. [[90 System/Tasks/Records/TASK-20261119-004]]

## Dual-engine design review - 2026-11-19 17:34

- **Do:** specify AND versus fallback before implementation, bind both passes to identical server-created actual-ID/selfie crops and original/crop/policy hashes. **Avoid:** comparing one engine to a profile photo, averaging unrelated scores, or accepting a stale approval after a policy change. Owner selected AND; this is reviewed design, not passing runtime evidence.
- **Do:** stage adapters/mocks independently of account setup, clearly label the live mode, and switch only when provider contract/health and consented same/different matching tests pass. **Avoid:** claiming cloud integration is activated because code compiles or blank-model inference works. After dual activation, missing keys/outages block instead of silently downgrading.
- **Do:** require fresh provider consent/capture for the changed data destination and treat cropped faces as biometric data. **Avoid:** uploading retained manual-review evidence under earlier local-only consent or copying secrets/test IDs into knowledge notes.

Evidence: owner scope replies and read-only Aegis/Forge review in [[90 System/Tasks/Records/TASK-20261119-004#Concrete implementation plan v4 - dual automatic matching (approved)|v4 proposal]]. Approval/source/provider/accuracy verification still pending; no external photos processed.

## Face-only revision intake - 2026-11-19 21:28

Read-only findings: V6 filenames exist but are zero bytes; a prior handoff claims more than the present source supports. Reconcile file contents, actual integrations and approval provenance before resuming; a filename or passing lint on an empty file proves no feature. Face presence needs a real single-image detector action, never selfie-to-itself comparison described as identity verification. Removing the ID card/admin nav must be coordinated with its server gate and manual API authority. Reuse existing submission review/view_submissions for a protected inline photo instead of adding another module. These are proposal findings, not implementation/test results. [[90 System/Runs/2026-11-19 212807 Face-Only Submission Revision]]

## V6 implementation lessons - 2026-11-19 21:53

- Check actual source contents: empty files and old task metadata do not prove a staged feature or owner approval. Reconcile runner/scope before coding.
- Use a real single-image detector action for presence. Never compare a selfie with itself or call a detected face identity/liveness proof. Keep actual blank inference separate from mocked positive route fixtures.
- Replace the server gate and retire manual APIs together with ID-card/navigation removal. Legacy enabled=false in v6 means the old gate is retired; the new selfie gate stays mandatory.
- Refresh short-lived capture nonces with CSRF on long drafts; bind them to session/context/account and consume only after transactional submission commit. Account/public-guest paths remain distinct.
- Keep the item form alive during face-server errors using fetch/known receipt redirects; no automatic POST retry after ambiguous network failure. Stop tracks/revoke blobs on every exit.
- Inspect live PK type before additive foreign keys. Actual submissions.id is BIGINT UNSIGNED; new migration rendering preserves it without altering old rows.
- Latch terminal time at the real database status transition. Generic updated_at changes for seen/notifications and must not move a30-day deletion boundary. Actual InnoDB rollback/transition tests verify the trigger.
- Extend legacy orphan cleanup's reference checks when another table stores private encrypted files. An aged linked selfie must survive; a genuinely unlinked24h orphan must delete. Two dedicated shared-cleanup assertions now pass.
- Remove only files this request actually created; fopen xb failure must never unlink an existing path. Sanitize new storage PDO errors and keep committed evidence after SMTP failure.
- Recheck active admin AND active role AND existing permission at private reads; UI hiding/session flags do not authorize a photo. Boundary/expiry/hash/cache checks passed with synthetic evidence.

Evidence: [[90 System/Runs/2026-11-19 213300 Submission Selfie v6]] / [[90 System/Tasks/Records/TASK-20261119-004]]. These are actual source/test/deployment findings, not model retraining, biometric accuracy or real-device acceptance.

## Private photo viewer v6.1 - 2026-11-19

- Use a native top-layer dialog for a photo inside a transformed/scrolling review modal; consume its Escape before the outer modal handler, and preserve focus/scroll. Keep active-admin light/dark tokens on the dialog/backdrop/controls.
- Load private photos on demand through current authorization; clear src, abort stale requests and revoke temporary Blob URLs on close, error, ancestor hide and AJAX replacement. A hidden inline image still fetches; visibility alone is not lazy access.
- Test fragment removal while loading AND after display. The first actual-template test caught a real locked-scroll/open-detached-dialog defect: a later observe(body, attributes) registration replaced the same observer's earlier body subtree-removal options. Observe Document removals separately from Element ancestor attributes. The focused regression now exercises this boundary; source-only review had missed it.
- Distinguish synthetic JPEG/layout/lifecycle checks from a real authorized admin/device test; preserve historical test evidence and existing item-photo behavior.

Evidence/source: assets/js/submission-selfie-viewer.js, tests/submission_face_admin_browser_test.mjs and [[90 System/Runs/2026-11-19 222413 Submission Selfie Viewer v6.1]]. No personal image/URL/token belongs in these lessons.

## Photo drag and interrupted input v6.2 - 2026-11-19

- Preserve simple-click/keyboard semantics while adding drag: capture on the image button, apply a small movement threshold, suppress the generated pointer click after a real drag, and keep keyboard click handling available. Set touch-action:none on both zoomed viewport and image button; hiding scrollbars alone does not implement touch drag.
- Treat reset/resize while a pointer is held as an interrupted gesture. Review caught accidental re-zoom after release, repeated resets clearing suppression, and a fresh fit click being swallowed after release outside the image. Preserve pending suppression across repeated resets; clear it on a new eligible pointerdown before the zoom guard and explicitly during photo cleanup. Release capture on all exits and clamp pan to image bounds.
- Validate trusted input and isolate fixture limitations before changing product code. CDP mouse-move button metadata caused a capture assertion failure; correcting it exercised real capture. CDP touch drag works, but missing tap compatibility click also reproduced on an independent plain native button. Label the simulated compatibility-click path and keep physical-phone acceptance separate; do not claim it as a real mobile tap test.

Evidence: assets/js/submission-selfie-viewer.js and [[90 System/Runs/2026-11-19 224454 Submission Selfie Drag v6.2]]. API choices: [[40 Knowledge/Integration Catalog]]. No personal photo or device identifiers in shared memory.

## Persistent seller controls - 2026-11-20

- Put shared controls in the persistent AJAX shell, not a fetched page; initialize once and retain one timer. Keep profile refresh an explicit successful-save event; settings language already reloads the shell. Resume polling after bfcache return.
- A sticky header can stop sticking when its ancestor only declares overflow-x:hidden (the other axis becomes a scrolling container). Remove that overflow on the shell and bound dropdown width instead; actual scroll tests confirm the header remains at the viewport top.
- Separate attention count from unread semantics. Filter latest offer sender and owner server-side; never mark results seen from a GET menu reader. Clear stale feed on error/session expiry; keep count and bounded rows separate.
- Hiding chat UI must not remove its active media owner. Use a seller-only placement mode; verify default floating position and existing Close preservation with synthetic widgets. Fixtures prove node lifecycle, not live calls.
- Use inherited theme tokens and top-origin motion; inspect screenshots as well as computed layout. Preview caught garbled control glyphs; new seller controls use entities/Unicode escapes. Do not change unrelated admin/public layout to implement a seller header.

Evidence and reusable entry points: [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]. Iris/Echo/Quinn should read this entry and the linked domain notes before similar work. This is shared task memory, not automatic model retraining.

## Persistent seller controls - 2026-11-20

- Put shared controls in the persistent AJAX shell; initialize once, retain one timer and resume after bfcache return. Refresh profile explicitly after successful save. Language preference already reloads the shell.
- overflow-x:hidden on a sticky header ancestor can create an unintended scrolling container. Remove that shell overflow and bound panel width; actual scroll checks confirm sticky behavior.
- Separate attention counts from unread flags. Check latest offer sender and owner server-side; never acknowledge results from a GET reader. Clear stale feed on error/session expiry; separate total count from latest20 entries.
- Hide chat without destroying active media-owner widgets. Keep seller-only layout mode and test default floating placement. Synthetic widget lifecycle is not proof of live calling.
- Reuse theme tokens, top-origin motion, bounded viewport and focus restoration. Inspect screenshots as well as computed styles; previews caught broken glyphs, corrected for seller controls with entities/Unicode escapes.

Iris/Echo/Quinn reuse these lessons and linked module notes before similar work. Shared memory does not retrain models. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

## Touch category names and loading layers - 2026-11-20

- A narrow viewport alone does not test mobile CSS: enable touch capability and assert hover:none. Shared floating tooltips were intentionally hidden for hover:none, but the new inline menu reused that class; explicitly restore display for seller inline titles.
- Removing tooltip background is insufficient: border and box-shadow can still look like a white text highlight. Reset the entire decoration only in the new placement mode.
- Persistent header z-index must sit below the page loading veil. Test actual elementFromPoint interception at header controls, not just numeric stacking values. Keep shared admin/public loader/tooltip CSS intact when the defect is seller-only.

Evidence: seller_topbar.css + seller_topbar_browser_test.mjs;281 fixture browser checks and two inspected category screenshots. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

## Docked conversations and fullscreen geometry - 2026-11-20

- Keep category/list popover geometry separate from conversation-window geometry. A topbar selector may open down while its conversation docks at bottom; fullscreen host must remove dock width/max-height/insets and fill the viewport. Do not limit maximize to an820px-wide dropdown.
- CSS state names may control visibility as well as appearance. Shared mobile CSS hides the body when a maximized window lacks is-mobile-chat-open; seller maximize now selects the conversation state explicitly and clears is-workspace-list-open. Preserve deliberate Back/list navigation.
- Use the production chat template in layout fixtures. A plain textarea placeholder missed transaction-context height consuming the short-screen composer. Limit the context card to a scrollable52px on short screens and retain messages/input. Test521-700px as well as phone/desktop breakpoints; mobile context layout is explicitly single-column through700px.
- Fullscreen success means actual viewport coverage plus visible messages/input and usable controls, not merely a box within screen bounds. Measure desktop available width using documentElement.clientWidth to exclude the scrollbar. Exercise repeated restore with an unsent draft and explicit list-to-conversation transitions.
- Inspect layering visually and through elementFromPoint. The old launcher z-index could paint over a fullscreen window hosted in a lower stacking layer; seller conversation host now sits above the icon. Loader remains above the whole header.

Sources: seller_topbar.css, floating-messages.js and actual-template seller_chat_widget_fixture.php/browser tests. Initial479-case browser pass plus screenshot review; final overlap regression recorded in linked run. No live messages/calls/phone evidence inferred. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

Chat v3 final evidence:503 browser checks pass after launcher-layer fix, including actual header-control occlusion and short-screen widget content. Two final theme/placement screenshots inspected. Earlier479 checks preceded the extra layering assertion. Keep these fixture claims separate from actual message/call/physical-phone behavior. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

## Independent menus and multi-chat windows - 2026-11-20

- Model transient menus separately from conversation lifetime. Outside-click/bell/menu closing must not hide/destroy persistent docks. The v3 single-mode visibility coupling caused the owner-reported disappearing-chat behavior.
- Separate visible slots from mounted capacity. Keep stable widget identity, per-window origin/order/draft, and preserve active-call-owner nodes. Hiding for overflow/minimize is not Close. Never silently delete an older chat just to open another; explicitly report the approved10-widget cap.
- Back should navigate to its recorded origin rather than retitle/reuse a conversation frame for an unrelated overall list. Minimize that window and open the origin under topbar; other docks remain. Fullscreen temporarily masks other docks, then restores their arrangement.
- Measure contact-list height through the actual fifth row, including heading/padding, then cap to viewport; fixed pixel guesses can expose a partial sixth row or break long names/call controls.
- A hover popup needs enough time to cross its gap. Delay closing and recheck hover/focus; provide click/tap and keyboard options. Tests exercise actual pointer travel and ArrowDown/Enter.
- Put mobile close control inside the open sidebar's stacking layer and delegate to its existing toggle; synchronize aria/focus with observed sidebar state instead of creating a competing open-state implementation.
- Match production encoding in fixtures. Missing meta charset garbled Unicode symbols in screenshots even though production source was correct. Added UTF-8 only to the fixture, then ran187 focused assertions and inspected previews; do not change valid product glyphs to fix a fixture.

Evidence:621 full checks and187 focused checks; four final theme/layout previews; sources in linked run. Shared memory for Iris/Echo/Quinn, not automatic model training. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

## Reference-sized windows and vertical rail - 2026-11-20

- Translate visual references into measurable user-facing contracts: about360x500px boxes,12px gaps, bottom alignment and bounded rail. Preserve current product theme/features; a reference to size/organization is not an instruction to copy branding or integrate a provider.
- Calculate visible slots from client width, box/gap size and reserved rail/gutters, then clamp to1..4. Do not tie mounted conversation capacity to display capacity; preserve keys/nodes/drafts while resizing to rail and back.
- Bound rail by available height and reserve +N when needed. Name labels need hover and focus behavior, with direct button/keyboard/touch selection. Loading widgets need a header-focus fallback when input is disabled.
- Reuse existing authorized image metadata rather than introducing a new endpoint. Allow safe local uploads paths only, reject traversal/unsafe schemes, and retain initials when URL is absent or load fails. Never store actual user images in lessons.
- Test actual browser button activation correctly. CDP keyDown with an Enter name alone did not emit default activation; include carriage-return text/keypress metadata. Direct-click diagnostic showed product selection worked, so product native buttons were kept instead of adding duplicate keyboard handlers.
- Geometry assertions alone are insufficient: verify message/composer content, safe avatar crop, rail hit regions, resize/draft retention and fullscreen separately. Match reference viewport height for useful previews.

Evidence:755 full assertions, three inspected reference previews; code/checks and actual limits: [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]. Relevant Iris/Echo/Quinn roles reuse shared notes; this is memory, not model retraining.


## Git credential cleanup v1 - 2026-11-20T11:24:01+08:00

Use placeholder names, never real credentials, as setup-template replacement keys. Check keys against example template. Fixing current files alone leaves secrets in earlier commits. Back up before approved rewriting; preserve unrelated files/refs and scan without printing values. Backups/other refs retain old secrets: do not upload backup or push all refs. Rotate provider secrets separately; rewriting history does not rotate them. [[90 System/Tasks/Records/TASK-20261120-011]].
