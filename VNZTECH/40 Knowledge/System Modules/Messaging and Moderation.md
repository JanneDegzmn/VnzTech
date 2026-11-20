---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-20
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Echo]]"
---

# Messaging and Moderation

Owner: [[90 System/Agents/Echo|Echo]]. Review date: **2026-11-14**.

## Implemented in source

- Global/private/transaction conversation helpers plus trade-request and guest-submission conversation paths.
- Message history, send/edit/delete, replies, reactions, typing, read, and delivered actions.
- Membership checks, participant display-name rules, presence lifecycle, unseen counts, and reader information.
- Server-signed Scaledrone room authorization plus client reconnect/reconciliation logic.
- Configurable moderation word/phrase rules with enabled state, block/review decisions, and admin review actions; moderation applies to global chat.

Admin Settings UI (2026-11-20): Configured moderation rules are paginated client-side at 12 per page after filtering. The endpoint remains unchanged and still returns all rules; this improves rendered-list navigation, not response size. Search returns to page one, and empty results hide page controls. [[90 System/Tasks/Records/TASK-20261120-015]] - [[90 System/Runs/2026-11-20 161822 Admin Settings UI v2]]

Admin Settings UI v3 supersedes the earlier 12-item display size: at most 3 configured rules are rendered per page after filtering. Existing search, accessible pagination and clamp behavior remain; the endpoint still returns the full collection. PASS:318 synthetic browser assertions; no API/backend/data change. [[90 System/Tasks/Records/TASK-20261120-015]] - [[90 System/Runs/2026-11-20 163311 Admin Settings UI v3]]

## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `backend/chat_service.php`
- `backend/chat_conversations.php`
- `backend/chat_messages.php`
- `backend/chat_presence.php`
- `backend/chat_presence_store.php`
- `backend/chat_scaledrone.php`
- `backend/chat_scaledrone_auth.php`
- `backend/global_chat_moderation.php`
- `backend/global_chat_moderation_service.php`
- `assets/js/transaction-chat.js`
- `assets/js/floating-messages.js`
- `includes/floating-messages.php`

## Verification limits and open items

- Realtime provider connectivity, reconnection across browsers, and delivery/read behavior were not tested live.
- The pure global-moderation test passes. The Scaledrone authorization test exists but was not run because its bootstrap opens the configured database.

## Related tests

- `tests/global_chat_moderation_test.php`
- `tests/chat_scaledrone_auth_test.php`

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Aegis|Aegis]] · [[90 System/Agents/Lyra|Lyra]] · [[90 System/Agents/Flint|Flint]] · [[90 System/Agents/Quinn|Quinn]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]

## Seller topbar placement - 2026-11-20

includes/floating-messages.php exposes seller-only data-placement=seller-topbar; assets/js/floating-messages.js keeps existing routing/unread/presence/call integrations and adds selected-conversation visibility, category/list dropdowns and collapse events. Layout overrides live in seller_topbar.css; default floating mode remains the fallback for other actors. Collapsing hides widgets without destroying them; existing Close preserves active call owners in their host. Notification and chat panels exclude each other. Existing window-count limits remain; close an old chat before opening another when the limit is reached.

Synthetic browser evidence checks existing openTransactionChat entry, reopen without duplicate widget, collapse preserving widget, Close retaining active-call-owner node, unread badge and default bottom-fixed placement. No actual messages, media stream, call signaling, provider connectivity or multi-browser delivery was tested. Shared provider/transaction-chat controller is unchanged. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Seller category titles on touch - 2026-11-20

Floating category tooltips become persistent inline names in seller-topbar mode; explicitly set display:inline to override shared hover:none display:none, and clear inherited tooltip border/box-shadow/background. Shared admin/public tooltip behavior and chat JS/API remain untouched. Actual touch media plus light/dark screenshots checked within281 passing browser assertions. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Chat v3 approved dock/fullscreen - 2026-11-20

assets/css/seller_topbar.css separates menu dropdown from bottom conversation host. Maximized host removes dock constraints and fills viewport; host layer11006 covers the topbar launcher, while seller loader remains above header. Two seller-only state lines in floating-messages.js keep is-mobile-chat-open on maximize and clear is-workspace-list-open so the selected body is shown; intentional Back/list and default admin/public branches remain. Restore preserves widget/draft; existing Close/collapse retains call-owner nodes.

503 synthetic browser checks pass including two maximize/restore cycles per viewport/theme, full body/messages/input bounds, list-to-fullscreen transition, short screen, and unchanged widget/call-owner-node lifecycle. Actual PHP widget markup; no message send/call signaling/controller/provider/real media test or backend/API change. Existing chat window limits remain. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Seller Chat v4 lifecycle - 2026-11-20

floating-messages.js now separates transient menu state from mounted seller dock windows. Visible slot limit is2 at>=900px/1 below; mounted cap10 is separate. Each window retains stable dock key/order and originating category. Selecting/reopening raises it into a visible slot; displaced windows remain mounted. Overflow options use textContent and stable signatures so unrelated renders do not replace focused options. Hover close delay allows crossing the gap into the popup; ArrowDown/Up/Enter and click provide keyboard/touch alternatives.

Outside click closes only menus. Back minimizes its own window and opens originating topbar list/categories; Minimize retains widget; Close removes only that window using existing active-call-owner preservation. Fullscreen shows chosen chat and Escape/Restore returns prior slots. Existing message/provider/API and default admin/public paths remain unchanged; ordinary browser reload does not persist open-window DOM state. Five-row contacts retain call buttons. Capacity prevents an eleventh mounted window and displays a close-one message. Sources: assets/js/floating-messages.js + seller_topbar.css; sidebar-close synchronization lives in seller-topbar.js.

621 full browser assertions and187 focused fixture assertions pass: three independently mounted chats/drafts, visible slots/overflow, capacity, same-chat reopen, outside/fullscreen/bell/menu persistence, Back paths, pointer/keyboard selection and existing call-owner-node lifecycle. No live sends/calls/provider delivery or real media verification. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Chat v5 organization - 2026-11-20

Seller dock display capacity now calculated from available client width for360px boxes/12px gaps/reserved rail, bounded1..4; mounted cap remains10. Rail entries use existing per-window keys/order/origin; selecting promotes an existing window without recreating it. Rail signature includes avatar/name/capacity to preserve focused nodes on unrelated refresh. Existing local peer picture on authorized widget is accepted only for safe uploads paths without traversal; remote/unsafe/missing values fall back to initials. No new profile API/provider request. Avatar load failure removes just image, leaving initials.

Vertical rail selection supports pointer/keyboard and +N popup for entries beyond height budget. Selected loading/disabled-input widget gets header focus fallback rather than losing keyboard focus. +N popup bounded against viewport; fullscreen hides rail and Restore restores slots. Transport, unread topbar badge, call owner preservation, Back/menu and10-window error behavior unchanged. No reload-persistence addition.755 synthetic browser assertions pass; no live sends/calls/transport verification. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]


## Mobile Messages v1 - 2026-11-20T11:49:24+08:00

Seller-only <=1024 CSS px now opens a viewport-filling Messages inbox directly from topbar icon. Native DOM rows use existing authorized list/contacts/trade endpoints, existing last-message/unread/updated date fields when available, safe local uploads avatars or initials, real presence only. Search covers loaded authorized data; All/Unread/Private/Admin/Trades/Global filters, New message eligible contacts and supported Global entry when empty. No stories/AI/groups invented. Conversation uses existing widget full-screen; Back returns retained inbox search/filter/scroll; browser history holds in-app inbox/conversation entries. Explicit Close returns page; outside click does not dismiss. Widgets/drafts/call-owner nodes remain mounted, ten-chat cap unchanged. Desktop v5 geometry/rail preserved. Safe-area/visualViewport sizing, focus trap/modal labels, reduced motion and seller theme tokens supported. At <=400px height context card temporarily hidden and composer compact so messages/input stay reachable; context returns at taller height. Page scroll lock removed when closing or resizing to desktop. Initial inbox focuses heading, avoiding automatic keyboard opening. Existing server membership/CSRF/message/call transports unchanged. Inbox reads use current endpoints; selecting a new contact/global/trade uses existing open action. No independent provider integration. 216 mobile/tablet assertions PASS, plus 223 existing topbar/desktop regression assertions PASS. Actual production PHP topbar/widget markup and JS/CSS under synthetic API responses, no DB/session/providers. Mobile/tablet 320x667,375x812,768x1024,1024x768,812x375 in light/dark; additional812x260 short-height and1440 desktop resize. Verified direct inbox, authorized people/local avatar/presence, search/unread/New message, full-screen real-widget content/composer, outside-click persistence, Back/query/scroll/filter/draft restore, browser Back, explicit Close/Escape/focus wrap, delayed-open cancellation, loading/error/retry/empty, call-owner node retention and desktop resize/dimensions. Three final previews inspected: light/dark375 inbox and light375 conversation. No live sends/calls, actual device keyboard, physical phone/tablet, screen-reader or full authenticated admin matrix verification. [[90 System/Tasks/Records/TASK-20261120-012]].


### Alignment + Chat Space v2 - 2026-11-20T13:52:53+08:00

Seller submission/trade context now uses a native details/summary disclosure labelled View submission info, initially closed on each newly rendered widget. Expanding retains all original info/photo/payment fields in a bounded internal scroll area; collapsing restores conversation space without recreating widget/draft. Short keyboard-height view uses a bounded popup below the summary rather than consuming remaining message height. This supersedes v1 hiding the context entirely at very short height. Existing widgets retain disclosure state while switching; new widgets start closed. Seller message spacing reduced to6px; five short synthetic replies fit in tested normal viewports, not a universal five-message guarantee for long/media/call content or keyboard-reduced heights. Scroll-latest button centered at bottom of message area with centered SVG. Mobile sidebar close burger prepended and positioned left of branding with reserved padding; close/focus behavior retained. Desktop box dimensions, message/call transport and server permissions unchanged; admin/guest markup not wrapped. Source: includes/transaction-chat.php; assets/js/seller-topbar.js; assets/css/seller_topbar.css; tests/seller_chat_space_browser_test.mjs. PASS:77 new actual-component browser assertions across1200x800,375x812,768x1024,1024x768 light/dark (native disclosure/click/Enter, five short replies, draft and message/composer preservation, centered arrow/SVG, left burger/close);216 mobile/tablet regression assertions including812x260;223 existing topbar/desktop regressions. PHP syntax, two JS syntax checks and admin PHP widget unchanged-info guard PASS. Two inspected previews: light desktop and dark phone collapsed-info conversation. No live seller messages/calls/provider, physical devices/keyboard or screen-reader acceptance. [[90 System/Tasks/Records/TASK-20261120-012]].


## Admin UX + Queue v1 - 2026-11-20T14:43:32+08:00

Admin header now reuses portal chat styling/lifecycle with an authenticated admin-topbar placement. Bell reads permission-filtered aggregate updates; profile displays existing name/initial because current admin record has no photo field. Phone/tablet <=1024 opens full-screen inbox/conversation with actual private/seller-request/guest/trade/global categories; desktop uses bounded existing multi-chat docks/rail. Admin submission/trade info now starts collapsed like seller. Context has one bounded, keyboard-focusable scroll region with hidden visual scrollbars and no nested info scrollbar. Shared message history is distinguished from shell page history in both admin/seller Back handlers so Back/Close does not reload review content. Existing permission gate for mounting admin chat remains view_submissions; backend chat membership/CSRF unchanged. Previously dated TASK012 seller-only/admin-inline notes remain historical; current admin shares collapse/topbar behavior. Existing public/guest floating placement unchanged. No call-owner remount introduced. PASS:185 actual admin-component/endpoint/browser assertions on320/375/768/1024/1440 light/dark;235 SQLite query/pagination/synthetic-burst assertions;41 permission endpoint/controller assertions;16 pure seller next-step assertions; seller regressions77 chat-space +216 mobile/tablet +223 topbar/desktop assertions. PHP lint11 application files and JS syntax5 checks; existing admin decision beginTransaction/FOR UPDATE/status-validation ordering preserved by source check. Four final previews inspected: light375 queue/header, dark1440 multi-chat, light/dark375 inbox. Synthetic data/services only; no live account/provider/message/call writes, real MySQL concurrency/load test, physical phone/keyboard or screen-reader acceptance. [[90 System/Tasks/Records/TASK-20261120-013]].

## Admin moderation settings UI v1 - 2026-11-20

Presentation-only settings styles remove nested max-height list scrolling, align configured-rule/flagged-message columns independently, and improve labels/cards/actions, focus, destructive contrast, and both admin themes. Source `assets/css/admin_operations.css`, `assets/css/admin_theme.css`; 130 browser checks at320/375/768/1024/1440 light/dark. Synthetic moderation records only; no policy/action/API/backend change or production decision. [[90 System/Tasks/Records/TASK-20261120-015]] - [[90 System/Runs/2026-11-20 153700 Admin Moderation UI v1]].

## Admin floating-chat palette v1.2 - 2026-11-20

Only the Admin topbar placement in `assets/css/admin_topbar.css` overrides floating-chat window/header/chat/info/composer/workspace surfaces: neutral charcoal separated from the black Admin page in dark mode, Admin light-theme surfaces in light mode. Shared `transaction_chat.css`, Seller/Public palettes, message bubbles, action/status accents, sizing and chat behavior are unchanged. PASS: 225 actual-template/widget synthetic browser assertions across 320/375/768/1024/1440 and both Admin themes, including computed panel/bubble colors. No live message, call or provider verification; owner acceptance pending. [[90 System/Tasks/Records/TASK-20261120-017]] · [[90 System/Runs/2026-11-20 202901 Admin Site Updates and Chat Palettes v1.2]]

## Admin Chat Moderation light theme v1.3 - 2026-11-20

The Chat Moderation page now uses the active Admin light palette for its card, rule editor/list, flagged cases, controls, and semantic status surfaces; dark mode is unchanged. This supersedes older black-in-light guidance for the Admin moderation screen only. No moderation rules, decisions, API, or Seller/Public UI changed. PASS: 388 synthetic browser assertions at 320/375/768/1024/1440 in light/dark with contrast, focus, and overflow checks; no live moderation decision tested. [[90 System/Tasks/Records/TASK-20261120-018]] · [[90 System/Runs/2026-11-20 204827 Admin Light Theme and Maintenance Toggle v1.3]]
