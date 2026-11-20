---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-20
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Iris]]"
---

# Interface and Portal UX

Current local behavior: [[90 System/Runs/2026-11-19 213300 Submission Selfie v6|submission selfie v6]], active2026-11-19. Earlier dated ID-review sections below remain history.

Owner: [[90 System/Agents/Iris|Iris]]. Review date: **2026-11-14**.

## CSS unit rules

Owner instruction recorded 2026-11-20: "rem = text; % = layouts; vw = full screen sections; px = borders."

| Purpose | Unit |
| --- | --- |
| Text | `rem` |
| Layouts | `%` |
| Full screen sections | `vw` |
| Borders | `px` |

Use these rules for future new or modified UI alongside the active theme and responsive checks. This is an owner design convention, not a claim of an existing CSS audit or conversion. This request changes shared notes/instructions only; existing application CSS remains unchanged. Shared instructions: repository and vault `AGENTS.md`; [[90 System/Agents/Iris|Iris]]. Request record: [[90 System/Tasks/Quick Requests#Q-20261120-004 CSS unit rules]].

## User requirement: light and dark themes

Recorded **2026-11-14**: every new or modified UI component must follow the active light/dark theme, including modals, popups, alerts, login errors, confirmations, forms, and other user-facing elements. Apply this across public, seller, and admin pages without another reminder. Reuse theme tokens and check readable text, status colors, actions, focus, backdrops, and small-screen layout in both modes.

This requirement is also stored in the repository-root `AGENTS.md`, vault `AGENTS.md`, and Iris instructions. It is a development default, not a claim that every historical page has already been audited.

## Implemented in source

- Admin review opens the private photo with **View submission selfie**. V6.2 adds bounded mouse/touch drag and focused arrow-key pan to 2x zoom, with grab/grabbing cursors and no zoomed scrollbars. Dragging does not reset; simple click/tap and toolbar/keyboard reset remain. Scoped native top-layer dialog, natural proportions, Close/Escape/backdrop, loading/read-error states and admin light/dark/mobile styling remain. Fragment replacement/ancestor close aborts requests, clears src/revokes the URL and restores focus/scroll; pointer capture is released on reset/close/disposal. Existing item-photo viewer and camera orientation unchanged. Source: admin/pages/submission_review.php, assets/js/submission-selfie-viewer.js, assets/css/admin_submission_review.css. Actual checks/limits: [[90 System/Runs/2026-11-19 224454 Submission Selfie Drag v6.2]]; original delivery: [[90 System/Runs/2026-11-19 222413 Submission Selfie Viewer v6.1]].

- Separate public, seller, and admin page/layout assets with client-side portal interactions.
- Public/seller/admin theme scripts, shared dialogs, Terms modal, password visibility/strength UI, and loader components.
- Seller language endpoint persists `en` or `fil` behind authentication and CSRF; the client has supported-label translations.
- Public selling/tracking/rates/trade interfaces and seller/admin submission/transaction pages exist.
- Messaging has shared embedded/floating interfaces and dedicated styling.
- Seller dashboard's direct header photo now matches `.dashboard-profile > img.profile-avatar { object-fit: cover; }` in `assets/css/seller_dashboard.css`. It preserves proportions with centered cropping inside the existing 48px circle; the separate nested-image rule and initial fallback remain intact. Previously only `.profile-avatar img` matched, missing the direct `<img>`. Scope/evidence: [[90 System/Tasks/Records/TASK-20261119-003]].
- A new guest-to-seller signup shows **Claim your guest submission** on the first dashboard visit, after existing reminders close. Actions are **Yes, claim it** and **Maybe later**; a persistent banner reopens the prompt after dismissal.
- The native dialog shows the account email, verification instructions, resend cooldown/errors, and a distinct verified-only state. **Email verified successfully** confirms verification; its description adds submission-link success only after actual ownership is confirmed. English/Filipino copy and seller theme variables are used.
- Verification status refreshes on focus/visibility and polls while its dialog is open. A verification transition reopens the popup after an earlier dismissal, waits behind existing reminders, and refreshes the current page fragment without replacing the dialog. My Submissions opens after acknowledgement.
- Invalid/expired/replaced links show an explanation and a resend action. Already-verified accounts instead see their actual verified state. Sellers without a guest marker can receive verification feedback with the claim steps hidden and a Continue to dashboard action.
- After successful password signup, users go directly to login instead of seeing an empty registration form. The login notice confirms account creation and explains sent/pending verification email status; it allows login before email verification. Old registration-success URLs forward to login with only allowed email-status flags.
- Registration profile-photo upload now styles the native file selector with a rounded blue 44px button, themed filename shell, hover/focus states, constrained mobile width, compact avatar spacing, and reduced-motion support. `assets/css/seller_register.css` reuses public light tokens with dark fallbacks; `account/register.php` connects the existing optional/format/5MB help text through `aria-describedby`. Native picker/filename and existing avatar-preview handler are retained. Approved scope and verification: [[90 System/Tasks/Records/TASK-20261119-002]].
- The shared seller alert now uses the dashboard theme variables instead of fixed dark colors. Approval alerts show a green check; other alerts reset to the notice icon. A wider card, inset message area, readable spacing, full-width actions, visible focus, and constrained scrolling support desktop and small screens. Styles live in `assets/css/seller_alert.css`.
- Shared `VNZDialog` alerts/confirmations, including Login Failed, now resolve dialog colors from the active public/seller/admin theme. Portal theme flags take precedence over lower-priority flags. Danger actions, focus rings, 44px buttons, backdrops, and scroll constraints support both modes while preserving dialog queue behavior.
- Dashboard logout now uses the shared themed confirmation. Seller copy follows English/Filipino language settings with **Stay signed in / Manatili** and **Log out / Mag-logout** actions; admin uses English. Cancel, Escape, or backdrop dismissal does not navigate. Duplicate clicks are prevented while a confirmation is pending, and the admin logout no longer has the eager loader attribute.
- Shared confirmation links accept `data-vnz-confirm-cancel-label`; other confirmations keep their existing default Cancel text. The shared dialog script URL is now versioned by file modification time so updated behavior is fetched.
- Seller **Sell an Item** now keeps Per Piece separate from Bulk. Bulk permits mixed phone and scrap-board lots; each lot has photo upload, exact/estimated quantity, total lot asking price, and item-level Additional Information. The duplicate overall Seller Notes was removed; the existing selfie capture/check stays. The modal is larger with readable fields/buttons, visible keyboard focus, 44px controls, and active seller light/dark surfaces. Source: `account/dashboard.php`, `assets/css/seller_dashboard.css`, `assets/css/seller_theme.css`, `assets/js/seller-language-copy.js`. Ten grouped Chrome fixture checks and inspected 1200x900/375x812 light/dark previews pass using actual CSS/template behavior and synthetic seller values. The authenticated local dashboard redirected to login; no live account, camera, physical device, screen reader, or native file-picker test. [[90 System/Runs/2026-11-20 003109 Seller Bulk Submission Flow]].
- Follow-up: seller light mode now explicitly sets the bulk category native select's `color-scheme: light` and white option surface/dark text, overriding the legacy dark `.seller-item-card select option` rule. Dark mode retains dark options. Computed option contrast and light/dark color-scheme assertions pass in the same synthetic desktop/mobile browser fixture; no authenticated native-picker or physical-device preview. [[90 System/Tasks/Records/TASK-20261119-006]].
- Seller settings **DISABLE 2FA** keeps its existing submit action but now has a high-contrast red danger surface and white label in both seller themes. Light and dark focus rings are explicitly visible. The 2FA button regression uses the actual settings class and seller CSS; 8 synthetic browser checks pass across 1200x900/375x812 and light/dark, including text contrast, semantic red surface, keyboard focus and horizontal overflow. No authenticated settings action was run. [[90 System/Tasks/Records/TASK-20261120-007]].
- Seller settings login save sits level beside its dropdown above500px, with helper text below the row. Both save buttons are190px wide/46px high; notification save is right aligned with18px space above it. Below500px both stretch full width and login controls stack. Latest owner correction supersedes the earlier separate-row placement.232 actual-form/CSS checks pass across English/Filipino labels, light/dark and1200/542/375px; three synthetic previews inspected, no live preference POST. [[90 System/Tasks/Records/TASK-20261120-009]] · [[90 System/Runs/2026-11-20 053913 Seller Preference Button Alignment]].
- Seller Security actions now use compact, page-scoped horizontal sizing and 12px labels while retaining their 42/46px heights; the authenticator login-preference action fits its content above500px and stays full-width on mobile. Profile and Settings sizing is unchanged. The Security lock icon is grayscale in active/inactive states and both Seller themes. Source: `account/pages/security.php`, `assets/css/seller_account.css`. PASS: 448 actual-source synthetic browser assertions across light/dark at375/499/500/542/768/1440px; four previews inspected. No authenticated account, live POST, physical-device, screen-reader, or deployment check. Two older Seller Security tests still expect its controls in Settings; see [[90 System/Tasks/Records/TASK-20261120-021]] · [[90 System/Runs/2026-11-20 215336 Seller Security UI v1.0]].
- Seller topbar Plan v2 removes the redundant welcome greeting and portal eyebrow. Header gutters now align with the sidebar/content edge; bell/chat remain44px and avatar48px without flex compression, the profile name truncates/hides at narrow widths, and the chat glyph uses a centered lighter stroke. Existing theme/actions are unchanged.245 synthetic actual-template Chrome checks pass for light/dark at1200/768/440/375/320px; no authenticated seller or physical-device preview. [[90 System/Tasks/Records/TASK-20261120-010]] · [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]].

## Obsidian Agent Office — 2026-11-19

- Separate from the PHP portals, a local Obsidian custom view now renders an original isometric office for the existing 20 named agents across six teams. It supports agent selection, exact agent/module-note navigation, search, a list alternative, pause/reduced motion, and Obsidian light/dark tokens. Container queries handle narrow split panes independently of the app window width.
- The native plugin files are installed under `VNZTECH/.obsidian/plugins/vnz-agent-office/`. Command: **VNZ Agent Office: Open Agent Office**; view type `vnz-agent-office`; optional protocol link `obsidian://vnz-office?vault=VNZTECH`. First successful activation opens the view once and stores the welcome flag locally.
- The office is a visual directory with decorative idle animation. It makes no AI/network calls and displays no invented work/online status. Existing notes are read/opened; the office does not execute the reusable agent instructions.
- 2026-11-20 update: the office reads an optional `agent_status` mapping from the Current Task pointer. Supported states are `idle`, `working`, `reviewing`, `done`, and `blocked`; unknown names/statuses and unavailable task records stay unavailable/idle. Status drives only scene/list/detail visual treatment and does not prove that a background AI worker or external process is running. Live Obsidian reload is pending. [[90 System/Runs/2026-11-20 084600 Live Agent Office Activity]].
- The owner later supplied a screenshot showing VNZ Agent Office enabled. The CLI check reported disabled; the updated company/task build now requires plugin reload before live UI verification. No owner app/ribbon/workspace settings were changed by this coding task. [[Agent Office]] provides the reload steps and fixture preview.
- Source files: `VNZTECH/.obsidian/plugins/vnz-agent-office/{office-view.js,office-scene.js,styles.css,manifest.json}`; generated `main.js` is built by `VNZTECH/90 System/Tools/build-agent-office.cjs`. The native-DOM regression check is `VNZTECH/90 System/Tools/agent-office-test.cjs`.

### Company and task-memory update

- Company data is centralized in `VNZTECH/90 System/Company Roster.json` and bundled into plugin v1.1.0. All 20 role notes retain names, old aliases, module scopes/procedures, and now state position, department, strategy, deliverable, handoff, and owner approval boundary. The scene seats them in Leadership, Product & Research, Engineering, Security, Design & QA, and Operations.
- CEO Approval Desk opens [[Task Center]], [[Approval Inbox]], and [[Decision Log]]. Agent cards show actual roster strategies. The Current Task card reads only `90 System/Tasks/Current Task.md`: recorded title/status/approval, writer, checkpoint, next action, and timestamp; it opens a strictly validated existing canonical task path. Metadata does not authorize tools or execute AI.
- The office refreshes on relevant pointer-file create/modify/delete/rename and manual refresh. Unknown/invalid metadata, missing pointer/record, parse/read errors, and stale asynchronous responses cannot silently display a fabricated idle/current state. View close unsubscribes events and invalidates outstanding reads. Text is inserted with `textContent`.
- Root/vault `AGENTS.md` and repository `.github/copilot-instructions.md` point runners to the same compact memory and owner-approved scope. Sharing depends on accessible/synchronized files; no autonomous cross-runner task engine or approval-security lock was added.
- Active task/result evidence: [[90 System/Tasks/Records/TASK-20261119-001]]. The new build's live Obsidian reload, screen-reader, and device coverage remain separate from fixture checks.


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

- Seller-header photo follow-up (2026-11-19): four Chrome fixture cases (light/dark × 1200px/true 375px) passed all 22 checks each using actual CSS and representative matching header markup. Synthetic portrait/landscape circles retained proportions with centered cover in 48px avatars; fallback and 45px nested submission avatars retained. Layout/theme/overflow checks and localhost CSS GET passed; screenshots inspected. No authenticated account/original user photo, physical device, screen reader or other-browser check was performed. Evidence: [[90 System/Tasks/Records/TASK-20261119-003]].
- Registration upload follow-up (2026-11-19): PHP lint and real public-page GET passed. Four Chrome fragment-fixture cases (light/dark × 1200px/true 375px mobile) passed 18 checks each: native/help semantics, 44px button, filename/overflow, contrast/hover/focus, keyboard activation with intercepted picker, synthetic select/replace/cancel/clear and avatar preview, live theme switch, reduced motion, and no JS errors. Focus accent was corrected after the proposed cyan measured below 3:1 on light surfaces; final focus measures 3.67–4.95:1. Screenshots inspected. OS-picker interaction, live signup, physical devices, screen readers, and other browsers remain unverified. Detailed evidence: [[90 System/Tasks/Records/TASK-20261119-002]].
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

## ID verification v2 - 2026-11-19

Added shared ID/selfie enrollment card and restricted admin review queue in includes/identity-verification.php, assets/js/identity-verification.js and assets/css/identity_verification.css; public/seller/admin dashboard integrations. Active-area light/dark tokens, 375px/desktop layout, keyboard focus, camera/blob cleanup, retry/status/carryover and stale-page refresh. Twelve real-Chrome template/style/script cases pass with synthetic API/camera/model boundaries; seven screenshots inspected. Actual three models load on CPU. Physical phone/ID/camera/screen-reader/other browsers untested. Feature disabled pending administrator setup.

[[90 System/Runs/2026-11-19 163005 Identity Verification v2]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]

### Activation follow-up - 2026-11-19 17:07

Earlier disabled/setup-pending state is historical. Owner Administrator setup completed; independent live form/API/private health and successful daily03:00 cleanup readback confirm local activation. No personal ID/device/human-review test or owner acceptance inferred. [[90 System/Runs/2026-11-19 163005 Identity Verification v2]]

## Submission selfie v6 - 2026-11-19 21:53

v6 removes old ID cards/scripts from public/seller item forms and uses includes/submission-face-capture.php plus submission-face-capture.js/CSS. Native themed dialog supports consent, permission denial, retake/cancel, short mobile scrolling, keyboard focus/Escape, track/blob cleanup and duplicate-submit prevention. Fresh nonce retrieval handles long drafts; failed server face checks retain item fields for retake. Public/seller eight theme/viewports and admin four inline theme/viewports pass; camera streams are synthetic, real-device testing remains separate.

[[90 System/Runs/2026-11-19 213300 Submission Selfie v6]] - [[90 System/Tasks/Records/TASK-20261119-004]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]

## Seller Topbar v1 - 2026-11-20 09:26 Asia/Manila

Persistent sticky seller-shell header replaces the dashboard-only greeting/profile. Greeting left; bell, existing chat launcher,48px cover avatar/name right. Profile opens the existing AJAX Profile page and refreshes after successful save. Mobile menu moves into the header; compact375px layout wraps clearly. Dropdowns open below the bar, share seller theme tokens, support Escape/outside/Close, focus restoration and reduced motion. Seller panels stay viewport-bounded; the mobile sidebar sits above the header when open. No second bottom chat launcher.

Sources: account/dashboard.php, account/pages/dashboard.php, account/pages/profile.php, includes/seller-topbar.php, assets/css/seller_topbar.css, assets/js/seller-topbar.js. Actual PHP component with synthetic actor/API, real assets:79 browser assertions at1200/375 light/dark and375x480 short screen;8 synthetic screenshots produced,4 inspected across iterations. No authenticated live seller, real phone or screen-reader acceptance claimed. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Topbar v2 corrections - 2026-11-20T10:06:18+08:00

Current header has no greeting/portal eyebrow following owner-approved Copilot v2; earlier v1 description is historical. Owner reported loader excluding the header and decorated/missing chat category titles. Seller-only seller_topbar.css now raises #vnzLoader above header/mobile sidebar and makes category titles inline with transparent background, no border/shadow, including hover:none. Existing v2 gutters/fixed icon sizes/actions preserved.281 synthetic Chrome checks pass across1200/768/440/375/320 light/dark; actual touch-capability emulation confirms hover:none at440/375/320. Overlay hit-testing covers header; mobile/light and desktop/dark category screenshots visually inspected. No physical phone/live seller acceptance. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Current Chat v3 behavior - 2026-11-20T10:20:37+08:00

Owner explicitly approved **"I-apply ang Chat v3"**. This supersedes v1's below-header conversation geometry: Chat icon/category/list selector remain in topbar; selected conversation docks bottom-right12px, bounded by viewport. Fullscreen fills available desktop viewport with12px inset and full mobile viewport; Restore returns to bottom and retains unsent draft. Seller-only CSS plus mobile/list-state correction avoids the blank body. Short-screen transaction context scrolls at52px to preserve the message/composer area. Previous v2 heading removal/alignment/icon sizes and loader/touch-label fixes remain.

503 actual-component/production-assets synthetic Chrome checks PASS at1200/768/600/440/375/320 light/dark and375x480 short screen. Real includes/transaction-chat.php template rendered with synthetic helpers/data; message/composer visibility and actual fullscreen geometry/hit-testing checked, rather than a plain textarea placeholder. Two final previews inspected (mobile dark fullscreen, desktop light dock), with earlier review catching corrected launcher overlap. No physical phone, live seller, screen-reader, real send/call/provider acceptance. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Current Chat v4 UI - 2026-11-20T10:38:28+08:00

Owner **"I-apply ang Chat v4"** approved the concrete amendment. Seller people dropdown now fits five complete contact rows (or fewer on short viewports), with internal scrolling and existing call controls. Contact-list height is measured through the fifth actual row and capped against available viewport. Back minimizes just that chat and returns to its originating topbar list/category menu; it does not keep the old titled bottom workspace list. Normal/fullscreen conversations survive outside click and bell/menu opening.

Desktop>=900px shows two360px dock windows; smaller screens show one responsive window. Other mounted/minimized chats appear in a numbered side selector, usable by hover/click/keyboard/touch interaction. Count is hidden open windows, separate from unread badge. Selection swaps visibility without removing widgets/drafts; maximum10 mounted chats gives an explicit capacity message, no silent replacement. Fullscreen temporarily hides other docks; Restore/Escape restores arrangement. Mobile sidebar has an in-header44px burger/close synchronized with existing toggle and focus restoration. Current v4 supersedes v3 single-selected-window/Back and outside-dismiss behavior; prior records remain historical.

621 full synthetic Chrome assertions pass across1200/768/600/440/375/320 light/dark plus375x480 widget viewport;187 focused assertions pass after adding explicit UTF-8 to the fixture page. Four final previews inspected: desktop light two docks/overflow, mobile dark overflow, mobile light sidebar close, mobile dark five-contact list. No authenticated live seller/call/media/provider/physical-phone/screen-reader acceptance. Sources and limits: [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Current Chat v5 reference layout - 2026-11-20T10:59:02+08:00

Owner **"I-apply ang Chat v5"** approved using supplied screenshot for chatbox size/organization only. Normal seller docks are about360x500px with12px gaps and12px bottom safety spacing. Width/height shrink to viewport on small/short screens. Reserved right rail/gutters total84px; available client width determines visible slots, maximum4 (verified1200:2,1440:3,1760/1920:4,768/375/320:1). This supersedes v4 fixed2-slot presentation, not its persistence/Back/sidebar/contact behavior. Fullscreen remains viewport-sized; Restore returns current dock arrangement.

Other mounted/minimized windows appear as48px avatar/initial shortcuts in a vertical right rail. Existing safe local peer avatar may be reused; initials otherwise. Hover/focus reveals name; click/tap/keyboard selects. Rail fits available height (up to6 buttons); if necessary reserve final button for +N remaining-chat selector. Count means remaining hidden chats, not unread;10 total mounted cap remains. No Facebook colors/SDK/provider copied. Five-contact list, independent drafts/call-owner nodes, sidebar burger, Back and outside persistence preserved.

755 full actual-component/assets synthetic Chrome assertions PASS; three reference previews inspected (light/dark1760x643 four docks, dark mobile rail/+N). Source: seller_topbar.css and floating-messages.js; browser fixture extended. No real seller/provider/message/call/physical-phone/screen-reader acceptance. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]


## Mobile Messages v1 - 2026-11-20T11:49:24+08:00

Seller-only <=1024 CSS px now opens a viewport-filling Messages inbox directly from topbar icon. Native DOM rows use existing authorized list/contacts/trade endpoints, existing last-message/unread/updated date fields when available, safe local uploads avatars or initials, real presence only. Search covers loaded authorized data; All/Unread/Private/Admin/Trades/Global filters, New message eligible contacts and supported Global entry when empty. No stories/AI/groups invented. Conversation uses existing widget full-screen; Back returns retained inbox search/filter/scroll; browser history holds in-app inbox/conversation entries. Explicit Close returns page; outside click does not dismiss. Widgets/drafts/call-owner nodes remain mounted, ten-chat cap unchanged. Desktop v5 geometry/rail preserved. Safe-area/visualViewport sizing, focus trap/modal labels, reduced motion and seller theme tokens supported. At <=400px height context card temporarily hidden and composer compact so messages/input stay reachable; context returns at taller height. Page scroll lock removed when closing or resizing to desktop. Initial inbox focuses heading, avoiding automatic keyboard opening. This supersedes earlier mobile dock/dropdown presentation only; historical v5 evidence above remains dated. Sources assets/js/floating-messages.js; assets/css/seller_topbar.css; tests/seller_mobile_messages_browser_test.mjs; tests/seller_topbar_browser_test.mjs. 216 mobile/tablet assertions PASS, plus 223 existing topbar/desktop regression assertions PASS. Actual production PHP topbar/widget markup and JS/CSS under synthetic API responses, no DB/session/providers. Mobile/tablet 320x667,375x812,768x1024,1024x768,812x375 in light/dark; additional812x260 short-height and1440 desktop resize. Verified direct inbox, authorized people/local avatar/presence, search/unread/New message, full-screen real-widget content/composer, outside-click persistence, Back/query/scroll/filter/draft restore, browser Back, explicit Close/Escape/focus wrap, delayed-open cancellation, loading/error/retry/empty, call-owner node retention and desktop resize/dimensions. Three final previews inspected: light/dark375 inbox and light375 conversation. No live sends/calls, actual device keyboard, physical phone/tablet, screen-reader or full authenticated admin matrix verification. [[90 System/Tasks/Records/TASK-20261120-012]].


### Alignment + Chat Space v2 - 2026-11-20T13:52:53+08:00

Seller submission/trade context now uses a native details/summary disclosure labelled View submission info, initially closed on each newly rendered widget. Expanding retains all original info/photo/payment fields in a bounded internal scroll area; collapsing restores conversation space without recreating widget/draft. Short keyboard-height view uses a bounded popup below the summary rather than consuming remaining message height. This supersedes v1 hiding the context entirely at very short height. Existing widgets retain disclosure state while switching; new widgets start closed. Seller message spacing reduced to6px; five short synthetic replies fit in tested normal viewports, not a universal five-message guarantee for long/media/call content or keyboard-reduced heights. Scroll-latest button centered at bottom of message area with centered SVG. Mobile sidebar close burger prepended and positioned left of branding with reserved padding; close/focus behavior retained. Desktop box dimensions, message/call transport and server permissions unchanged; admin/guest markup not wrapped. PASS:77 new actual-component browser assertions across1200x800,375x812,768x1024,1024x768 light/dark (native disclosure/click/Enter, five short replies, draft and message/composer preservation, centered arrow/SVG, left burger/close);216 mobile/tablet regression assertions including812x260;223 existing topbar/desktop regressions. PHP syntax, two JS syntax checks and admin PHP widget unchanged-info guard PASS. Two inspected previews: light desktop and dark phone collapsed-info conversation. No live seller messages/calls/provider, physical devices/keyboard or screen-reader acceptance. [[90 System/Tasks/Records/TASK-20261120-012]].


## Admin UX + Queue v1 - 2026-11-20T14:43:32+08:00

Admin header now reuses portal chat styling/lifecycle with an authenticated admin-topbar placement. Bell reads permission-filtered aggregate updates; profile displays existing name/initial because current admin record has no photo field. Phone/tablet <=1024 opens full-screen inbox/conversation with actual private/seller-request/guest/trade/global categories; desktop uses bounded existing multi-chat docks/rail. Admin submission/trade info now starts collapsed like seller. Context has one bounded, keyboard-focusable scroll region with hidden visual scrollbars and no nested info scrollbar. Shared message history is distinguished from shell page history in both admin/seller Back handlers so Back/Close does not reload review content. Existing permission gate for mounting admin chat remains view_submissions; backend chat membership/CSRF unchanged. Existing Submissions now has New/Under review/Waiting seller/Done tabs, My assigned/Unassigned/All filters, deterministic oldest/newest created_at+id sort, retained search/status/item type and12-row server pagination. Waiting seller requires latest active admin offer; latest seller counter-offer routes to Under review because offer_sent alone does not identify whose turn it is. Done means submission decision, not delivery/payment. Polling banner is read-only and explicit Refresh retains filters/current page and blocks while review modal open; no automatic navigation/assignment/accept/reject. Initial MAX(id)+COUNT snapshot plus newer-ID count/total-growth advisory catches usual late lower-ID insert commits; concurrent deletions can still mask exact arrivals. No durable event stream, exact queue position, ETA or production load guarantee. Seller cards show Next step based on existing submission/latest-offer sender/status, including whose response is needed. PASS:185 actual admin-component/endpoint/browser assertions on320/375/768/1024/1440 light/dark;235 SQLite query/pagination/synthetic-burst assertions;41 permission endpoint/controller assertions;16 pure seller next-step assertions; seller regressions77 chat-space +216 mobile/tablet +223 topbar/desktop assertions. PHP lint11 application files and JS syntax5 checks; existing admin decision beginTransaction/FOR UPDATE/status-validation ordering preserved by source check. Four final previews inspected: light375 queue/header, dark1440 multi-chat, light/dark375 inbox. Synthetic data/services only; no live account/provider/message/call writes, real MySQL concurrency/load test, physical phone/keyboard or screen-reader acceptance. [[90 System/Tasks/Records/TASK-20261120-013]].


## Header Alignment v1 - 2026-11-20T14:55:00+08:00

Seller sidebar brand/topbar and admin sidebar-top/topbar now share85px border-box height with no flex shrinking. Vertical padding centers existing controls; responsive side padding preserved. Each shell defines --portal-header-height. Admin content-area minimum height follows that value. Admin header/button surfaces use neutral dark panel colors; existing light-panel tokens retained. Sidebar/topbar admin border colors use one theme-aware value so the separator continues across the shell. This supersedes older varying76/82/92px header rows. Sources assets/css/seller_topbar.css, assets/css/admin_topbar.css; existing tests/admin_topbar_browser_test.mjs, tests/seller_mobile_messages_browser_test.mjs, tests/seller_topbar_browser_test.mjs. PASS:215 admin,226 seller mobile/tablet,225 topbar regression assertions using actual header/widget assets and synthetic data. Header-specific checks cover85px row heights/bottom-edge alignment, matching admin border colors and neutral dark/existing light surfaces at320/375/768/1024/1440 (seller adds812 landscape/1200 desktop). Three previews inspected: dark admin1440, light admin375, light seller1200. Three test JS syntax and changed-line Git whitespace PASS. No live provider/call/device/physical-keyboard or screen-reader acceptance; no backend/schema/config/push/deploy changes. [[90 System/Tasks/Records/TASK-20261120-014]].

## Admin moderation settings UI v1 - 2026-11-20

Moderation cards now follow page scroll rather than independent capped list scrollboxes; rules/review columns start at the same top without stretching the shorter side. Responsive form/columns, control sizing/focus and destructive action colors were checked against admin dashboard + operations + theme CSS in both themes at320/375/768/1024/1440. 130 synthetic browser assertions and four previews pass/inspected; no live admin record, physical device or screen-reader acceptance. [[90 System/Tasks/Records/TASK-20261120-015]] - [[90 System/Runs/2026-11-20 153700 Admin Moderation UI v1]].

## Admin Settings pagination and theme palette v2 - 2026-11-20

Appearance and Admin profile lead the Settings page; the other account and moderation panels retain their relative order. The entire Settings surface uses scoped tokens for active light/dark neutral panels, controls, actions and semantic states. Pagination stays on the client because the existing endpoint supplies all rules; test after filtering and mutations, including empty/one-page state, deletion clamp, keyboard-reachable controls, contrast and horizontal overflow. PASS:178 synthetic browser assertions at320/375/768/1024/1440 in both themes; four phone/desktop previews inspected. No authenticated account, screen-reader or physical-device acceptance. [[90 System/Tasks/Records/TASK-20261120-015]] - [[90 System/Runs/2026-11-20 161822 Admin Settings UI v2]].

## Admin Settings v3 spacing and black cards - 2026-11-20

Owner requested more space between the tightly adjacent Settings cards, and clarified a black/charcoal card theme on Admin Settings only. Maintain black surfaces regardless of the Admin light/dark selector, but retain that selector and do not restyle other Admin, seller or public pages. Change password spans the full settings grid. The moderation list displays at most three cards per page. Light-mode specific admin-theme selectors must also override global light text rules inside the black card so headings, labels, metadata and quotes retain readable contrast. PASS:318 synthetic browser assertions at320/375/768/1024/1440 in both modes; six phone/desktop/password previews inspected. [[90 System/Tasks/Records/TASK-20261120-015]] - [[90 System/Runs/2026-11-20 163311 Admin Settings UI v3]].

## Admin password action spacing v4 - 2026-11-20

Within the existing black/charcoal Settings card, keep Update password right-aligned and add `1rem` below its action row before the Role metadata divider. This is spacing only; do not introduce a white background or change the Settings palette. The focused browser test measures the button-to-divider gap and password card contrast in both Admin selector modes at320/375/768/1024/1440. PASS:368 synthetic browser assertions; light-mode screenshot capture was unstable, so computed layout/contrast assertions are the evidence for that mode. [[90 System/Tasks/Records/TASK-20261120-015]] - [[90 System/Runs/2026-11-20 165612 Admin Password Divider Spacing v4]].

## Branded seller maintenance v1.1 - 2026-11-20

Seller-only maintenance now uses the VNZ TECH logo/wordmark and blue accent, with theme-token surfaces/text, reason and estimated-return panels, public-availability note, Check status action, and Admin sign-in link. The runtime dialog preserves unsent form text, blocks seller mutations, has a visible 3px keyboard focus ring, cycles focus across the brand/status/action links, and uses contrast-checked theme accent status. Browser coverage passes public/seller/admin × light/dark ×375/768/1440 with no horizontal overflow; four synthetic previews inspected at375/1440. No authenticated portal, screen-reader, or physical-device acceptance. [[90 System/Tasks/Records/TASK-20261120-016]] · [[90 System/Runs/2026-11-20 175641 Branded Seller Maintenance Follow-up]]

## Admin page width and maintenance indicator v1.2 - 2026-11-20

Admin Profile and Chat Moderation main cards span the content area rather than occupying one track of the shared two-column grid; their inner responsive behavior remains. The Profile Role metadata now has visible separation from the form. Preserve the existing black Admin card surfaces in both themes; no white card background was added. The general Admin shell no longer shows a persistent maintenance banner; use Admin > Site Updates for status and controls. Public announcements and seller maintenance behavior are unaffected. PASS: 388 Moderation, 127 account/Profile, 337 site-status and 263 Site Updates synthetic browser assertions. Six light/dark previews across desktop/mobile widths inspected; no live account or physical-device acceptance. [[90 System/Tasks/Records/TASK-20261120-016]] · [[90 System/Runs/2026-11-20 184244 Admin UI Full-Width v1.2]]

## Admin Site Updates and maintenance v1.3-R2 - 2026-11-20

VNZ TECH branding is the default for Admin Site Updates. Keep its new cards black/charcoal in both Admin themes, scope those overrides to Admin, and explicitly override the light-theme `#contentArea` heading/label rules inside the dark cards. Enable/Disable are separate actions; only Enable confirms and requires a reason/estimate. Author guidance asks for simple Filipino or English and actionable user instructions; existing notices were not rewritten. The public Sell-page maintenance view also stays in the public theme when it is included in All seller services. Static and runtime maintenance views have no Admin sign-in CTA. The seller dashboard uses theme tokens for a scoped maintenance response. Browser checks cover both themes at 375/768/1440, including focus, contrast and horizontal overflow; representative previews were inspected. No live account/device/screen-reader result. [[90 System/Tasks/Records/TASK-20261120-016]] · [[90 System/Runs/2026-11-20 193429 Admin Site Updates and Service Modes v1.3-R2]]

## Admin Site Updates and chat theme v1.2 - 2026-11-20

The earlier v1.3-R2 Site Updates palette note above is historical. TASK-017 v1.1/v1.2 supersedes its dark-in-light treatment **only for the Admin Site Updates page**: cards, fields, guidance/status, controls and confirmation dialog now use light Admin surfaces/text in light mode; dark mode retains black/charcoal. The tabs follow Buying Rates' underline pattern and the Site Updates navigation icon is gray. Admin floating-chat window/header/chat/info/composer/workspace panels use separated neutral charcoal in dark mode and Admin light-theme tokens in light mode. Semantic message bubbles and action/status accents remain; use Admin topbar placement selectors instead of changing shared Seller/Public chat styles.

PASS: 815 Site Updates browser assertions at 375/768/1440 in light/dark, including fields/status/dialog/focus; 225 Admin topbar assertions at 320/375/768/1024/1440 in both themes, including panel and bubble colors. Synthetic page/widget/service fixtures and previews only; no live Admin or provider/chat verification, owner result acceptance pending. [[90 System/Tasks/Records/TASK-20261120-017]] · [[90 System/Runs/2026-11-20 202901 Admin Site Updates and Chat Palettes v1.2]]

## Admin module light-theme correction and maintenance toggle v1.3 - 2026-11-20

The owner's newer scope supersedes earlier black-in-light card guidance for Admin Chat Moderation, Activity Log, Profile, Security, and Settings: use active Admin light tokens in light mode and preserve the existing dark palette. Site Updates' Enable/Disable controls are now a single button that changes label/state on the same element; maintenance behavior and activation status remain unchanged. Keep Seller/Public styles separate. PASS: 211 Account/Activity Log + 388 Chat Moderation + 875 Site Updates browser assertions across light/dark and responsive widths; synthetic desktop previews inspected. No live Admin, physical device, screen-reader, maintenance activation, or deployment verification. [[90 System/Tasks/Records/TASK-20261120-018]] · [[90 System/Runs/2026-11-20 204827 Admin Light Theme and Maintenance Toggle v1.3]]

## Admin Site Updates header refinement v1.4 - 2026-11-20

Reuse the Reports `operations-header` markup and stylesheet when aligning Site Updates header hierarchy; keep the local `ADMINISTRATION` eyebrow but remove its redundant VNZ TECH logo/wordmark/tagline block. Rely on active Admin theme rules for readable eyebrow and description contrast, and verify the actual fixture loads the shared operations stylesheet. PASS: 1,037 browser assertions across light/dark at 375/768/1440px, plus inspected light 375/1440 and dark 1440 synthetic previews. No live Admin/device/screen-reader/deployment acceptance. [[90 System/Tasks/Records/TASK-20261120-019]] · [[90 System/Runs/2026-11-20 211328 Admin Site Updates Header Refinement v1.4]]

## Admin transaction modal layering v1.0 - 2026-11-20

Keep Admin modals above the global shell using the established Admin overlay layer; a viewport-sized backdrop must also receive hit tests over the topbar and sidebar. The transaction modal now uses `z-index: 11035`, matching the existing Admin submission modal and exceeding the topbar/sidebar layers. PASS: 81 browser assertions at 375/768/1440px in both themes; synthetic mobile and desktop previews inspected. No live Admin/device/screen-reader result. [[90 System/Tasks/Records/TASK-20261120-020]] · [[90 System/Runs/2026-11-20 212655 Admin Transaction Modal Layering v1.0]]


## TASK016 original base and local setup evidence - 2026-11-20 22:06:02 Asia/Manila

TASK016 original account split and Site Updates UI were delivered with rem text/proportion-based layout, active theme support, escaped announcement previews, themed maintenance confirmation and draft-preserving overlay. Dialog UA centering was defeated by the admin reset; explicit margin:auto fixed it and browser geometry checks caught it. Historical original preview/check coverage is in the base run; later Copilot palette/layout/service-mode decisions supersede old screenshots. Current task ownership retained. [[90 System/Runs/2026-11-20 220602 Site Updates Base Delivery Reconciliation]] - [[90 System/Tasks/Records/TASK-20261120-016]]
