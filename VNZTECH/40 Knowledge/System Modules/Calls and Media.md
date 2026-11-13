---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-14
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Lyra]]"
---

# Calls and Media

Owner: [[90 System/Agents/Lyra|Lyra]]. Review date: **2026-11-14**.

## Implemented in source

- Audio/video signaling and persisted call-session events/summaries; media-stream ownership handling in the client.
- `chatGetOneToOneConversationPeer` restricts eligible calls to distinct seller participants in qualifying private conversations.
- Images, PDF documents, voice, and video have upload/storage helpers and configured limits.
- Membership-gated attachment serving includes byte-range support for voice/video.
- Legacy blank MP4/WebM message types are inferred only with valid stored names and nonzero sizes.

## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `backend/chat_call_signal.php`
- `backend/chat_call_sessions.php`
- `backend/chat_attachments.php`
- `backend/chat_attachment.php`
- `backend/chat_service.php`
- `assets/js/transaction-chat.js`
- `includes/transaction-chat.php`
- `includes/transaction-chat-window.php`

## Verification limits and open items

- Admin and guest messaging support must not be described as admin/guest call eligibility.
- The legacy attachment-type test passes; microphone/camera permissions, ICE connectivity, recordings, uploads, and playback were not exercised in a browser.

## Related tests

- `tests/chat_attachment_message_type_test.php`
- `tests/chat_scaledrone_auth_test.php`

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Echo|Echo]] · [[90 System/Agents/Aegis|Aegis]] · [[90 System/Agents/Iris|Iris]] · [[90 System/Agents/Quinn|Quinn]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]
