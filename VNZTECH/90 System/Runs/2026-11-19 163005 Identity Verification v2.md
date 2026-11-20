---
type: agent-run
status: ready_for_review
created: 2026-11-19
updated: 2026-11-19
tags: [identity, submissions, implementation, lessons]
task_id: TASK-20261119-004
runner: Codex
---

# Identity Verification v2

Owner approved **“Apply mo na ang V2”** and requested recurring API/third-party knowledge plus lessons for agents. Exact message time unavailable; approval recorded 16:30 Asia/Manila. Login upgrades remain deferred in [[90 System/Tasks/Records/TASK-20261119-005]]. Main Atlas writer coordinated separate backend (Aegis/Forge/Vega), UI (Iris), signup/security review (Cipher/Quinn), and local setup/model/browser/knowledge work. Workers supplied checks/lessons; this is the single consolidated implementation record.

## Implemented scope

- Private ID+selfie enrollment before item submission; face-api.js 0.22.2 is self-hosted and assistive. Exact evidence version requires an active, explicitly permitted admin's human decision. No client score grants approval. Account once until revoke; guest one-use draft/session/email grant consumed with submission transaction.
- Secure exact guest attempt reservation during signup, verified matching email finalization, pending remaining pending, no conflict/replay/expired approval promotion. Existing submission/chat claims remain independent. Password signup CSRF+atomic user/reservation, SMTP after commit with safe file cleanup; login/TOTP/remember/provider session completion unchanged.
- Shared themed public/seller card and restricted admin queue, camera lifecycle/blob cleanup, retry/error/pending/expiry/revocation feedback, UTC handling, draft/BFCache refresh. Private ID controls are unnamed in the ordinary item form.
- Authenticated AES-GCM encrypted, stripped/re-encoded JPEG evidence outside the entire webroot; active-owner/reviewer access, no-store/nosniff responses. Pending/abandoned/decided expiry, narrow cleanup CLI and 48-hour maintenance heartbeat. Durable account approval survives raw-image purge. Expired evidence becomes unreadable immediately; physical removal happens on cleanup cadence.

Attributable source: `backend/identity_verification.php`, `identity_api.php`, `identity_evidence_cleanup.php`, `submit_submission.php`, `register.php`, `social_signup_complete.php`, `verify_email.php`; `sell.php`, account register/dashboard, admin dashboard/page, shared include/CSS/JS; additive `database/migrations/20261119_create_identity_verification.sql`; pinned vendor bundle/license/models/manifest; focused tests; `tools/fetch_identity_assets.py`, `setup_identity_private.ps1`, `complete_identity_setup.ps1`, `deploy_identity.php`; `.gitignore` nonsecret instance marker. Existing registration styling/avatar/settings/other vault work preserved and not attributed to this feature.

## Actual verification

- PHP helper: **48** synthetic checks pass (encryption/tamper, roles/active actors, grants/replay/rollback, carryover/expiry, retention/paths and disabled/maintenance policy).
- Actual temporary HTTP routes: **17** API/submission checks pass, **11** signup checks pass, **10** existing email-verification regressions pass. Synthetic SQLite/accounts/images/mail/provider boundaries; no live users, SMTP or tokens.
- Actual isolated InnoDB: **10** checks pass, including two concurrent workers with exactly one committed submission/audit, loser rollback, grant restoration and serialized revocation. Test-owned database/files/sessions removed; no application user rows queried or changed.
- Real Chrome model test: all **9** pinned assets match recorded size/SHA; all three models load on CPU, blank canvas returns zero faces, descriptor-distance API works. No recognition accuracy/liveness claim.
- Chrome UI: **12** public/seller/admin light/dark ×1200/375 cases pass with conflicting inactive-area theme flags, actual partial/templates/scripts/styles, keyboard focus, overflow/readability, pending/approved/unavailable gate and draft preservation. Mocked model failure/no/multiple face/retry; synthetic canvas camera capture stops tracks, cancel stops tracks and clears blobs. Seven screenshots inspected across main/UI reviewers; synthetic missing admin images show expected private-read error. Later full-area stylesheet rerun also passes. Screen-reader, real camera/ID/physical-device/other-browser checks not run.
- **14** PHP files lint pass; changed JS syntax and repository-default whitespace check pass. **7** read-only localhost probes pass: public page/assets/status accessible, evidence denied, CLI route404, private key/config URLs404. Initial fixture PHP INI short-path/focus/area-token expectations were corrected; no hidden test failures claimed as product success.

## Applied local state and remaining blocker

- Additive migration applied after private backup of affected permission tables; three identity tables present, only `Super Admin` receives `identity_review`. No users/submissions migrated or retroactively approved.
- Private root/key/config created outside webroot; restricted DACLs verified without outputting keys. Configuration remains `enabled=false`; private key is never committed. Atomic key setup refuses replacement when encrypted evidence exists.
- Windows denied both SYSTEM and current-user scheduled-task creation (Access denied). The standard administrator elevation attempt returned **operation canceled**; no successful cleanup schedule or activation inferred. Capture/gate rollout is disabled until a fresh successful daily03:00 cleanup and private health are verified. This is an OS privilege issue, not a new implementation-approval request. Do not repeat elevation automatically after cancellation.
- Setup validates exact task action, enabled daily03:00 trigger, approved principal and fresh run/heartbeat before activation. Partial migration recovery requires inspecting additive schema rather than blindly reapplying; no destructive rollback.

Next: complete the standard Administrator setup (`tools/complete_identity_setup.ps1`), verify app status and cleanup readback, then owner tests their ID/camera locally and reviews mobile HTTPS behavior. Never request their ID in chat. Owner acceptance pending.

## Agent knowledge

Created [[40 Knowledge/Integration Catalog]] and [[40 Knowledge/Engineering Lessons]], linked from Knowledge/Agent Directory/Workflows and repository/vault instructions. API version/provenance/data flow/limits, transaction/carryover/replay, active actors, UTC, camera lifecycle, private storage and Windows permission/INI lessons are shared evidence. Relevant agents read matching entries on future tasks; no model-retraining claim or full-history duplication.

[[90 System/Tasks/Records/TASK-20261119-004]] · [[90 System/Coding Updates]] · [[40 Knowledge/System Modules/Identity and Accounts]] · [[40 Knowledge/System Modules/Verification and QA]]

## Activation verified - 2026-11-19 17:07 Asia/Manila

Owner supplied successful Administrator completion output and "Alright". This acknowledges setup, not a personal ID/device test or result acceptance. Root independently verified: live guest API HTTP200 success/enabled/available true, status not_started/approved false, no-store; public selling form renders enabled. Unowned attempt0 evidence403; CLI/private key/config URLs404. No evidence uploaded or user records read.

Cleanup task Ready/enabled, fixed PHP action, daily03:00 +08:00, last result0 at17:05:34, next run2026-11-20 03:00. Private setup-result success17:05:37, fresh heartbeat, instance marker and encryption/schema health confirmed. Normal-token metadata access was denied; authorized elevated readback succeeded without repeating setup. Earlier disabled/blocked evidence remains historical; activation blocker resolved. Ready for owner local ID/selfie/admin review and actual device test; acceptance pending, login task005 deferred.

No application/config changes or broad test rerun for this readback; existing coding entry and living notes updated.
