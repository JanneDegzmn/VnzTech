---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-14
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Peso]]"
---

# Buying Rates

Owner: [[90 System/Agents/Peso|Peso]]. Review date: **2026-11-14**.

## Implemented in source

- Rate create/update, active toggles, archive/restore, and permanent-delete actions.
- Public read-only endpoint returns only active rates with `archived_at IS NULL`.
- Public payload includes rate type, category, description, price, and unit.
- The public selling page fetches the endpoint when opening the Current Buying Rates modal.
- Displayed rates are described as subject to inspection and verification.

## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `backend/manage_buying_rate.php`
- `backend/get_public_rates.php`
- `admin/pages/buying_rates.php`
- `sell.php`
- `assets/css/admin_buying_rates.css`

## Verification limits and open items

- Live database values and public/admin consistency were not exercised.
- Rate display code is evidence of a pricing reference, not proof of an automatic valuation engine or guaranteed final purchase price.

## Related tests

Walang dedicated test na natukoy para sa buong module sa kasalukuyang test directory.

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Iris|Iris]] · [[90 System/Agents/Orion|Orion]] · [[90 System/Agents/Rhea|Rhea]] · [[90 System/Agents/Quinn|Quinn]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]
