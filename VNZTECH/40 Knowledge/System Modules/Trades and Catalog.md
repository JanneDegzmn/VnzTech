---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-20
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Flint]]"
---

# Trades and Catalog

Owner: [[90 System/Agents/Flint|Flint]]. Review date: **2026-11-14**.

## Implemented in source

- Trade-item create/update/delete, active and featured toggles, sort order, image-upload handling, and requirements management.
- Seller trade requests validate authenticated identity, an active item, and configured requirements before insertion.
- Admin approve/reject/cancel/complete actions use `manage_trades` and locked status transitions.
- The admin Trade Requests list prioritizes PENDING, then APPROVED, then other statuses; requests sort newest-first within each group so a recent resolved request cannot visually precede all pending work.
- Featured trade cards/carousel and seller trade-history/admin request views exist.
- Trade-request conversations have separate authorization/context helpers and a migration.

## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `backend/manage_trade.php`
- `backend/trade_requests.php`
- `admin/pages/trade_items.php`
- `admin/pages/trade_requests.php`
- `tests/admin_trade_request_order_test.py`
- `account/pages/trade_history.php`
- `sell.php`
- `backend/chat_service.php`
- `database/migrations/20261027_create_trade_request_chats.sql`

## Verification limits and open items

- `tests/admin_trade_request_order_test.py` exercises the actual admin SQL ordering clause against synthetic rows. It covers pending-first priority, newest-first order within the pending group, approved-next priority, and newest-first ordering among other statuses; it does not exercise the full trade lifecycle.
- Catalog images, requirement edge cases, request status changes, and linked chat behavior were not tested through the running UI.

## Related tests

Focused query-order regression: `tests/admin_trade_request_order_test.py`. No full trade-lifecycle UI/database regression is recorded.

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Echo|Echo]] · [[90 System/Agents/Rhea|Rhea]] · [[90 System/Agents/Iris|Iris]] · [[90 System/Agents/Quinn|Quinn]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]
