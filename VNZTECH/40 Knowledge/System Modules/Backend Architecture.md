---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-14
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Forge]]"
---

# Backend Architecture

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
