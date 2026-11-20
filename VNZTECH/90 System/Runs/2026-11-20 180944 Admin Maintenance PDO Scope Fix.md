---
type: agent-run
status: partial
created: 2026-11-20
updated: 2026-11-20
tags: [runs, maintenance, php, verification]
---

# Admin maintenance PDO-scope fix

2026-11-20 18:09:44 Asia/Manila. Runner: Copilot SDK in VS Code (Atlas/Iris). The owner reported `Call to a member function prepare() on null` at `backend/auth.php:53`, reached from the Admin dashboard maintenance guard. No new approval was requested; the fix is within the existing owner-approved v1.1 promise that authenticated Admin access remains available. No agent handoff.

## Cause and change

`backend/auth.php` was first required from inside `vnzEnforceSiteMaintenance()`. PHP executes included files in the caller's variable scope, so the nested database bootstrap assigned `$pdo` locally while `getCurrentAdmin()` reads it globally. `backend/site_status.php` now imports global `$pdo` before requiring auth.

`tests/site_status_guard_test.mjs` now copies the real `backend/auth.php` and uses an isolated SQLite database/session fixture. It verifies active Admin bypass through the actual `getCurrentAdmin()` query path and retains invalid/inactive Admin, public, seller, and identity-action coverage.

## Verification

- The new regression assertion failed before the fix.
- `node tests/site_status_guard_test.mjs`: **25 PASS**.
- `node tests/site_status_routes_test.mjs`: **211 PASS**.
- `C:\xampp\php\php.exe tests\site_status_service_test.php`: **45 PASS**; malformed-state fail-closed log messages are expected.
- PHP lint: `backend/site_status.php` and `backend/auth.php` **PASS**.
- `node --check tests/site_status_guard_test.mjs`: **PASS**.

## Limits and next step

The regression uses isolated SQLite, not the live MySQL database or an authenticated browser session. Maintenance remains inactive; no migration, deployment, or live runtime check was performed. Broader TASK-016 Plan v1 work and owner acceptance remain pending. Next safe action: reconcile remaining Plan v1 work and ownership.

[[90 System/Tasks/Records/TASK-20261120-016]] · [[40 Knowledge/System Modules/Backend Architecture]] · [[40 Knowledge/System Modules/Verification and QA]] · [[40 Knowledge/Engineering Lessons]]
