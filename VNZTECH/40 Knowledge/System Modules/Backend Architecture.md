---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-19
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Forge]]"
---

# Backend Architecture

Current local behavior: [[90 System/Runs/2026-11-19 213300 Submission Selfie v6|submission selfie v6]], active2026-11-19. Earlier dated ID-review sections below remain history.

Owner: [[90 System/Agents/Forge|Forge]]. Review date: **2026-11-14**.

## Implemented in source

- PHP/PDO application organized into public entry pages, account/admin portals, shared includes, backend handlers, and SQL migrations.
- Shared helpers for sessions, CSRF, admin authorization, pagination, offer ownership, messaging, and delivery-state rules.
- Handlers use JSON or redirect responses, HTTP method checks, validation, and transactional operations where implemented.
- Composer declares PHPMailer; npm declares qrcode-generator. Dependency declarations do not independently establish feature behavior.
- Separate database bootstraps exist; chat authorization tests can transitively load the configured database.

## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `backend/auth.php`
- `backend/session.php`
- `backend/csrf.php`
- `backend/pagination.php`
- `backend/offer_response_authorization.php`
- `backend/chat_service.php`
- `backend/transaction_delivery_workflow.php`
- `composer.json`
- `package.json`
- `.htaccess`

## Verification limits and open items

- Cross-module behavior was assessed from source; no complete running-application integration test was performed.
- Do not generalize protection on an inspected endpoint to every handler without checking it.

## Related tests

Walang dedicated test na natukoy para sa buong module sa kasalukuyang test directory.

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Aegis|Aegis]] · [[90 System/Agents/Vega|Vega]] · [[90 System/Agents/Quinn|Quinn]] · [[90 System/Agents/Atlas|Atlas]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]

## ID verification v2 - 2026-11-19

Identity helper now separates enabled policy from health: an enabled maintenance/storage/config failure blocks requests rather than switching policy off. Private storage must be outside the entire documentroot, not only this checkout. Grant consumption is inside submission transaction; signup reservation/email finalization use caller transactions. Cleanup heartbeat checks actual freshness. New tools handle additive setup/health and reviewed administrator completion; active capture disabled pending a successful local schedule. See shared lessons for boundaries and deployment limits.

[[90 System/Runs/2026-11-19 163005 Identity Verification v2]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]

### Activation follow-up - 2026-11-19 17:07

Earlier disabled/setup-pending state is historical. Owner Administrator setup completed; independent live form/API/private health and successful daily03:00 cleanup readback confirm local activation. No personal ID/device/human-review test or owner acceptance inferred. [[90 System/Runs/2026-11-19 163005 Identity Verification v2]]

## Submission selfie v6 - 2026-11-19 21:53

v6 separates legacy ID enabled state from mandatory submission selfie mode. Server helper performs bounded single-image presence inference through the fixed Node CPU worker; no browser score or self-comparison authorizes a face check. Checked JPEG/hash, actor/session/context nonce and policy bind transactional Store; raw bytes stay private. nonce API is CSRF-protected and old ID actions retire410. A mode marker prevents silent fallback. No cloud call, new runtime dependency, service or login change.

[[90 System/Runs/2026-11-19 213300 Submission Selfie v6]] - [[90 System/Tasks/Records/TASK-20261119-004]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]
