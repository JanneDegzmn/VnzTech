---
type: agent-run
status: ready_for_review
created: 2026-11-19
updated: 2026-11-19
tags: [identity, submissions, selfie, implementation]
task_id: TASK-20261119-004
runner: Codex
---

# Submission Selfie v6

Owner **"Apply v6 at activate locally"**, explicit full-scope approval recorded21:33 Asia/Manila; exact message timestamp unavailable. [[90 System/Tasks/Records/TASK-20261119-004|Canonical scope]]. Root owns shared mode/gates/API/status/cleanup, activation and consolidated notes; separate writers implement helper/worker/schema/tests, public/seller camera UI, existing admin-review photo/protected route/tests. Old V6 files were empty; new work is attributed here. No real photos, private records or credentials go in notes.

Next: complete source and focused checks, privately back up affected config/schema and apply only additive local migration, then activate after model/storage/cleanup health. No cloud image transmission, new admin module, remote deployment or login change. Actual source/check/migration/activation evidence will be appended separately.

## Delivered source and responsibility

Root/Atlas reconciled the actual empty placeholders, recorded explicit approval and consolidated separate Forge/Aegis backend, Iris capture UI and Quinn/admin work. Attributable app paths: backend/submission_security_face.php and submission_face_photo.php; identity_automation.php/identity_verification.php/identity_api.php/submit_submission.php; tools/identity_match_worker.cjs + backend/identity_face_matcher.php presence protocol; sell.php/account/dashboard.php; includes/submission-face-capture.php; submission-face-capture.js/CSS; existing admin/dashboard.php, submission_review.php, retired identity_verifications.php and admin_submission_review.css. New additive photo/terminal-trigger SQL, activate_submission_face_v6.php, docs/submission-selfie-v6.md and historical-guide pointer; gitignored fail-closed marker. Earlier dirty v4/UI/vault source preserved. Shared test copy dependencies and old nav assertion updated, not attributed as a redesign of those features.

## Actual focused verification

| Command / scope | Result |
| --- | --- |
| PHP tests/submission_face_capture_test.php | 63 PASS; nonce/actor, encryption/hash, rollback/replay, expiry, actual blank refusal, shared orphan protection |
| Python tests/submission_face_submit_http_test.py | 36 PASS actual copied submit/API routes; positive local presence explicitly MOCKED, SMTP non-network fixture |
| Python tests/submission_face_admin_http_test.py | 24 PASS real copied endpoint/auth/crypto with synthetic JPEG and SQLite |
| Node tests/identity_match_worker_test.mjs | 33 PASS including real CPU single presence and legacy pair blank refusals |
| PHP tests/submission_face_mysql_test.php | 10 PASS actual disposable InnoDB FK/unique/first-terminal/rollback/seen-change trigger checks |
| Node tests/submission_face_capture_browser_test.mjs | 24 PASS;8 public/seller light/dark desktop/375 viewports, short-mobile scrolling, synthetic camera/token/POST/error lifecycle |
| Node tests/submission_face_admin_browser_test.mjs | 7 PASS;4 light/dark desktop/375 inline panels +3 honest unavailable states |
| Legacy helper/API/email/local matcher/browser checks | 48/26/10/13/24 PASS respectively; earlier modes use isolated fixtures |
| PHP lint / changed JS syntax / default git diff --check | 14 PHP PASS; JS PASS; whitespace PASS with normal pre-existing CRLF notices |
| Anonymous live local probes | 11 PASS: new guest form/token + old ID retirement + private/CLI denial |

Initial disposable MySQL test emitted a CLI session-path permission warning; fixture now sets the system temp session path, and clean rerun passes. Eight synthetic UI previews were generated; subagents inspected all, root independently inspected guest light/seller dark/admin dark375. No private image/screenshot contents copied into vault. Actual accepted face/camera, authenticated live admin/seller session, physical phone, SMTP and calibrated identity/liveness accuracy remain untested. No claim of identity verification from presence.

## Applied local setup and readback - verified21:50

Encrypted private configuration/schema backups created without printing values/reading user or evidence rows. New submission_security_faces table renders existing submissions.id exact BIGINT(20) UNSIGNED type; submissions/user/ID rows unchanged. New vnz_submission_face_terminal_v6 AFTER UPDATE trigger hashes to reviewed source and latches UTC first accepted/declined +30days. --migrate reported table_created/trigger_created true with activated false. --check verified key/model/hash/heartbeat/schema and actual blank worker refusal, --activate then reported ready/activated true, mode face_submission_v6, provider_request_sent false. Marker first + atomic config replacement prevents silent fallback, preserves unrelated private configuration.

Existing Windows cleanup task Ready/enabled, fixed PHP identity_evidence_cleanup.php action, lastresult0 at17:05:34, next2026-11-20 03:00 +08:00. No new task/service; expiry access denied before physical cleanup. Isolated shared-sweep tests prove referenced aged selfies remain and abandoned files delete. No live cleanup sweep/personal evidence processing/submission creation by this runner.

Current pointer/task/indexes, seven affected living modules, system map, one consolidated v6 coding entry, Integration Catalog and Engineering Lessons updated. Owner result acceptance pending; next action is refresh and real camera/device test, then review photo under existing Submissions. No further implementation/migration/activation approval needed for delivered scope.

Final readback:19 relevant notes/386 wikilinks checked with no missing targets/anchors; current/canonical approved-v6/active-mode fields agree, app-document link exists and default whitespace check passes. Metadata-only private readback confirms face_submission_v6/table/terminal-trigger active, exact BIGINT UNSIGNED FK type, provider_request_sent false. The two v6 synthetic preview directories were removed after inspection; pre-existing v4 previews/other work preserved. No further broad tests needed after documentation-only finalization.
