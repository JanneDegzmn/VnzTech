---
type: agent-run
status: reconciled
created: 2026-11-20
updated: 2026-11-20
tags: [runs, site-updates, local-setup, reconciliation]
---

# Site Updates base delivery reconciliation

2026-11-20 22:06:02 Asia/Manila - Codex/Atlas records its original approved Plan v1 implementation/setup evidence after detecting later owner-directed Copilot revisions in the shared checkout. Owner approval: **"Alright apply mo safely."**, recorded in TASK016. This record does not take over TASK021/016, reverse later approvals, or infer owner acceptance.

## Base implementation attributable to this Codex run

- Seller Profile/personal details/email verification, Security/password/authenticator/login preference, Settings/appearance/language/notifications. Session-owned profile save now checks CSRF and preserves stored language when omitted. Admin own Profile/Security/Appearance available to active admins; moderation/activity remain privileged with existing pagination.
- Admin Site Updates, announcement draft/publish/archive/expiry/pagination/escaped preview and public list/banner; distinct manage_announcements/manage_maintenance permissions, initially Super Admin only. Internal PHP/PDO and browser DOM/polling; no new provider, outgoing broadcast or package.
- Snapshot-backed maintenance guard at87 HTTP boundaries; exact admin recovery/status/logout exceptions, JSON/HTML503/no-store/Retry-After, private atomic file publication, serialized writers/stale versions, local recovery tool and isolated runtime overlay preserving text drafts/releasing chat media. Later owner-approved service-mode/public-page and Admin palette revisions supersede original broad blocking/presentation; do not restore original semantics from this snapshot.
- Source groups: account/{dashboard,pages/profile,pages/settings,pages/security}.php; admin/{dashboard,pages/profile,pages/security,pages/settings,pages/moderation,pages/activity,pages/announcements}.php; backend/{site_status,admin_site_status,site_status_updates,update_profile,update_admin_settings,verify_email,seller_2fa}.php; guarded HTTP entries in tests/site_status_routes.json; announcements.php; includes/site-status-head.php; assets/{css/site-status,css/seller_account,js/site-status,js/admin-site-updates,js/transaction-chat,js/seller-language,js/seller-language-copy}; .gitignore; migration/setup/test files below. Existing user work in shared files was retained.

## Actual local setup, not merely prepared source

Executed `C:\xampp\php\php.exe tools/site_updates_setup.php --apply` under the owner's approved local setup scope. A private consistent base-table DDL/data and trigger backup was created outside htdocs before additive DDL. Applied only database/migrations/20261120_create_site_updates.sql: site_announcements/site_maintenance plus two permissions and active Super Admin grants. Snapshot/readiness configured. Output confirmed both tables, configured=true, state_valid=true and maintenance_active=false. No unrelated migrations, real announcement publication, activation, login/provider change or deployment.

Private root: `C:\xampp\private\VNZ-TECH\site-status`; do not publish backup/state files. Snapshot is nonsecret operational state; backup can contain private database values and is not vault content. The backup covers base tables/data/triggers, not a full server/files/views/routines/events disaster-recovery image. Recovery: local CLI `tools/site_updates_setup.php --recover-off`; database mirror reconciles on next admin save. File/DB ordinary errors compensate, but publication across two stores is not crash-atomic.

Final current-source recheck at 2026-11-20 22:06:02 Asia/Manila: `--health` PASS (configured/valid, both tables, two Super Admin grants, maintenance OFF); `tests/site_status_mysql_test.php` **16 PASS** using connection-local TEMPORARY tables shadowing the three service tables plus a private temporary snapshot. No persistent announcement/maintenance/audit rows were changed by these tests. Local unauthenticated HTTP smoke earlier PASS: status reader200 valid JSON/off/no announcements; announcements.php200 HTML; admin/login.php200 HTML.

## Earlier isolated checks, historical before later Copilot edits

Seller account156; admin account42 PHP +103 browser; Site Updates251 browser including centered themed dialog; service43 PHP; guard18 HTTP; route/source178 across87 entries; client229 browser; MySQL16; regression admin topbar215, seller topbar225, seller mobile226, chat-space77, queue235+41. PHP lint99 and JS syntax11 PASS; changed-line whitespace PASS. Synthetic source/browser checks cover both themes and375/768/1440; other regressions cover additional widths. Original seller and Site Updates previews inspected, including mobile confirmation/desktop editor; newer UI previews belong to later Copilot records, not this run.

Two fixture integration failures were corrected: eval stubs needed the actual no-side-effect helper/require stripping after guard insertion; native dialog close is asynchronous, so fixture waits for removal before resubmit. No product guard was weakened. A CLI session path sandbox warning was resolved by per-process CLI temp session storage, not application/session configuration. Python timezone-library lookup failed before intake writes and used explicit UTC+08 fallback without install.

These old counts do not certify later Copilot source. Current TASK016 amendments and TASK017-021 retain their own evidence/remaining work. Physical device, screen-reader, real provider/calls, authenticated admin publishing/activation and production load/deployment remain unverified by this run.

## Shared-checkout and Git reconciliation

Checkout advanced to658a2e2 (`UI and UX modifications`) with owner/Copilot work and current pointer TASK021. Two last Codex style/area-attribute additions were removed exactly (seven own lines) after identifying newer approved palette/behavior; no user commits/changes were reset. Current pointer and later task ownership preserved. No Git commit/push/merge/rebase/reset executed by Codex. Local tracking reports28ahead/1behind; remote-tracking branch is a standalone README-only initial commit and `git merge-base HEAD newrepo/VnzTech` exits1. Correct user advice is a normal merge with --allow-unrelated-histories, not forced push. Fresh fetch may reveal more remote changes.

This run appends missing original-base/setup/lesson evidence to existing living notes and coding ledger; it does not mark broader TASK016 complete or overwrite later acceptance status. Next: owner commits pending documentation, integrates remote history, resolves any README conflict and pushes; current product-review tasks remain with their recorded runner.


[[90 System/Tasks/Records/TASK-20261120-016]] - [[90 System/Tasks/Current Task]]
