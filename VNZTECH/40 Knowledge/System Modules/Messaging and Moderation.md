---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-14
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
