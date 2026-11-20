---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-20
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Vega]]"
---

# Data and Migrations

Current local behavior: [[90 System/Runs/2026-11-19 213300 Submission Selfie v6|submission selfie v6]], active2026-11-19. Earlier dated ID-review sections below remain history.

Owner: [[90 System/Agents/Vega|Vega]]. Review date: **2026-11-14**.

## Implemented in source

- Migration sources cover admin/seller 2FA, verification/reset attempts, notification preferences, and remember-login tokens.
- Messaging migrations cover conversations, participants, reactions, presence, receipts, attachments, and call sessions.
- Guest identity/claim proof, global moderation, and trade-request chat schema changes are present.
- The recent OAuth identity and transaction delivery migrations are part of the source inventory.
- PDO connection helpers and transactional workflows refer to the existing operational schema; the repository migrations are not a complete verified database dump.
- `database/migrations/20261119_add_bulk_submission_format.sql` adds `submissions.bulk_format` and `submission_items.quantity_is_estimated`, both non-null and defaulting to `0` for legacy rows. This distinguishes the new seller account bulk format from legacy wholesale/public records. The migration file exists but **was not applied**; no current database schema/deployment claim is made. Focused compatibility and persistence behavior is covered by the isolated 41-case submission HTTP test. [[90 System/Runs/2026-11-20 003109 Seller Bulk Submission Flow]].

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

## ID verification v2 - 2026-11-19

Applied additive database/migrations/20261119_create_identity_verification.sql locally after private permission-table backup: identity_attempts, identity_bindings, identity_audit and identity_review permission, granted only to active Super Admin. User/submission records not migrated or autoapproved. Private encrypted evidence/key/config are outside webroot and Git; restricted DACL verified. Daily cleanup task registration denied by Windows; rollout disabled, not an applied scheduler. Isolated InnoDB race/rollback/revoke test passes10 checks. Partial schema recovery requires inspection, not blind reapply.

[[90 System/Runs/2026-11-19 163005 Identity Verification v2]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]

### Activation follow-up - 2026-11-19 17:07

Earlier disabled/setup-pending state is historical. Owner Administrator setup completed; independent live form/API/private health and successful daily03:00 cleanup readback confirm local activation. No personal ID/device/human-review test or owner acceptance inferred. [[90 System/Runs/2026-11-19 163005 Identity Verification v2]]

## Submission selfie v6 - 2026-11-19 21:53

Applied additive submission_security_faces table and vnz_submission_face_terminal_v6 AFTER UPDATE trigger after encrypted private config/schema backup; no existing rows rewritten. Local submissions.id is BIGINT(20) UNSIGNED, so CLI renders its exact type into the new FK column rather than blindly applying INT. Trigger latches first accepted/declined UTC timestamp and30-day purge; seen/notification updates cannot extend it. Same submission transaction + unique nonce enforce binding/replay; cleanup preserves referenced photos and removes expired/orphan evidence. Existing daily03 cleanup remains Ready/enabled.

[[90 System/Runs/2026-11-19 213300 Submission Selfie v6]] - [[90 System/Tasks/Records/TASK-20261119-004]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]


## TASK016 original base and local setup evidence - 2026-11-20 22:06:02 Asia/Manila

TASK016 local setup was actually applied by Codex: database/migrations/20261120_create_site_updates.sql created site_announcements/site_maintenance and two Super Admin-only initial permissions after a private base-table/data/trigger backup. Private snapshot/readiness configured outside htdocs; maintenance OFF. Current --health PASS and16 MySQL temporary-table assertions PASS without persistent test rows. Backup excludes server/files/views/routines/events; keep it private. tools/site_updates_setup.php supports health/local recovery; snapshot/DB publication is not crash-atomic. [[90 System/Runs/2026-11-20 220602 Site Updates Base Delivery Reconciliation]] - [[90 System/Tasks/Records/TASK-20261120-016]]
