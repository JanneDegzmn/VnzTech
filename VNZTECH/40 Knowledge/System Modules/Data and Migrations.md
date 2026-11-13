---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-14
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Vega]]"
---

# Data and Migrations

Owner: [[90 System/Agents/Vega|Vega]]. Review date: **2026-11-14**.

## Implemented in source

- Migration sources cover admin/seller 2FA, verification/reset attempts, notification preferences, and remember-login tokens.
- Messaging migrations cover conversations, participants, reactions, presence, receipts, attachments, and call sessions.
- Guest identity/claim proof, global moderation, and trade-request chat schema changes are present.
- The recent OAuth identity and transaction delivery migrations are part of the source inventory.
- PDO connection helpers and transactional workflows refer to the existing operational schema; the repository migrations are not a complete verified database dump.

## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `database/migrations/20261006_add_admin_2fa_and_activity_log.sql`
- `database/migrations/20261006_add_admin_totp_login_preference.sql`
- `database/migrations/20261006_add_seller_2fa.sql`
- `database/migrations/20261006_add_seller_totp_login_preference.sql`
- `database/migrations/20261006_create_admin_password_reset_attempts.sql`
- `database/migrations/20261006_create_email_verification_tokens.sql`
- `database/migrations/20261006_create_seller_notification_preferences.sql`
- `database/migrations/20261006_create_seller_password_reset_tokens.sql`
- `database/migrations/20261008_add_chat_message_features.sql`
- `database/migrations/20261008_create_messaging_tables.sql`
- `database/migrations/20261009_add_presence_and_delivery_receipts.sql`
- `database/migrations/20261010_add_chat_image_attachments.sql`
- `database/migrations/20261010_add_pdf_attachment_metadata.sql`
- `database/migrations/20261010_add_presence_logout_timestamp.sql`
- `database/migrations/20261010_add_seller_email_to_submissions.sql`
- `database/migrations/20261010_add_voice_message_type.sql`
- `database/migrations/20261010_create_chat_call_sessions.sql`
- `database/migrations/20261011_add_guest_submission_chat.sql`
- `database/migrations/20261012_create_guest_submission_claims.sql`
- `database/migrations/20261012_extend_guest_submission_claim_proof.sql`
- `database/migrations/20261026_add_video_chat_message_type.sql`
- `database/migrations/20261026_create_remember_login_tokens.sql`
- `database/migrations/20261027_add_global_chat_fuckyou_rule.sql`
- `database/migrations/20261027_create_global_chat_moderation.sql`
- `database/migrations/20261027_create_trade_request_chats.sql`
- `database/migrations/20261112_create_user_oauth_accounts.sql`
- `database/migrations/20261114_add_transaction_delivery_choice.sql`

## Verification limits and open items

- The baseline review applied no migrations and inspected no live schema. A later Remember me repair confirmed the local `remember_login_tokens` table was missing, applied the existing `database/migrations/20261026_create_remember_login_tokens.sql`, and verified its required columns. This establishes only that local migration; other migrations/deployments remain unverified.
- A rolled-back MySQL check verified token insertion, hashed validator storage, and expiry, then confirmed no test token remained. No account/submission records were changed. See [[90 System/Runs/2026-11-14 050800 Remember Login and Theme Consistency]].
- Presence of a migration file establishes intended schema change, not deployment state.
- Review the documented direct-accept offer relationship and test-fixture drift with Orion/Aegis/Quinn before declaring compatibility.

## Related tests

- `tests/guest_submission_link_test.php`
- `tests/remember_login_http_test.py` uses disposable SQLite/session fixtures; the separate local MySQL rollback check is recorded in the run.

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Forge|Forge]] · [[90 System/Agents/Mira|Mira]] · [[90 System/Agents/Orion|Orion]] · [[90 System/Agents/Quinn|Quinn]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]
