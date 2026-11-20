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
